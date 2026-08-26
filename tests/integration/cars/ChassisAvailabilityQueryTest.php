<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration coverage for the chassis-uniqueness query in
 * app/api/cars/chassis-availability.php.
 *
 * That endpoint's uniqueness check delegates to
 * CarRepository::findByChassisKey(), so this test runs the exact same query
 * against real fixture rows as a characterization test of its composite-key
 * (year + type + chassis) advisory uniqueness check — the schema has no
 * UNIQUE constraint on these columns (only a non-unique idx_cars_chassis
 * index), so "taken" is purely application-level. The query text itself is
 * pinned to production by the unit-tier
 * tests/unit/cars/services/CarRepositoryTest.php's
 * testFindByChassisKey* tests (this class needs a real DB and the
 * integration tier isn't part of CI, so the drift guard lives where CI
 * actually runs it). Existing Playwright coverage
 * (tests/playwright/chassis-availability-error.spec.js,
 * tests/playwright/ajax-endpoints.spec.js) only exercises the endpoint's
 * error paths — this fills the happy-path gap found during #1604.
 *
 * @see app/api/cars/chassis-availability.php
 * @see tests/unit/cars/services/CarRepositoryTest.php
 */
#[Group('integration')]
#[Group('chassis')]
final class ChassisAvailabilityQueryTest extends IntegrationTestCase
{
    private const QUERY_SQL = 'SELECT id FROM cars WHERE year = ? AND type = ? AND chassis = ?';

    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->testUserId = $this->createTestUser();
    }

    /**
     * Mirrors the query in app/api/cars/chassis-availability.php's chassis_check handler.
     *
     * DB::query() never throws on an execute-time failure — it just returns
     * zero rows — so a broken query would otherwise make "available" and
     * "query failed" indistinguishable. See IntegrationTestCase's own
     * countMatchingLogs()/deleteCarWithHistory() for the same convention.
     */
    private function isChassisTaken(string $year, string $type, string $chassis): bool
    {
        $carQ = $this->db->query(self::QUERY_SQL, [$year, $type, $chassis]);

        if ($carQ->error()) {
            throw new RuntimeException("isChassisTaken() query failed: {$carQ->errorString()}");
        }

        return $carQ->count() > 0;
    }

    /**
     * Unique chassis string within cars.chassis's varchar(15) limit.
     * $suffix (e.g. a case-sensitivity marker) is appended after trimming the
     * random portion, so the total length stays constant.
     */
    private function randomChassis(string $suffix = ''): string
    {
        return 'T' . substr(uniqid(), -(10 - strlen($suffix))) . $suffix;
    }

    /**
     * Creates a fixture car under the standard Elan S4 FHC year/type (1973,
     * type code 36 — see CarModelTest.php) used by every test in this suite.
     */
    private function createFixtureCar(string $chassis): int
    {
        return $this->createTestCar($this->testUserId, [
            'year'    => 1973,
            'type'    => '36',
            'chassis' => $chassis,
        ]);
    }

    #[Group('fast')]
    public function testChassisTakenWhenDuplicateExists(): void
    {
        $chassis = $this->randomChassis();
        $this->createFixtureCar($chassis);

        $this->assertTrue($this->isChassisTaken('1973', '36', $chassis));
    }

    #[Group('fast')]
    public function testChassisAvailableWhenNoMatch(): void
    {
        $chassis = $this->randomChassis();

        $this->assertFalse($this->isChassisTaken('1973', '36', $chassis));
    }

    #[Group('fast')]
    public function testChassisAvailableWhenYearOrTypeDiffers(): void
    {
        $chassis = $this->randomChassis();
        $this->createFixtureCar($chassis);

        $this->assertFalse(
            $this->isChassisTaken('1974', '36', $chassis),
            'Same chassis under a different year must not count as taken'
        );
        $this->assertFalse(
            $this->isChassisTaken('1973', '45', $chassis), // Elan S4 DHC
            'Same chassis under a different type must not count as taken'
        );
    }

    /**
     * chassis is varchar(15) utf8mb4_unicode_ci — case-insensitive collation —
     * so a chassis ending in a lowercase suffix letter still matches an
     * existing row with the uppercase suffix. Owners type the suffix letter
     * in either case; this is real, user-visible matching behavior.
     */
    #[Group('fast')]
    public function testChassisMatchIsCaseInsensitive(): void
    {
        $chassis = $this->randomChassis('B');
        $this->createFixtureCar($chassis);

        $this->assertTrue($this->isChassisTaken('1973', '36', strtolower($chassis)));
    }

    /**
     * The schema has no UNIQUE constraint on (year, type, chassis) — only a
     * non-unique index — so more than one row can already share a key. The
     * production query's "taken" check is count() > 0, not count() === 1;
     * this asserts that holds even with duplicates already present.
     */
    #[Group('fast')]
    public function testChassisStillTakenWithDuplicateRowsPresent(): void
    {
        $chassis = $this->randomChassis();
        $this->createFixtureCar($chassis);
        $this->createFixtureCar($chassis);

        $this->assertTrue($this->isChassisTaken('1973', '36', $chassis));
    }

    /**
     * cars.chassis is varchar(15). chassis-availability.php rejects anything
     * longer before the query runs (strlen($chassis) > 15), but a chassis at
     * exactly that limit does reach it — asserts the match still holds there.
     */
    #[Group('fast')]
    public function testChassisTakenAtMaxLength(): void
    {
        $chassis = 'TB' . uniqid(); // 'TB' (2) + uniqid() (13) = 15 chars exactly
        $this->assertSame(15, strlen($chassis), 'uniqid() length assumption broke — chassis is no longer at the varchar(15) boundary');
        $this->createFixtureCar($chassis);

        $this->assertTrue($this->isChassisTaken('1973', '36', $chassis));
    }
}
