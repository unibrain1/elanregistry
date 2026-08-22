<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for Issue #1624 (AC #11): dead input guard in
 * app/api/shared/location-reverse.php.
 *
 * The endpoint previously guarded its lat/lon inputs with
 * `$lat === null || $lon === null`, but Input::get() never returns null for
 * an absent parameter — it returns '' (empty string) — so the guard could
 * never actually reject a missing/malformed request; malformed input fell
 * through to `(float)$lat`/`(float)$lon`, silently coercing garbage to 0.0
 * instead of failing with a clear error. The fix replaces the guard with
 * `!is_numeric($lat) || !is_numeric($lon)`, which correctly rejects '',
 * non-numeric strings, and any other malformed input while accepting valid
 * numeric strings (including strings, since Input::get() always returns a
 * string or array, never a native float).
 *
 * location-reverse.php is a procedural AJAX endpoint script (calls
 * users/init.php, reads $method/$user, sends an ApiResponse) with no
 * independently-callable validation logic, so — per the issue's own
 * guidance to prefer a unit-tier test — this suite covers the fix two ways:
 *
 *   1. Proves the specific replacement condition
 *      `!is_numeric($lat) || !is_numeric($lon)` behaves correctly in
 *      isolation for the exact input shapes Input::get() actually produces
 *      (confirmed elsewhere: '' for an absent field, not null).
 *   2. Asserts the live source of location-reverse.php contains the fixed
 *      condition and no longer contains the dead `=== null` guard — a
 *      structural regression guard against the fix being reverted.
 *
 * @issue 1624
 * @link https://github.com/elan-registry/registry/issues/1624
 * @category regression
 */
#[Group('regression')]
#[Group('fast')]
final class Issue1624LocationReverseGuardRegressionTest extends TestCase
{
    private const TARGET_FILE = __DIR__ . '/../../../app/api/shared/location-reverse.php';

    /**
     * Mirrors the fixed guard condition exactly:
     * !is_numeric($lat) || !is_numeric($lon) — true means "reject".
     */
    private function guardRejects(mixed $lat, mixed $lon): bool
    {
        return !is_numeric($lat) || !is_numeric($lon);
    }

    /**
     * Input::get() returns '' (empty string) for an absent POST field, never
     * null — confirmed by Input's contract elsewhere in this codebase. The
     * pre-fix `$lat === null` guard could therefore never fire for a
     * genuinely missing field; is_numeric('') is false, so the fixed guard
     * correctly rejects it.
     */
    public function testGuardRejectsEmptyStringInputForAbsentField(): void
    {
        $this->assertTrue(
            $this->guardRejects('', '45.5'),
            'The guard must reject an empty-string latitude (what Input::get() returns for an absent field).'
        );
        $this->assertTrue(
            $this->guardRejects('45.5', ''),
            'The guard must reject an empty-string longitude.'
        );
        $this->assertTrue(
            $this->guardRejects('', ''),
            'The guard must reject both coordinates being empty-string.'
        );
    }

    public function testGuardRejectsNonNumericStrings(): void
    {
        $this->assertTrue(
            $this->guardRejects('abc', '45.5'),
            'The guard must reject a non-numeric latitude string.'
        );
        $this->assertTrue(
            $this->guardRejects('45.5', 'xyz'),
            'The guard must reject a non-numeric longitude string.'
        );
        $this->assertTrue(
            $this->guardRejects('not-a-number', 'also-not-a-number'),
            'The guard must reject two non-numeric coordinate strings.'
        );
    }

    /**
     * Input::sanitize() can return an array for array-shaped POST data (e.g.
     * lat[]=1&lat[]=2), not just a string — Input::get() returns that array
     * unmodified. is_numeric() on an array is false, so the guard must reject
     * it the same way it rejects any other non-numeric shape.
     */
    public function testGuardRejectsArrayInput(): void
    {
        $this->assertTrue(
            $this->guardRejects(['1', '2'], '45.5'),
            'The guard must reject an array-shaped latitude (possible via lat[]=1&lat[]=2).'
        );
        $this->assertTrue(
            $this->guardRejects('45.5', ['1', '2']),
            'The guard must reject an array-shaped longitude.'
        );
    }

    public function testGuardAcceptsValidNumericStrings(): void
    {
        $this->assertFalse(
            $this->guardRejects('45.5', '-122.6765'),
            'The guard must accept valid numeric latitude/longitude strings.'
        );
        $this->assertFalse(
            $this->guardRejects('0', '0'),
            'The guard must accept the coordinate origin (0, 0) — a legitimate, valid coordinate pair.'
        );
        $this->assertFalse(
            $this->guardRejects('-90', '180'),
            'The guard must accept boundary-valid numeric strings (range validity is checked separately by validateCoordinates()).'
        );
    }

    /**
     * Structural guard: the live source must contain the fixed condition and
     * must no longer contain the dead null-comparison guard it replaced.
     */
    public function testLiveSourceUsesFixedGuardAndNotTheDeadGuard(): void
    {
        $source = file_get_contents(self::TARGET_FILE);
        $this->assertIsString($source, 'app/api/shared/location-reverse.php must be readable');

        $this->assertStringContainsString(
            '!is_numeric($lat) || !is_numeric($lon)',
            $source,
            'location-reverse.php must use the fixed is_numeric() guard — regression guard for #1624.'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/\$lat\s*===\s*null/',
            $source,
            'The dead `$lat === null` guard must not reappear — Input::get() never returns null for an '
                . 'absent field, so this comparison can never fire and would silently let malformed input '
                . 'through to (float) coercion.'
        );
    }
}
