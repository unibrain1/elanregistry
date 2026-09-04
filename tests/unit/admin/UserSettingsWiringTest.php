<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for usersc/user_settings.php's DB-failure handling and
 * missing-profile handling.
 *
 * Covers:
 * - usersc/user_settings.php (the try/catch around Owner::syncLocationToCars()/
 *   getCarsOwned(), and the missing-$profiledetails log+exit branch)
 *
 * The page cannot be require()'d from PHPUnit for a full behavioral test: it
 * renders a complete HTML page inline (no isolated return path), reads
 * superglobals, and both branches under test need conditions that can't be
 * triggered deterministically over a real request (a genuine DB fault, or a
 * profiles row missing for an authenticated user). Following
 * AccountPageWiringTest.php's established precedent (#1505 PR B), both
 * branches are instead asserted against the file's source text.
 *
 * The throw condition itself — a DB/query failure surfacing as
 * OwnerDatabaseException from Owner::getCarsOwned()/syncLocationToCars() — is
 * already exercised against a stubbed DatabaseInterface in
 * tests/integration/OwnerReadMethodsDatabaseFailureTest.php; this test covers
 * only user_settings.php's handling of that exception once thrown.
 *
 * @author Elan Registry Development Team
 */
#[Group('fast')]
#[Group('unit')]
#[Group('owner-account')]
final class UserSettingsWiringTest extends TestCase
{
    /** Endpoint path, relative to the repository root. */
    private const USER_SETTINGS_ENDPOINT = 'usersc/user_settings.php';

    /** Owner class path, relative to the repository root. */
    private const OWNER_CLASS_PATH = 'usersc/classes/Owner.php';

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
    // user_settings.php — car location sync DB-failure handling
    // (source inspection)
    // =========================================================================

    /**
     * A DB failure inside Owner::syncLocationToCars()/getCarsOwned() must be
     * caught, logged under LOG_CATEGORY_DATABASE_ERROR, and must add a
     * friendly message to $errors[] rather than let the exception propagate
     * and crash the page — the profile fields above this block may have
     * already saved successfully.
     *
     * Source inspection: reaching this branch needs a genuine \Throwable
     * from the DB layer, which cannot be produced deterministically from a
     * PHPUnit process or a real HTTP request (see class docblock). The
     * assertions below are ordered and scoped so that moving the catch
     * block, changing the caught type, dropping the log call, or leaving
     * $errors[] unpopulated in the catch body would each fail a specific
     * assertion rather than passing on a loose substring match.
     */
    public function testCarLocationSyncDbFailureIsCaughtLoggedAndDegradesGracefully(): void
    {
        $content = $this->readEndpointSource(self::USER_SETTINGS_ENDPOINT);

        $this->assertStringContainsString(
            'try {',
            $content,
            'The car location sync call must be wrapped in a try block'
        );
        $this->assertStringContainsString(
            '$carsUpdated = $owner->syncLocationToCars();',
            $content,
            'The endpoint must call syncLocationToCars() inside the try block'
        );

        // Isolate the catch block that immediately follows the sync call, so
        // assertions below can't accidentally match an unrelated catch block
        // elsewhere in the file (e.g. a future addition).
        $tryStart = strpos($content, '$carsUpdated = $owner->syncLocationToCars();');
        $this->assertIsInt($tryStart, 'Could not locate the syncLocationToCars() call site');
        $catchBlock = substr($content, $tryStart, 1200);

        $this->assertMatchesRegularExpression(
            '/}\s*catch\s*\(\\\\?(ElanRegistry\\\\Exceptions\\\\)?OwnerDatabaseException\s+\$e\)\s*{/',
            $catchBlock,
            'The car location sync call must be caught with OwnerDatabaseException specifically'
        );

        // Isolate just the body of this catch block (up to its own closing
        // brace) so the following assertions can't accidentally match
        // content from a later, unrelated block.
        $catchBodyStart = strpos($catchBlock, 'OwnerDatabaseException $e) {');
        $this->assertIsInt($catchBodyStart, 'Could not locate the OwnerDatabaseException catch body');
        $catchBodyEnd = strpos($catchBlock, "\n            }", $catchBodyStart);
        $this->assertIsInt($catchBodyEnd, 'Could not locate the end of the OwnerDatabaseException catch body');
        $catchBody = substr($catchBlock, $catchBodyStart, $catchBodyEnd - $catchBodyStart);

        $this->assertStringContainsString(
            'LOG_CATEGORY_DATABASE_ERROR',
            $catchBody,
            'The catch block must log the failure under LOG_CATEGORY_DATABASE_ERROR'
        );
        $this->assertStringContainsString(
            '$errors[] = ',
            $catchBody,
            'The catch block must populate $errors[] with a friendly message rather than let the exception propagate'
        );
    }

    // =========================================================================
    // user_settings.php — missing profile row handling
    // (source inspection)
    // =========================================================================

    /**
     * A missing profiles row for an authenticated user (the else branch of
     * `if ($userQ->count() > 0)`) must be logged under LOG_CATEGORY_USER, and
     * must stop page execution via `exit` rather than fall through into the
     * unconditional use of $profiledetails below (city/state/country/website
     * comparisons at 12+ call sites), which would otherwise fatal on an
     * undefined variable.
     *
     * Source inspection: reaching this branch needs a profiles row to be
     * missing for an authenticated user — data corruption that cannot be
     * produced deterministically from a PHPUnit process or a real HTTP
     * request without directly manipulating the database out from under an
     * active session. The assertions below are ordered and scoped so that
     * removing the log call, or removing/reordering the `exit` relative to
     * the log call, would each fail a specific assertion.
     */
    public function testMissingProfileRowIsLoggedAndStopsExecution(): void
    {
        $content = $this->readEndpointSource(self::USER_SETTINGS_ENDPOINT);

        $this->assertStringContainsString(
            'if ($userQ->count() > 0) {',
            $content,
            'The endpoint must branch on whether the profile query returned a row'
        );

        $elseStart = strpos($content, '} else {');
        $this->assertIsInt($elseStart, 'Could not locate the missing-profile else branch');
        $elseBlock = substr($content, $elseStart, 700);

        $elseBodyEnd = strpos($elseBlock, "\n}\n");
        $this->assertIsInt($elseBodyEnd, 'Could not locate the end of the missing-profile else branch');
        $elseBody = substr($elseBlock, 0, $elseBodyEnd);

        $this->assertStringContainsString(
            'LOG_CATEGORY_USER',
            $elseBody,
            'The missing-profile branch must log under LOG_CATEGORY_USER'
        );
        $this->assertStringContainsString(
            'exit;',
            $elseBody,
            'The missing-profile branch must stop execution rather than fall through to unconditional $profiledetails use'
        );

        // The log call must precede exit, not the reverse (a reordered exit
        // would silently drop the audit trail).
        $logPos = strpos($elseBody, 'LOG_CATEGORY_USER');
        $exitPos = strpos($elseBody, 'exit;');
        $this->assertLessThan(
            $exitPos,
            $logPos,
            'The log call must occur before exit, so the failure is recorded before execution stops'
        );
    }

    /**
     * Regression guard for #1879: every project-owned vericode write must go
     * through hashVericode() rather than storing a plaintext value that
     * users/verify.php's hash-only comparison can never match.
     *
     * A round-trip integration test (tests/integration/UserSettingsVericodeTest.php)
     * pins the hashVericode()/hash_equals() contract itself, but does not
     * execute these source files, so it cannot fail if one of these lines is
     * ever reverted back to plaintext. This source-inspection test is what
     * actually goes red on a regression, following the same pattern as this
     * file's other tests.
     */
    public function testAllProjectOwnedVericodeWritesAreHashed(): void
    {
        $userSettings = $this->readEndpointSource(self::USER_SETTINGS_ENDPOINT);

        // Scope to each DB-write call individually ($db->update(...) /
        // ->update(...)), not the whole file — the email-options array at
        // line ~371 legitimately passes the raw plaintext $vericode so the
        // recipient can use it, and must NOT be flagged as a violation.
        // Checking each call's own body independently (rather than a single
        // file-wide regex) is what makes this discriminate correctly: a
        // regression in either call is caught on its own, not masked by the
        // other call still being correct.
        preg_match_all('/->update\(([^;]*)\);/s', $userSettings, $updateCalls);
        $this->assertNotEmpty($updateCalls[1], 'Could not locate any ->update() DB write calls to inspect');
        $vericodeUpdateCallsChecked = 0;
        foreach ($updateCalls[1] as $updateCallBody) {
            if (!str_contains($updateCallBody, 'vericode')) {
                continue;
            }
            $vericodeUpdateCallsChecked++;
            $this->assertMatchesRegularExpression(
                '/[\'"]vericode[\'"]\s*=>\s*hashVericode\(/',
                $updateCallBody,
                self::USER_SETTINGS_ENDPOINT . ' must hash every vericode written via ->update() (#1879): ' . $updateCallBody
            );
        }
        $this->assertSame(
            2,
            $vericodeUpdateCallsChecked,
            'Expected exactly 2 ->update() calls touching vericode (email-change + password-reset); '
            . 'a different count means this test is no longer checking what it claims to'
        );

        $ownerClass = $this->readEndpointSource(self::OWNER_CLASS_PATH);

        $this->assertMatchesRegularExpression(
            '/[\'"]vericode[\'"]\]\s*=\s*hashVericode\(/',
            $ownerClass,
            self::OWNER_CLASS_PATH . ' must hash the vericode it generates on owner creation (#1879)'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/[\'"]vericode[\'"]\]\s*=\s*randomString\(\d+\)(?!\s*\))/i',
            $ownerClass,
            self::OWNER_CLASS_PATH . ' must not store a bare randomString() result unhashed (#1879)'
        );
    }
}
