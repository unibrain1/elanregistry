<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for usersc/account.php's DB-failure handling around
 * `new Owner($ownerId)`.
 *
 * Covers:
 * - usersc/account.php (the try/catch around Owner construction/find())
 *
 * The page cannot be require()'d from PHPUnit for a full behavioral test:
 * it renders a complete HTML page inline (no isolated return path), and
 * Owner::find() only throws OwnerDatabaseException on a genuine \Throwable
 * from the DB layer — there is no input- or environment-driven way to
 * trigger that deterministically over a real request without an actual DB
 * fault. Following VerifyCarWiringTest.php's established precedent (#1505
 * PR A), the catch block is instead asserted against the file's source
 * text.
 *
 * The throw condition itself — a DB/query failure surfacing as
 * OwnerDatabaseException from Owner::find() — is already exercised against
 * a stubbed DatabaseInterface in
 * tests/unit/classes/OwnerProfileTest.php::testFindThrowsOwnerDatabaseExceptionOnDatabaseError();
 * this test covers only account.php's handling of that exception once thrown.
 *
 * @author Elan Registry Development Team
 */
#[Group('fast')]
#[Group('unit')]
#[Group('owner-account')]
final class AccountPageWiringTest extends TestCase
{
    /** Endpoint path, relative to the repository root. */
    private const ACCOUNT_ENDPOINT = 'usersc/account.php';

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
    // account.php — DB-failure handling around new Owner($ownerId)
    // (source inspection)
    // =========================================================================

    /**
     * A DB failure inside Owner::find() (triggered from the constructor)
     * must be caught, logged under LOG_CATEGORY_DATABASE_ERROR, and must
     * leave $owner as a valid object (so getProfileQualityScore() further
     * down the file doesn't fatal on an undefined variable) and $ownerData
     * as null (so the existing `if ($ownerData !== null)` guard degrades
     * the page gracefully instead of propagating the exception).
     *
     * Source inspection: reaching this branch needs a genuine \Throwable
     * from the DB layer, which cannot be produced deterministically from a
     * PHPUnit process or a real HTTP request (see class docblock). The
     * assertions below are ordered and scoped so that moving the catch
     * block, changing the caught type, dropping the log call, or leaving
     * $owner/$ownerData unset in the catch body would each fail a specific
     * assertion rather than passing on a loose substring match.
     */
    public function testOwnerConstructionDbFailureIsCaughtLoggedAndDegradesGracefully(): void
    {
        $content = $this->readEndpointSource(self::ACCOUNT_ENDPOINT);

        $this->assertStringContainsString(
            'try {',
            $content,
            'The Owner construction must be wrapped in a try block'
        );
        $this->assertStringContainsString(
            '$owner     = new Owner($ownerId);',
            $content,
            'The endpoint must construct Owner inside the try block'
        );

        // Isolate the catch block that immediately follows the construction,
        // so assertions below can't accidentally match an unrelated catch
        // block elsewhere in the file (e.g. the CarDatabaseException catch
        // around Car::findByOwner()).
        $tryStart = strpos($content, '$owner     = new Owner($ownerId);');
        $this->assertIsInt($tryStart, 'Could not locate the Owner construction call site');
        $catchBlock = substr($content, $tryStart, 700);

        $this->assertMatchesRegularExpression(
            '/}\s*catch\s*\(\\\\?(ElanRegistry\\\\Exceptions\\\\)?OwnerDatabaseException\s+\$e\)\s*{/',
            $catchBlock,
            'The Owner construction must be caught with OwnerDatabaseException specifically'
        );

        // Isolate just the body of this catch block (up to its own closing
        // brace) so the following assertions can't accidentally match
        // content from a later, unrelated block.
        $catchBodyStart = strpos($catchBlock, 'OwnerDatabaseException $e) {');
        $this->assertIsInt($catchBodyStart, 'Could not locate the OwnerDatabaseException catch body');
        $catchBodyEnd = strpos($catchBlock, "\n}\n", $catchBodyStart);
        $this->assertIsInt($catchBodyEnd, 'Could not locate the end of the OwnerDatabaseException catch body');
        $catchBody = substr($catchBlock, $catchBodyStart, $catchBodyEnd - $catchBodyStart);

        $this->assertStringContainsString(
            'LOG_CATEGORY_DATABASE_ERROR',
            $catchBody,
            'The catch block must log the failure under LOG_CATEGORY_DATABASE_ERROR'
        );
        $this->assertStringContainsString(
            '$owner     = new Owner();',
            $catchBody,
            'The catch block must leave $owner as a valid (unloaded) Owner instance, not undefined'
        );
        $this->assertStringContainsString(
            '$ownerData = null;',
            $catchBody,
            'The catch block must set $ownerData to null so the existing null-guard degrades the page'
        );
    }
}
