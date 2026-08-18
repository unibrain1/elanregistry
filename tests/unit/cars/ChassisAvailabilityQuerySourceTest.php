<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Pins app/api/cars/chassis-availability.php's chassis-uniqueness SQL query
 * text and bind order, so a drift in either fails this suite immediately
 * instead of leaving tests/integration/cars/ChassisAvailabilityQueryTest.php
 * silently characterizing a stale copy of the query.
 *
 * Runs in the unit tier — no DB needed, and this is the tier CI actually
 * executes on every PR (the integration tier isn't part of CI). See
 * LogCategoriesUsageTest.php for the same "scan production source" pattern.
 *
 * The SQL text is quoted (`'...'`) rather than matched bare, so an appended
 * WHERE condition (e.g. a future chassis_override exclusion) breaks the
 * match instead of still containing it as a substring.
 *
 * @see app/api/cars/chassis-availability.php
 * @see tests/integration/cars/ChassisAvailabilityQueryTest.php
 */
final class ChassisAvailabilityQuerySourceTest extends TestCase
{
    private const ENDPOINT_FILE = 'app/api/cars/chassis-availability.php';

    private const CHARACTERIZATION_FILE = 'tests/integration/cars/ChassisAvailabilityQueryTest.php';

    private const QUERY_SQL = "'SELECT id FROM cars WHERE year = ? AND type = ? AND chassis = ?'";

    private const BIND_PARAMS = '[$year, $type, $chassis]';

    private function readSource(string $relativePath): string
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        $this->assertIsString($source, 'Could not read ' . $relativePath);

        return $source;
    }

    /**
     * Asserts the endpoint's SQL query text still matches the characterization test.
     */
    #[Group('fast')]
    public function testQueryTextMatchesCharacterizationTest(): void
    {
        $this->assertStringContainsString(
            self::QUERY_SQL,
            $this->readSource(self::ENDPOINT_FILE),
            self::ENDPOINT_FILE . "'s chassis-uniqueness query text no longer matches "
                . self::CHARACTERIZATION_FILE . "'s characterization — update both together"
        );
    }

    /**
     * Asserts the endpoint's bind parameter order still matches the characterization test.
     */
    #[Group('fast')]
    public function testBindParameterOrderMatchesCharacterizationTest(): void
    {
        $this->assertStringContainsString(
            self::BIND_PARAMS,
            $this->readSource(self::ENDPOINT_FILE),
            self::ENDPOINT_FILE . "'s bind parameter order no longer matches the characterization test's "
                . '(year, type, chassis) assumption — update both together'
        );
    }

    /**
     * The two prior tests only prove production matches this file's own pinned
     * copy of the query. Without this, editing production plus this file's
     * constants but forgetting the integration test's separate copy of the
     * same SQL would leave the whole suite green while
     * tests/integration/cars/ChassisAvailabilityQueryTest.php silently
     * characterizes a stale query — exactly what "update both together" warns
     * against but doesn't otherwise enforce.
     */
    #[Group('fast')]
    public function testCharacterizationTestUsesTheSameQueryText(): void
    {
        $this->assertStringContainsString(
            self::QUERY_SQL,
            $this->readSource(self::CHARACTERIZATION_FILE),
            self::CHARACTERIZATION_FILE . ' no longer runs the same SQL as '
                . self::ENDPOINT_FILE . ' — update both together'
        );
    }
}
