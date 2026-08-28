<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for verify_car.php's DB-failure handling around
 * Car::findByVerificationCode().
 *
 * Covers:
 * - app/admin/verify/verify_car.php (the try/catch around
 *   Car::findByVerificationCode())
 *
 * The endpoint file cannot be require()'d from PHPUnit: like
 * app/api/cars/save.php (see CarActionsSaveWiringTest.php), every code path
 * in this file ends in exit/header(), which would terminate the PHPUnit
 * process. The catch branch under test additionally requires
 * Car::findByVerificationCode() to throw a genuine CarDatabaseException,
 * which only happens on a real underlying \Throwable from CarRepository /
 * the DB layer (see Car::findByVerificationCode()'s catch (\Throwable $e)
 * block, usersc/classes/Car/Car.php) — there is no input- or
 * environment-driven way to trigger that deterministically over a real HTTP
 * request without an actual DB fault (investigated and ruled out: the
 * verification-code allowlist regex `^[0-9a-f]{32}$/i` rejects anything
 * that could carry a query-breaking payload, and the codebase has no
 * fault-injection hook for simulating a DB failure in a test environment).
 * Following CarActionsSaveWiringTest.php's established precedent, the catch
 * block is instead asserted against the endpoint file's source text.
 *
 * Deliberately narrow: this does not cover the "not found" branch below the
 * catch (untouched by this change), nor the $applyCarStateChange closure's
 * own try/catch further down the file (a separate, pre-existing error path).
 * The throw condition itself — a DB/query failure surfacing as
 * CarDatabaseException — is already exercised against a stubbed
 * DatabaseInterface in
 * tests/integration/cars/services/CarRepositoryFindByVerificationCodeFailureTest.php;
 * this test covers only verify_car.php's handling of that exception once thrown.
 *
 * @author Elan Registry Development Team
 */
#[Group('fast')]
#[Group('unit')]
#[Group('car-verification')]
final class VerifyCarWiringTest extends TestCase
{
    /** Endpoint path, relative to the repository root. */
    private const VERIFY_ENDPOINT = 'app/admin/verify/verify_car.php';

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Read an endpoint's source text for wiring assertions.
     *
     * @param string $relativePath Path relative to the repository root
     * @return string The endpoint file contents
     */
    private function readEndpointSource(string $relativePath): string
    {
        $filePath = __DIR__ . '/../../../' . $relativePath;
        $this->assertFileExists($filePath, "Endpoint file must exist: {$relativePath}");

        $content = file_get_contents($filePath);
        $this->assertIsString($content, "Endpoint file must be readable: {$relativePath}");

        return $content;
    }

    // =========================================================================
    // verify_car.php — DB-failure handling around findByVerificationCode()
    // (source inspection)
    // =========================================================================

    /**
     * A DB failure inside Car::findByVerificationCode() must be caught,
     * logged under the car-verification category, shown as a friendly error,
     * and must exit — not fall through to the "not found" branch below it.
     *
     * Source inspection: reaching this branch needs a genuine \Throwable
     * from the DB layer, which cannot be produced deterministically from a
     * PHPUnit process or a real HTTP request (see class docblock). The
     * assertions below are ordered and scoped so that moving the catch
     * block, changing the caught type, dropping the log call, or letting
     * execution continue past it would each fail a specific assertion
     * rather than passing on a loose substring match.
     */
    public function testFindByVerificationCodeDbFailureIsCaughtLoggedAndExits(): void
    {
        $content = $this->readEndpointSource(self::VERIFY_ENDPOINT);

        $this->assertStringContainsString(
            'try {',
            $content,
            'The call to findByVerificationCode() must be wrapped in a try block'
        );
        $this->assertStringContainsString(
            '$carObj = Car::findByVerificationCode($code);',
            $content,
            'The endpoint must look up the car by verification code inside the try block'
        );

        // Isolate the catch block that immediately follows the lookup, so
        // assertions below can't accidentally match the unrelated catch
        // blocks later in the file (e.g. inside $applyCarStateChange).
        $catchStart = strpos($content, '$carObj = Car::findByVerificationCode($code);');
        $this->assertIsInt($catchStart, 'Could not locate the findByVerificationCode() call site');
        $catchBlock = substr($content, $catchStart, 600);

        $this->assertMatchesRegularExpression(
            '/}\s*catch\s*\(\\\\?ElanRegistry\\\\Exceptions\\\\CarDatabaseException\s+\$e\)\s*{/',
            $catchBlock,
            'The findByVerificationCode() call must be caught with CarDatabaseException specifically'
        );

        // Isolate just the body of this catch block (up to its own closing
        // brace) so the following assertions can't accidentally match
        // content from a later, unrelated block.
        $catchBodyStart = strpos($catchBlock, 'CarDatabaseException $e) {');
        $this->assertIsInt($catchBodyStart, 'Could not locate the CarDatabaseException catch body');
        $catchBodyEnd = strpos($catchBlock, "\n    }\n", $catchBodyStart);
        $this->assertIsInt($catchBodyEnd, 'Could not locate the end of the CarDatabaseException catch body');
        $catchBody = substr($catchBlock, $catchBodyStart, $catchBodyEnd - $catchBodyStart);

        $this->assertStringContainsString(
            'LOG_CATEGORY_CAR_VERIFICATION',
            $catchBody,
            'The catch block must log the failure under LOG_CATEGORY_CAR_VERIFICATION'
        );
        $this->assertStringContainsString(
            'exit;',
            $catchBody,
            'The catch block must call exit so execution cannot fall through to the "not found" branch'
        );

        // Confirm the catch block appears strictly before the "not found"
        // branch, i.e. it guards that branch rather than following it.
        $notFoundPos = strpos($content, 'Verification code not found or invalid');
        $catchPos = strpos($content, 'CarDatabaseException $e');
        $this->assertIsInt($notFoundPos, 'Could not locate the "not found" branch');
        $this->assertIsInt($catchPos, 'Could not locate the CarDatabaseException catch');
        $this->assertLessThan(
            $notFoundPos,
            $catchPos,
            'The CarDatabaseException catch must appear before the "not found" branch it guards'
        );
    }
}
