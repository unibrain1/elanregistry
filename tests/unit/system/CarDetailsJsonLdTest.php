<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\Group;

/**
 * Schema.org JSON-LD gate for Issue #1371.
 *
 * app/owner/cars/details.php embeds a Schema.org `Car` JSON-LD block to help
 * search engines and AI crawlers understand car detail pages. The block is
 * placed in the page body (NOT `<head>`) — deliberately, right after the
 * `$carData = $car->data();` data-loading block and before
 * `<div class="page-wrapper">` — because the car ID used to build it isn't
 * resolved until the data-loading block runs; placing it any earlier would
 * either 404 on a bad car_id or reference an undefined `$carData`.
 *
 * The actual JSON-LD payload construction (bodyType mapping, the
 * Federal/Race → vehicleConfiguration exclusion, whitespace defense) lives in
 * `ElanRegistry\CarView::buildCarSchema()` and is behaviorally tested in
 * `tests/unit/classes/CarViewTest.php` — this class only guards the wiring:
 * that details.php still calls it, in the right place, and still encodes the
 * result safely.
 *
 * This test is a pure static-text scan: it reads the file's raw source via
 * file_get_contents() and never requires/executes it, so it needs no
 * database and no bootstrapped UserSpice environment.
 */
#[Group('system')]
#[Group('page-metadata')]
class CarDetailsJsonLdTest extends TestCase
{
    private const DETAILS_PAGE = 'app/owner/cars/details.php';

    private string $rootDir = '';
    private string $content = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = dirname(__DIR__, 3);

        $filePath = $this->rootDir . '/' . self::DETAILS_PAGE;
        $this->assertFileExists($filePath, self::DETAILS_PAGE . ' must exist (Issue #1371)');

        $this->content = (string)file_get_contents($filePath);
    }

    public function testContainsJsonLdScriptTag(): void
    {
        $this->assertStringContainsString(
            '<script type="application/ld+json">',
            $this->content,
            self::DETAILS_PAGE . ' must embed a JSON-LD <script> block (Issue #1371)'
        );
    }

    public function testCallsCarViewBuildCarSchema(): void
    {
        $this->assertStringContainsString(
            'CarView::buildCarSchema(',
            $this->content,
            self::DETAILS_PAGE . ' must delegate JSON-LD payload construction to CarView::buildCarSchema() '
            . '(Issue #1371) — the actual field mapping is behaviorally tested in CarViewTest.php'
        );
    }

    public function testJsonEncodesWithHexSafetyFlags(): void
    {
        // JSON_HEX_TAG is the load-bearing flag here — it hex-encodes < and > so a
        // DB-sourced field containing "</script>" can't break out of the script
        // element. Guards against a future edit dropping the flags.
        foreach (['JSON_HEX_TAG', 'JSON_HEX_AMP', 'JSON_HEX_APOS', 'JSON_HEX_QUOT'] as $flag) {
            $this->assertStringContainsString(
                $flag,
                $this->content,
                self::DETAILS_PAGE . " must json_encode() the JSON-LD payload with $flag (Issue #1371)"
            );
        }
    }

    public function testJsonLdBlockFollowsCarDataAssignment(): void
    {
        $carDataMatches = [];
        preg_match(
            '/\$carData\s*=\s*\$car->data\(\);/',
            $this->content,
            $carDataMatches,
            PREG_OFFSET_CAPTURE
        );
        $this->assertNotEmpty(
            $carDataMatches,
            self::DETAILS_PAGE . ' must contain the $carData = $car->data(); assignment to check JSON-LD position (Issue #1371)'
        );

        $scriptTagPosition = strpos($this->content, '<script type="application/ld+json">');
        $this->assertNotFalse(
            $scriptTagPosition,
            self::DETAILS_PAGE . ' must contain the JSON-LD <script> block to check its position (Issue #1371)'
        );

        $this->assertGreaterThan(
            $carDataMatches[0][1],
            $scriptTagPosition,
            self::DETAILS_PAGE . ' JSON-LD block must appear AFTER $carData is loaded — the car ID is not '
            . 'resolved before that point (Issue #1371)'
        );
    }

    public function testHandlesJsonEncodeFailureWithoutEmittingAnEmptyScriptTag(): void
    {
        // json_encode() returns false on malformed UTF-8, which legacy chassis/color/series
        // data (predating the current input-sanitization pipeline — see CarView::buildCarSchema()'s
        // docblock) could in principle contain. Guards against a future edit that drops this
        // check and silently emits an empty, invalid <script type="application/ld+json"></script>.
        $this->assertStringContainsString(
            '$carSchemaJson === false',
            $this->content,
            self::DETAILS_PAGE . ' must check for json_encode() failure before emitting the JSON-LD block (Issue #1371)'
        );
        $this->assertStringContainsString(
            'LogCategories::LOG_CATEGORY_SYSTEM_ERROR',
            $this->content,
            self::DETAILS_PAGE . ' must log a json_encode() failure via LOG_CATEGORY_SYSTEM_ERROR, matching this '
            . 'file\'s existing convention for malformed legacy data (Issue #1371)'
        );
    }
}
