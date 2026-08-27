<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/IntegrationTestCase.php';

use ElanRegistry\Car\CarRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration coverage for the chassis-uniqueness query in
 * app/api/cars/chassis-availability.php.
 *
 * That endpoint's uniqueness check delegates to
 * CarRepository::findByChassisKey(), and this test calls that same method
 * directly against real fixture rows as a characterization test of its
 * composite-key (year + type + chassis) advisory uniqueness check — the
 * schema has no UNIQUE constraint on these columns (only a non-unique
 * idx_cars_chassis index), so "taken" is purely application-level. Because
 * this test calls the real production method rather than re-embedding a copy
 * of its SQL, it cannot drift out of sync with CarRepository the way a
 * separately pinned query string could — any change to findByChassisKey()'s
 * SELECT list, WHERE clause, or bind order is exercised here automatically.
 * Existing Playwright coverage (tests/playwright/chassis-availability-error.spec.js,
 * tests/playwright/ajax-endpoints.spec.js) only exercises the endpoint's
 * error paths — this fills the happy-path gap found during #1604.
 *
 * @see app/api/cars/chassis-availability.php
 * @see usersc/classes/Car/CarRepository.php
 */
#[Group('integration')]
#[Group('chassis')]
final class ChassisAvailabilityQueryTest extends IntegrationTestCase
{
    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->testUserId = $this->createTestUser();
    }

    /**
     * Mirrors the chassis_check handler in app/api/cars/chassis-availability.php,
     * which treats a match from CarRepository::findByChassisKey() as "taken".
     */
    private function isChassisTaken(string $year, string $type, string $chassis): bool
    {
        return (new CarRepository($this->db))->findByChassisKey($year, $type, $chassis) !== null;
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
