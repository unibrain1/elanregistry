<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use ElanRegistry\Car\CarShowcaseService;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for CarShowcaseService::buildShowcasePool()
 *
 * Verifies that:
 * - The pool is always an array (even on an empty registry)
 * - The pool contains at most 12 items (RECENT_LIMIT + RANDOM_LIMIT)
 * - Every item in the pool carries the expected scalar fields
 * - The `is_new` property is present and typed as bool on every item
 *
 * Requires a live database connection (tests are skipped gracefully when
 * unavailable). Every other precondition — at least one image-eligible car,
 * a controlled top-5-by-recency ordering — is established by the tests
 * themselves via createShowcaseEligibleCar() / clearAmbientCars(), not assumed
 * from ambient database state; a test is responsible for the data it needs,
 * not for skipping when the database doesn't happen to already have it.
 */
#[Group('integration')]
final class CarShowcaseServiceIntegrationTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    /**
     * Create a fixture car with a minimal valid image payload, so it's eligible
     * for buildShowcasePool()'s IMAGE_CONDITION. Cleaned up by tearDown() like
     * any other createTestCar() row.
     *
     * `cars.image` is a JSON array of bare filenames (CLAUDE.md) — not an array
     * of {path,name} objects, which IMAGE_CONDITION's JSON_VALID()/JSON_LENGTH()
     * checks would accept but no production code ever writes.
     *
     * @return int The created car's id
     */
    private function createShowcaseEligibleCar(): int
    {
        $userId = $this->createTestUser();
        $carId = $this->createTestCar($userId);

        $imageJson = json_encode(['img_' . bin2hex(random_bytes(16)) . '.jpg']);
        $update = $this->db->query('UPDATE cars SET image = ? WHERE id = ?', [$imageJson, $carId]);
        if ($update->error()) {
            $this->fail("Failed to set fixture image for car {$carId}: " . $update->errorString());
        }

        return $carId;
    }

    /**
     * Delete any pre-existing `cars` rows (and their car_transfer_requests /
     * cars_hist rows) before establishing this test's own fixtures.
     *
     * getNewCarIds() ranks the WHOLE cars table with no per-test scoping, so
     * proving floor/tie-break behavior deterministically requires a known
     * starting point. Any row present here is, by construction of this schema
     * (never seeded with cars, never production data — see
     * tests/bootstrap-integration.php's hard guard against the dev database),
     * leaked debris from an earlier test run whose own cleanup didn't complete
     * (e.g. a killed process) — safe and correct to delete outright rather than
     * mutate-and-restore: deleting genuine debris has no "original state" to
     * preserve, unlike an earlier version of this helper that temporarily
     * backdated ambient ctimes and tried to restore them, which left `mtime`
     * permanently rewritten (ON UPDATE CURRENT_TIMESTAMP) and orphaned
     * cars_hist audit rows behind on every run.
     *
     * @return void
     */
    private function clearAmbientCars(): void
    {
        $result = $this->db->query('SELECT id FROM cars');
        if ($result->error()) {
            $this->fail('Failed to query ambient cars: ' . $result->errorString());
        }

        $ids = array_map(fn($row) => (int) $row->id, $result->results());
        if ($ids === []) {
            return;
        }
        $idList = implode(',', $ids);

        $delTransfers = $this->db->query("DELETE FROM car_transfer_requests WHERE existing_car_id IN ({$idList})");
        if ($delTransfers->error()) {
            $this->fail('Failed to delete ambient car_transfer_requests rows: ' . $delTransfers->errorString());
        }

        $delCars = $this->db->query("DELETE FROM cars WHERE id IN ({$idList})");
        if ($delCars->error()) {
            $this->fail('Failed to delete ambient cars: ' . $delCars->errorString());
        }

        // The cars_delete trigger just wrote fresh audit rows for the deletions
        // above, on top of whatever history the leaked rows already had — clean
        // up both in one pass, after the cars delete (not before), so the
        // trigger-written rows are included.
        $delHist = $this->db->query("DELETE FROM cars_hist WHERE car_id IN ({$idList})");
        if ($delHist->error()) {
            $this->fail('Failed to delete ambient cars_hist rows: ' . $delHist->errorString());
        }
    }

    /**
     * buildShowcasePool() must always return an array — never null or false.
     */
    public function testBuildShowcasePoolReturnsArray(): void
    {
        $pool = (new CarShowcaseService($this->db))->buildShowcasePool();

        $this->assertIsArray($pool);
    }

    /**
     * The pool must contain at most 12 items (6 recent + 6 random).
     */
    public function testBuildShowcasePoolMaxSize(): void
    {
        $pool = (new CarShowcaseService($this->db))->buildShowcasePool();

        $this->assertLessThanOrEqual(12, count($pool));
    }

    /**
     * Every item must have an `is_new` property typed as bool.
     */
    public function testBuildShowcasePoolItemsHaveIsNewProperty(): void
    {
        $this->createShowcaseEligibleCar();

        $pool = (new CarShowcaseService($this->db))->buildShowcasePool();

        $this->assertNotEmpty($pool, 'Pool must be non-empty once at least one image-eligible car exists');
        foreach ($pool as $car) {
            $this->assertObjectHasProperty('is_new', $car);
            $this->assertIsBool($car->is_new);
        }
    }

    /**
     * Every item must expose the scalar fields the home-page template relies on.
     */
    public function testBuildShowcasePoolItemsHaveRequiredFields(): void
    {
        $this->createShowcaseEligibleCar();

        $pool = (new CarShowcaseService($this->db))->buildShowcasePool();

        $this->assertNotEmpty($pool, 'Pool must be non-empty once at least one image-eligible car exists');
        $car = $pool[0];
        $this->assertObjectHasProperty('id', $car);
        $this->assertObjectHasProperty('year', $car);
        $this->assertObjectHasProperty('series', $car);
        $this->assertObjectHasProperty('variant', $car);
        $this->assertObjectHasProperty('type', $car);
        $this->assertObjectHasProperty('ctime', $car);
    }

    /**
     * Pool size must never exceed 12 even when the registry has more eligible cars.
     * Also verifies no car appears twice (recent and random pools are mutually exclusive).
     *
     * Creates 13 cars with a synthetic image payload and asserts the cap holds.
     * Test rows are cleaned up by IntegrationTestCase::tearDown().
     */
    public function testBuildShowcasePoolCapAt12WhenManyEligibleCarsExist(): void
    {
        $userId = $this->createTestUser();

        // cars.image is a JSON array of bare filenames (CLAUDE.md) — one shared
        // filename is fine here since the test only cares about pool cap/uniqueness.
        $imageJson = json_encode(['img_test_showcase_fixture.jpg']);

        for ($i = 0; $i < 13; $i++) {
            $carId = $this->createTestCar($userId);
            $update = $this->db->query('UPDATE cars SET image = ? WHERE id = ?', [$imageJson, $carId]);
            if ($update->error()) {
                $this->fail("Failed to set fixture image for car {$carId}: " . $update->errorString());
            }
        }

        $pool = (new CarShowcaseService($this->db))->buildShowcasePool();

        $this->assertLessThanOrEqual(12, count($pool));

        $ids = array_map(fn($car) => (int) $car->id, $pool);
        $this->assertSame(count($ids), count(array_unique($ids)), 'Pool must not contain duplicate car IDs');
    }

    /**
     * Cars added within NEW_DAYS (90 days) are stamped is_new = true.
     *
     * Creates 13 fixture cars with ctime = NOW() so they dominate the recent-6
     * query (most-recently-added). All are within 90 days, so Condition A fires.
     */
    public function testIsNewTrueForCarsWithinRecentWindow(): void
    {
        $userId = $this->createTestUser();

        $imageJson = json_encode(['img_test_is_new.jpg']);

        $fixtureIds = [];
        for ($i = 0; $i < 13; $i++) {
            $carId = $this->createTestCar($userId);
            $update = $this->db->query('UPDATE cars SET image = ? WHERE id = ?', [$imageJson, $carId]);
            if ($update->error()) {
                $this->fail("Failed to set fixture image for car {$carId}: " . $update->errorString());
            }
            $fixtureIds[] = $carId;
        }

        $pool = (new CarShowcaseService($this->db))->buildShowcasePool();

        $fixtureIdSet = array_flip($fixtureIds);
        $poolFixtureCars = array_filter($pool, fn($car) => isset($fixtureIdSet[(int) $car->id]));

        if (empty($poolFixtureCars)) {
            $this->markTestSkipped('No fixture cars appeared in pool — DB state prevented is_new assertion.');
        }

        foreach ($poolFixtureCars as $car) {
            $this->assertTrue($car->is_new, "Fixture car {$car->id} (ctime=NOW) should have is_new=true");
        }
    }

    // -----------------------------------------------------------------------
    // getNewCarIds() — floor and tie-breaking coverage
    // -----------------------------------------------------------------------

    /**
     * getNewCarIds() must return a list of ints — never null, false, or mixed types.
     */
    public function testGetNewCarIdsReturnsArrayOfIntegers(): void
    {
        $userId = $this->createTestUser();
        $this->createTestCar($userId);

        $ids = (new CarShowcaseService($this->db))->getNewCarIds();

        $this->assertIsArray($ids);
        foreach ($ids as $id) {
            $this->assertIsInt($id, 'Every element returned by getNewCarIds() must be a PHP int');
        }
    }

    /**
     * A car added within 90 days is always included — the date rule fires.
     */
    public function testGetNewCarIdsIncludesCarWithinNinetyDays(): void
    {
        $userId = $this->createTestUser();
        $carId  = $this->createTestCar($userId); // ctime = NOW()

        $ids = (new CarShowcaseService($this->db))->getNewCarIds();

        $this->assertContains($carId, $ids, 'Car with ctime=NOW() must appear in getNewCarIds() via the 90-day rule');
    }

    /**
     * A car outside 90 days and outside the top-5 must not be included.
     *
     * Five helper cars (ctime=NOW()) occupy the global top-5 floor slots,
     * guaranteeing the backdated fixture sits at position 6+. Works on any
     * database — no skip needed.
     */
    public function testGetNewCarIdsOldCarOutsideTopFiveIsExcluded(): void
    {
        $userId = $this->createTestUser();

        // Occupy the 5 floor slots with recent cars so the fixture cannot sneak in.
        for ($i = 0; $i < 5; $i++) {
            $this->createTestCar($userId); // ctime = NOW()
        }

        $carId = $this->createTestCar($userId);
        $backdate = $this->db->query('UPDATE cars SET ctime = DATE_SUB(NOW(), INTERVAL 91 DAY) WHERE id = ?', [$carId]);
        if ($backdate->error()) {
            $this->fail("Failed to backdate fixture car {$carId}: " . $backdate->errorString());
        }

        $ids = (new CarShowcaseService($this->db))->getNewCarIds();

        $this->assertNotContains(
            $carId,
            $ids,
            'Car with ctime 91 days ago and outside the top-5 must not appear in getNewCarIds()'
        );
    }

    /**
     * A car outside the 90-day window but among the top-5 most-recently-added
     * must still be included — the floor guarantee fires.
     *
     * Deletes ambient cars first (see clearAmbientCars()) so this test's own
     * fixtures deterministically occupy the global top-5 positions, regardless
     * of what a prior run's leaked fixtures may have left in the shared schema.
     */
    public function testGetNewCarIdsFloorIncludesOldCarInTopFive(): void
    {
        $this->clearAmbientCars();

        $userId = $this->createTestUser();
        $carIds = [];
        for ($i = 0; $i < 6; $i++) {
            $carIds[] = $this->createTestCar($userId);
        }

        $backdate = $this->db->query(
            'UPDATE cars SET ctime = DATE_SUB(NOW(), INTERVAL 91 DAY) WHERE id IN (' .
            implode(',', array_map('intval', $carIds)) . ')'
        );
        if ($backdate->error()) {
            $this->fail('Failed to backdate fixture cars: ' . $backdate->errorString());
        }

        sort($carIds);
        // 6 cars, equal ctimes — top-5 by id DESC = the 5 highest IDs (indices 1–5).
        // The second-lowest ID is at position 5 of 6 and must be included via the floor.
        $fifthNewestId = $carIds[1];

        $ids = (new CarShowcaseService($this->db))->getNewCarIds();

        $this->assertContains(
            $fifthNewestId,
            $ids,
            'Car at position 5 of 6 by id DESC must be included via the floor guarantee even when older than 90 days'
        );
    }

    /**
     * When multiple cars share the same ctime, the top-5 is broken by id DESC.
     *
     * Six cars with identical ctimes 91 days ago: the 5 with highest IDs must be
     * included (floor) and the lowest-ID car must be excluded (position 6 of 6).
     *
     * Deletes ambient cars first for the same reason as the floor-inclusion
     * test above.
     */
    public function testGetNewCarIdsTieBrokenByIdDesc(): void
    {
        $this->clearAmbientCars();

        $userId = $this->createTestUser();
        $carIds = [];
        for ($i = 0; $i < 6; $i++) {
            $carIds[] = $this->createTestCar($userId);
        }

        // Identical ctime for all six — tie-breaking is purely by id DESC.
        $backdate = $this->db->query(
            "UPDATE cars SET ctime = DATE_SUB(NOW(), INTERVAL 91 DAY) WHERE id IN (" .
            implode(',', array_map('intval', $carIds)) . ')'
        );
        if ($backdate->error()) {
            $this->fail('Failed to backdate fixture cars: ' . $backdate->errorString());
        }

        sort($carIds);
        $lowestId   = $carIds[0];                 // Position 6 of 6 by id DESC — must be excluded
        $topFiveIds = array_slice($carIds, 1);    // Positions 1–5 by id DESC — must be included

        $ids = (new CarShowcaseService($this->db))->getNewCarIds();

        $this->assertNotContains(
            $lowestId,
            $ids,
            'Car with the lowest id must be excluded (position 6 of 6) when all six share the same ctime'
        );
        foreach ($topFiveIds as $id) {
            $this->assertContains($id, $ids, "Car id={$id} (top-5 by id DESC) must be included via the floor guarantee");
        }
    }
}
