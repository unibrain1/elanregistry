<?php

declare(strict_types=1);

use ElanRegistry\Car\CarImageProcessor;
use ElanRegistry\Car\CarRepository;
use ElanRegistry\Exceptions\CarConcurrentModificationException;
use ElanRegistry\Exceptions\ImageProcessingException;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for CarImageProcessor service class
 */
#[Group('fast')]
final class CarImageProcessorTest extends TestCase
{
    private CarImageProcessor $processor;

    protected function setUp(): void
    {
        $this->processor = new CarImageProcessor($this->createStub(CarRepository::class));
    }

    // ============================================================
    // encodeImages tests
    // ============================================================

    public function testEncodeImagesReturnsJsonString(): void
    {
        $images = ['image1.jpg', 'image2.png'];
        $result = $this->processor->encodeImages($images);
        $this->assertJson($result);
        $this->assertEquals('["image1.jpg","image2.png"]', $result);
    }

    public function testEncodeImagesHandlesEmptyArray(): void
    {
        $result = $this->processor->encodeImages([]);
        $this->assertEquals('[]', $result);
    }

    // ============================================================
    // decodeAndProcessImages tests
    // ============================================================

    public function testDecodeAndProcessImagesHandlesNull(): void
    {
        $result = $this->processor->decodeAndProcessImages(null, '/images/1/', '/', '/var/www/');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testDecodeAndProcessImagesHandlesEmptyString(): void
    {
        $result = $this->processor->decodeAndProcessImages('', '/images/1/', '/', '/var/www/');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ============================================================
    // removeImage tests
    // ============================================================

    public function testRemoveImageThrowsOnEmptyFilename(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->never())->method('updateImage');
        $processor = new CarImageProcessor($repo);

        $this->expectException(ImageProcessingException::class);
        $carData = (object) ['id' => 1, 'image' => '["test.jpg"]'];
        $processor->removeImage($carData, '');
    }

    public function testRemoveImageReturnsFalseWhenImageNotFound(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->never())->method('updateImage');
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => '["other.jpg"]'];
        $this->assertFalse($processor->removeImage($carData, 'nonexistent.jpg'));
    }

    public function testRemoveImageReturnsTrueWhenFound(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->once())->method('updateImage')->willReturn(true);
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => '["test.jpg","other.jpg"]'];
        $result = $processor->removeImage($carData, 'test.jpg');
        $this->assertTrue($result);
    }

    public function testRemoveImageUpdatesCarData(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->once())->method('updateImage')->willReturn(true);
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => '["test.jpg","other.jpg"]'];
        $processor->removeImage($carData, 'test.jpg');
        $this->assertEquals('["other.jpg"]', $carData->image);
    }

    public function testRemoveImageHandlesCsvFormat(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->once())->method('updateImage')->willReturn(true);
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => 'test.jpg,other.jpg'];
        $result = $processor->removeImage($carData, 'test.jpg');
        $this->assertTrue($result);
    }

    public function testRemoveLastImageSetsCarDataImageToEmptyString(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->once())->method('updateImage')->willReturn(true);
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => '["test.jpg"]'];
        $processor->removeImage($carData, 'test.jpg');
        $this->assertSame('', $carData->image);
    }

    /**
     * When updateImage() returns false (CAS conflict — another request modified
     * the image column between the read and the write), removeImage() must throw
     * CarConcurrentModificationException so save.php can route it to a retriable
     * response rather than a generic server error.
     */
    public function testRemoveImageThrowsCarConcurrentModificationExceptionOnCasConflict(): void
    {
        // count=0: UPDATE matched 0 rows → CAS guard failed → updateImage() returns false
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->once())->method('updateImage')->willReturn(false);
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => '["test.jpg"]'];
        $this->expectException(CarConcurrentModificationException::class);
        $processor->removeImage($carData, 'test.jpg');
    }

    // ============================================================
    // removeImages (plural) tests
    // ============================================================

    public function testRemoveImagesShortCircuitsOnEmptyFilenamesArray(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->never())->method('updateImage');
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => '["test.jpg","other.jpg"]'];
        $result = $processor->removeImages($carData, []);
        $this->assertSame(['updated' => false, 'casConflict' => false], $result);
        // Untouched — short-circuit must not mutate the passed-in car data.
        $this->assertSame('["test.jpg","other.jpg"]', $carData->image);
    }

    public function testRemoveImagesReturnsNoOpWhenCarDataImageIsEmpty(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->never())->method('updateImage');
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => ''];
        $result = $processor->removeImages($carData, ['test.jpg', 'other.jpg']);
        $this->assertSame(['updated' => false, 'casConflict' => false], $result);
    }

    public function testRemoveImagesHandlesCsvFormat(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->once())->method('updateImage')->willReturn(true);
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => 'test.jpg,other.jpg'];
        $result = $processor->removeImages($carData, ['test.jpg']);
        $this->assertSame(['updated' => true, 'casConflict' => false], $result);
        $this->assertSame('["other.jpg"]', $carData->image);
    }

    public function testRemoveImagesRemovesOnlyFilenamesPresentInMixedList(): void
    {
        $repo = $this->createMock(CarRepository::class);
        $repo->expects($this->once())->method('updateImage')->willReturn(true);
        $processor = new CarImageProcessor($repo);

        $carData = (object) ['id' => 1, 'image' => '["a.jpg","b.jpg","c.jpg"]'];
        // 'x.jpg' and 'y.jpg' are not present in the current image list — only
        // 'a.jpg' and 'c.jpg' should actually be removed.
        $result = $processor->removeImages($carData, ['a.jpg', 'x.jpg', 'c.jpg', 'y.jpg']);
        $this->assertSame(['updated' => true, 'casConflict' => false], $result);
        $this->assertSame('["b.jpg"]', $carData->image);
    }
}
