<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\Car\CarAdministrationService;
use ElanRegistry\Car\CarImageProcessor;
use ElanRegistry\Car\CarRepository;
use ElanRegistry\Exceptions\CarConcurrentModificationException;
use ElanRegistry\Resize;

use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end integration tests for the car image lifecycle.
 *
 * Exercises the real primitives used by app/api/cars/save.php's uploadImages()
 * (secure filename generation, GD resizing, CAS-guarded image JSON persistence)
 * against a real database and a synthetic temp image root, then covers decode,
 * car deletion, and concurrent-modification handling.
 *
 * All filesystem work happens under sys_get_temp_dir() — never the real
 * userimages/ tree, whose car-ID subdirectories are shared between the dev and
 * test databases and could collide with a generated test car ID.
 */
#[Group('integration')]
final class CarImageLifecycleTest extends IntegrationTestCase
{
    /**
     * Thumbnail sizes generated per upload, read from the same
     * ELAN_IMAGE_THUMBNAIL_SIZES constant app/api/cars/save.php's
     * uploadImages() uses (#1067 — was a $settings->elan_image_thumbnail_sizes
     * DB read prior to this), so a production config change can't silently
     * drift out of sync with this test.
     *
     * @var list<int>
     */
    private array $thumbnailSizes;

    private int $testUserId;
    private int $testCarId;
    private string $tempRoot;
    private string $imageDir;
    private CarRepository $repo;
    private CarImageProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->thumbnailSizes = array_map('intval', array_map('trim', explode(',', ELAN_IMAGE_THUMBNAIL_SIZES)));

        $this->testUserId = $this->createTestUser();

        // image => '' (not NULL) so the first CAS write's expectedJson baseline matches:
        // MySQL's `WHERE image = ''` never matches a NULL column.
        //
        // No try/catch around this: requireDatabase() above already confirmed the DB is
        // reachable, so a RuntimeException here means a real fixture/schema regression,
        // not an environment issue — it must fail the test, not skip it silently.
        $this->testCarId = $this->createTestCar($this->testUserId, ['image' => '']);

        // random_bytes(), not uniqid(): the directory name must not be guessable by
        // another local process racing to pre-create it as a symlink.
        $this->tempRoot = sys_get_temp_dir() . '/elanregistry-imgtest-' . bin2hex(random_bytes(8)) . '/';
        $this->imageDir = $this->tempRoot . $this->testCarId . '/';
        if (!mkdir($this->tempRoot, 0700) || !mkdir($this->imageDir, 0700)) {
            $this->fail("Could not create temp image directory: {$this->imageDir}");
        }

        $this->repo = new CarRepository($this->db);
        $this->processor = new CarImageProcessor($this->repo);
    }

    protected function tearDown(): void
    {
        if (isset($this->tempRoot) && is_dir($this->tempRoot)) {
            $this->recursiveRemoveDirectory($this->tempRoot);
        }

        parent::tearDown();
    }

    /**
     * An upload writes the base file plus every resized variant to disk, and the
     * CAS-guarded image column ends up holding only the base filename.
     */
    #[Group('fast')]
    public function testUploadWritesVariantsAndUpdatesImageJson(): void
    {
        $filename = $this->uploadOneTestImage();

        $this->assertUploadedFilesExist($filename);
        $this->assertVariantsAreActuallyResized($filename);

        $imageJson = $this->processor->encodeImages([$filename]);
        $this->assertTrue(
            $this->repo->updateImage($this->testCarId, $imageJson, ''),
            'CAS update against the empty-string baseline must affect exactly one row'
        );

        $stored = $this->db->query('SELECT image FROM cars WHERE id = ?', [$this->testCarId]);
        if ($stored->error()) {
            $this->fail("Verification query failed for car {$this->testCarId}: " . $stored->errorString());
        }
        $row = $stored->first();
        $this->assertIsObject($row);
        $this->assertSame($imageJson, $row->image);

        $decoded = json_decode($imageJson, true);
        $this->assertIsArray($decoded);
        $this->assertCount(1, $decoded);
        $this->assertIsString($decoded[0]);
        $this->assertStringNotContainsString(
            '-resized-',
            $decoded[0],
            'Only the base filename is persisted — resized variants are derived, never stored'
        );
    }

    /**
     * decodeAndProcessImages() resolves the stored filename back to the file on
     * disk and reports its real metadata.
     */
    #[Group('fast')]
    public function testDecodeAndProcessImagesRoundTripsWrittenFiles(): void
    {
        $filename = $this->uploadOneTestImage();
        $imageJson = $this->processor->encodeImages([$filename]);

        $imageDirRelative = '/' . $this->testCarId . '/';
        $decoded = $this->processor->decodeAndProcessImages(
            $imageJson,
            $imageDirRelative,
            '',
            rtrim($this->tempRoot, '/')
        );

        $this->assertCount(1, $decoded);
        $this->assertSame($filename, $decoded[0]['basename']);
        $this->assertSame($imageDirRelative . $filename, $decoded[0]['path']);
        $this->assertGreaterThan(0, $decoded[0]['size']);
        $this->assertSame('jpeg', $decoded[0]['type']);
        $this->assertSame('image/jpeg', $decoded[0]['mime']);
    }

    /**
     * Deleting a car removes its database row but leaves its image files behind.
     */
    #[Group('fast')]
    public function testDeleteCarRemovesDbRowButLeavesFilesOnDisk(): void
    {
        $filename = $this->uploadOneTestImage();
        $this->assertUploadedFilesExist($filename);

        $carData = $this->repo->findById($this->testCarId);
        $this->assertIsObject($carData);

        $result = (new CarAdministrationService())->delete(
            $carData,
            'Integration test image lifecycle',
            $this->testUserId,
            $this->repo
        );
        $this->assertTrue($result);

        $remaining = $this->db->query('SELECT id FROM cars WHERE id = ?', [$this->testCarId]);
        if ($remaining->error()) {
            $this->fail("Verification query failed for car {$this->testCarId}: " . $remaining->errorString());
        }
        $this->assertSame(0, $remaining->count(), 'cars row must be gone after delete()');

        // Untracking skips tearDown's cleanup for this car, so the cars_hist row the
        // DELETE trigger just wrote has to be removed here instead. Fails loudly, same
        // as the `cars` verification above — untracking removes the only other
        // safety net for this row, so a failure here can't be allowed to pass silently.
        $histCleanup = $this->db->query('DELETE FROM cars_hist WHERE car_id = ?', [$this->testCarId]);
        if ($histCleanup->error()) {
            $this->fail("Failed to clean up cars_hist for car {$this->testCarId}: " . $histCleanup->errorString());
        }
        $this->untrackCarId($this->testCarId);

        // KNOWN GAP (#1629): CarAdministrationService::delete() does not delete any image
        // files from disk. This assertion documents CURRENT behavior as a regression
        // baseline — when #1629 lands, this assertion must flip to assertFileDoesNotExist().
        $this->assertUploadedFilesExist($filename);
    }

    /**
     * removeImage() on a stale car object must fail loudly rather than clobbering
     * a concurrent writer's image list.
     */
    #[Group('fast')]
    public function testRemoveImageCasConflictThrowsConcurrentModificationException(): void
    {
        $filename = $this->uploadOneTestImage();
        $imageJson = $this->processor->encodeImages([$filename]);
        $this->assertTrue($this->repo->updateImage($this->testCarId, $imageJson, ''));

        $staleCarData = $this->repo->findById($this->testCarId);
        $this->assertIsObject($staleCarData);
        $this->assertSame($imageJson, $staleCarData->image);

        $concurrent = $this->db->query(
            'UPDATE cars SET image = ? WHERE id = ?',
            [json_encode(['img_b_fake.jpg']), $this->testCarId]
        );
        if ($concurrent->error()) {
            $this->fail("Concurrent update failed for car {$this->testCarId}: " . $concurrent->errorString());
        }

        $this->expectException(CarConcurrentModificationException::class);
        $this->processor->removeImage($staleCarData, $filename);
    }

    /**
     * removeImage() against a fresh (non-stale) car object shrinks the stored JSON
     * to empty, and — like CarAdministrationService::delete() (#1629) — leaves the
     * removed image's files behind on disk. Same known gap, different call site.
     */
    #[Group('fast')]
    public function testRemoveImageSucceedsButLeavesFilesOnDisk(): void
    {
        $filename = $this->uploadOneTestImage();
        $imageJson = $this->processor->encodeImages([$filename]);
        $this->assertTrue($this->repo->updateImage($this->testCarId, $imageJson, ''));

        $carData = $this->repo->findById($this->testCarId);
        $this->assertIsObject($carData);

        $this->assertTrue($this->processor->removeImage($carData, $filename));

        $stored = $this->db->query('SELECT image FROM cars WHERE id = ?', [$this->testCarId]);
        if ($stored->error()) {
            $this->fail("Verification query failed for car {$this->testCarId}: " . $stored->errorString());
        }
        $row = $stored->first();
        $this->assertIsObject($row);
        $this->assertSame('', $row->image, 'Removing the only image must leave the empty-list sentinel');

        // KNOWN GAP: CarImageProcessor::removeImage() only updates the DB — it never
        // unlinks the file it just dropped from the JSON list. Same underlying bug as
        // #1629 (car deletion), at a different call site; documents current behavior
        // as a regression baseline rather than asserting the fix that doesn't exist yet.
        $this->assertUploadedFilesExist($filename);
    }

    /**
     * removeImages() (plural) is the compensating-cleanup primitive mvTmpImages()
     * uses when rename() fails to move one or more files out of userimages/temp/
     * (#1452). This test exercises CarImageProcessor::removeImages() directly with
     * a filename list representing "these files did not make it to disk" rather
     * than driving the full addCar() -> mvTmpImages() HTTP flow: mvTmpImages() is
     * a plain function in app/api/cars/save.php that depends on globals
     * ($targetFilePath, $user) and a full users/init.php bootstrap that this
     * integration harness does not load, so reproducing an actual rename()
     * failure through that entry point isn't practical here. What IS verified
     * end-to-end is the exact postcondition mvTmpImages() relies on: after
     * removeImages() runs against the filenames that failed to move, the stored
     * image list in cars.image no longer references them — the same DB-level
     * assertion the full flow would produce.
     */
    #[Group('fast')]
    public function testRemoveImagesStripsUnmovedFilenameAfterSimulatedRenameFailure(): void
    {
        $movedFilename = $this->uploadOneTestImage();
        $unmovedFilename = CarImageProcessor::generateSecureFilename('jpg');

        // Both filenames are recorded in cars.image as if mvTmpImages() had already
        // decoded $cardetails['image'] before attempting to move each file — but only
        // $movedFilename actually exists in $this->imageDir (uploadOneTestImage() wrote
        // it there). $unmovedFilename stands in for a file whose rename() call failed:
        // it is listed in the DB but was never moved into place.
        $imageJson = $this->processor->encodeImages([$movedFilename, $unmovedFilename]);
        $this->assertTrue($this->repo->updateImage($this->testCarId, $imageJson, ''));

        $carData = $this->repo->findById($this->testCarId);
        $this->assertIsObject($carData);

        $result = $this->processor->removeImages($carData, [$unmovedFilename]);
        $this->assertSame(['updated' => true, 'casConflict' => false], $result);

        $stored = $this->db->query('SELECT image FROM cars WHERE id = ?', [$this->testCarId]);
        if ($stored->error()) {
            $this->fail("Verification query failed for car {$this->testCarId}: " . $stored->errorString());
        }
        $row = $stored->first();
        $this->assertIsObject($row);

        $decoded = json_decode($row->image, true);
        $this->assertIsArray($decoded);
        $this->assertSame([$movedFilename], $decoded, 'Only the unmoved filename must be stripped; the moved one must remain');

        // The in-memory car object passed to removeImages() is updated in place too —
        // this is what lets mvTmpImages() read $car->data()->image after the call.
        $this->assertSame($row->image, $carData->image);
    }

    /**
     * mvTmpImages()'s mkdir()-failure branch (app/api/cars/save.php:866-875) treats
     * every filename in the decoded image list as unmoved — none of them could have
     * been moved, since the destination directory itself never got created. This
     * test exercises the resulting removeImages() call with the full filename list,
     * confirming the DB column collapses to the empty-list sentinel ('') rather than
     * retaining any of the never-moved filenames — mirroring removeImage()'s existing
     * empty-list convention (see testRemoveImageSucceedsButLeavesFilesOnDisk above).
     */
    #[Group('fast')]
    public function testRemoveImagesClearsImageColumnWhenAllFilenamesUnmovedAfterSimulatedMkdirFailure(): void
    {
        $unmovedA = CarImageProcessor::generateSecureFilename('jpg');
        $unmovedB = CarImageProcessor::generateSecureFilename('jpg');

        // Neither file was ever written to $this->imageDir — standing in for the
        // mkdir()-failure branch, where $filePath never exists and so no image could
        // possibly have been moved into it.
        $imageJson = $this->processor->encodeImages([$unmovedA, $unmovedB]);
        $this->assertTrue($this->repo->updateImage($this->testCarId, $imageJson, ''));

        $carData = $this->repo->findById($this->testCarId);
        $this->assertIsObject($carData);

        $result = $this->processor->removeImages($carData, [$unmovedA, $unmovedB]);
        $this->assertSame(['updated' => true, 'casConflict' => false], $result);

        $stored = $this->db->query('SELECT image FROM cars WHERE id = ?', [$this->testCarId]);
        if ($stored->error()) {
            $this->fail("Verification query failed for car {$this->testCarId}: " . $stored->errorString());
        }
        $row = $stored->first();
        $this->assertIsObject($row);
        $this->assertSame('', $row->image, 'Clearing every listed filename must leave the empty-list sentinel, not an empty JSON array');
        $this->assertSame('', $carData->image);
    }

    /**
     * removeImages() on a stale car object must not throw (unlike the singular
     * removeImage()) — it is used from mvTmpImages()'s compensating-cleanup path,
     * which must not have the in-flight addCar() response interrupted by an
     * exception. A CAS conflict is instead reported back via the result array so
     * the caller can log it and move on.
     */
    #[Group('fast')]
    public function testRemoveImagesReportsCasConflictWithoutThrowing(): void
    {
        $filename = $this->uploadOneTestImage();
        $imageJson = $this->processor->encodeImages([$filename]);
        $this->assertTrue($this->repo->updateImage($this->testCarId, $imageJson, ''));

        $staleCarData = $this->repo->findById($this->testCarId);
        $this->assertIsObject($staleCarData);
        $this->assertSame($imageJson, $staleCarData->image);

        $concurrent = $this->db->query(
            'UPDATE cars SET image = ? WHERE id = ?',
            [json_encode(['img_b_fake.jpg']), $this->testCarId]
        );
        if ($concurrent->error()) {
            $this->fail("Concurrent update failed for car {$this->testCarId}: " . $concurrent->errorString());
        }

        $result = $this->processor->removeImages($staleCarData, [$filename]);
        $this->assertSame(['updated' => false, 'casConflict' => true], $result);

        // The stale in-memory object must be left untouched — only a successful CAS
        // write is allowed to mutate $carData->image.
        $this->assertSame($imageJson, $staleCarData->image);

        $stored = $this->db->query('SELECT image FROM cars WHERE id = ?', [$this->testCarId]);
        if ($stored->error()) {
            $this->fail("Verification query failed for car {$this->testCarId}: " . $stored->errorString());
        }
        $row = $stored->first();
        $this->assertIsObject($row);
        $this->assertSame(
            json_encode(['img_b_fake.jpg']),
            $row->image,
            'The concurrent writer\'s value must survive — the CAS conflict must not be silently overwritten'
        );
    }

    /**
     * removeImage() with a filename that isn't in the car's image list reports
     * failure by return value rather than by exception, and must not touch the
     * stored list — a miss is a no-op, not a partial write.
     */
    #[Group('fast')]
    public function testRemoveImageReturnsFalseWhenFilenameNotInList(): void
    {
        $filename = $this->uploadOneTestImage();
        $imageJson = $this->processor->encodeImages([$filename]);
        $this->assertTrue($this->repo->updateImage($this->testCarId, $imageJson, ''));

        $carData = $this->repo->findById($this->testCarId);
        $this->assertIsObject($carData);

        $this->assertFalse(
            $this->processor->removeImage($carData, 'this-filename-was-never-uploaded.jpg'),
            'Removing a filename that is not in the list must return false, not throw'
        );

        $stored = $this->db->query('SELECT image FROM cars WHERE id = ?', [$this->testCarId]);
        if ($stored->error()) {
            $this->fail("Verification query failed for car {$this->testCarId}: " . $stored->errorString());
        }
        $row = $stored->first();
        $this->assertIsObject($row);
        $this->assertSame($imageJson, $row->image, 'A no-op removal must leave the stored image list untouched');
        $this->assertSame($imageJson, $carData->image, 'A no-op removal must leave the in-memory car object untouched');
    }

    /**
     * Replicate uploadImages()'s real primitives: secure filename, base file on
     * disk, then one GD resize per configured thumbnail size.
     *
     * @return string The base filename (never a resized variant name)
     */
    private function uploadOneTestImage(): string
    {
        $filename = CarImageProcessor::generateSecureFilename('jpg');
        $sourcePath = $this->imageDir . $filename;
        $this->makeTestJpeg($sourcePath);

        foreach ($this->thumbnailSizes as $size) {
            $resizeObj = new Resize($sourcePath);
            $resizeObj->resizeImage($size, $size, 'auto');
            $resizeObj->saveImage($this->variantPath($filename, $size), 80);
        }

        return $filename;
    }

    /**
     * Write a real, valid JPEG — GD and exif_imagetype() reject dummy content.
     */
    private function makeTestJpeg(string $path, int $width = 40, int $height = 30): void
    {
        $img = imagecreatetruecolor($width, $height);
        if ($img === false) {
            $this->fail('GD could not create a truecolor image canvas');
        }

        $color = imagecolorallocate($img, 200, 30, 30);
        if ($color === false) {
            $this->fail('GD could not allocate a colour for the test image');
        }

        imagefill($img, 0, 0, $color);
        if (!imagejpeg($img, $path, 80)) {
            $this->fail("GD could not write the test JPEG to {$path}");
        }
    }

    /**
     * Absolute path of one resized variant for a base filename and target size.
     */
    private function variantPath(string $filename, int $size): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);

        return $this->imageDir . $baseName . '-resized-' . $size . '.jpg';
    }

    /**
     * Absolute paths of the resized variants generated for a base filename.
     *
     * @return list<string>
     */
    private function variantPaths(string $filename): array
    {
        return array_map(fn (int $size) => $this->variantPath($filename, $size), $this->thumbnailSizes);
    }

    private function assertUploadedFilesExist(string $filename): void
    {
        $this->assertFileExists($this->imageDir . $filename);
        foreach ($this->variantPaths($filename) as $path) {
            $this->assertFileExists($path);
        }
    }

    /**
     * Confirms each variant was actually resized to its target width, not just
     * copied as a same-size file with a "-resized-" name. The source JPEG from
     * makeTestJpeg() is landscape (40x30), so Resize's 'auto' mode holds width to
     * the target size and derives height as round(size * 30 / 40).
     */
    private function assertVariantsAreActuallyResized(string $filename): void
    {
        foreach ($this->thumbnailSizes as $size) {
            $path = $this->variantPath($filename, $size);
            $dimensions = getimagesize($path);
            $this->assertIsArray($dimensions, "Could not read image dimensions for {$path}");
            $this->assertSame($size, $dimensions[0], "Variant {$path} has the wrong width");
            $this->assertSame(
                (int) round($size * 30 / 40),
                $dimensions[1],
                "Variant {$path} has the wrong height"
            );
        }
    }

    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir) || is_link($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_link($path) || !is_dir($path)) {
                if (!unlink($path)) {
                    fwrite(STDERR, "NOTE: tearDown() failed to unlink {$path}\n");
                }
            } else {
                $this->recursiveRemoveDirectory($path);
            }
        }
        if (!rmdir($dir)) {
            fwrite(STDERR, "NOTE: tearDown() failed to remove directory {$dir}\n");
        }
    }
}
