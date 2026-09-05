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
 * - usersc/user_settings.php (the try/catch around Owner::syncOwnerFieldsToCars()/
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
 * OwnerDatabaseException from Owner::getCarsOwned()/syncOwnerFieldsToCars() — is
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
     * A DB failure inside Owner::syncOwnerFieldsToCars()/getCarsOwned() must be
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
            'The owner-field sync call must be wrapped in a try block'
        );
        $this->assertStringContainsString(
            '$syncResult = $owner->syncOwnerFieldsToCars();',
            $content,
            'The endpoint must call syncOwnerFieldsToCars() inside the try block'
        );

        // Isolate the catch block that immediately follows the sync call, so
        // assertions below can't accidentally match an unrelated catch block
        // elsewhere in the file (e.g. a future addition).
        $tryStart = strpos($content, '$syncResult = $owner->syncOwnerFieldsToCars();');
        $this->assertIsInt($tryStart, 'Could not locate the syncOwnerFieldsToCars() call site');
        $catchBlock = substr($content, $tryStart, 1800);

        $this->assertMatchesRegularExpression(
            '/}\s*catch\s*\(\\\\?(ElanRegistry\\\\Exceptions\\\\)?OwnerDatabaseException\s*\|\s*\\\\?(ElanRegistry\\\\Exceptions\\\\)?CarDatabaseException\s+\$e\)\s*{/',
            $catchBlock,
            'The owner-field sync call must be caught with both OwnerDatabaseException AND '
            . 'CarDatabaseException — they are siblings under ElanRegistryException, not '
            . 'parent/child, so catching only one lets the other escape as an unlogged fatal '
            . '(regression guard: this catch was OwnerDatabaseException-only until #1873 round '
            . 'two, since syncOwnerFieldsToCars() can also propagate CarDatabaseException from '
            . "CarRepository's updateCarForOwner())"
        );

        // Isolate just the body of this catch block (up to its own closing
        // brace) so the following assertions can't accidentally match
        // content from a later, unrelated block.
        $catchBodyStart = strpos($catchBlock, 'OwnerDatabaseException | CarDatabaseException $e) {');
        $this->assertIsInt($catchBodyStart, 'Could not locate the combined-catch body');
        $catchBodyEnd = strpos($catchBlock, "\n            }", $catchBodyStart);
        $this->assertIsInt($catchBodyEnd, 'Could not locate the end of the combined-catch body');
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

    // =========================================================================
    // user_settings.php — $profileFieldsChanged sync gating (source inspection)
    // =========================================================================

    /**
     * The unconfirmed-email guard (#1873).
     *
     * `cars.email` is the address verification batches send to. When
     * `email_act == 1`, user_settings.php writes only `email_new` — `users.email`
     * is unchanged until the owner confirms via users/verify.php. Setting
     * $profileFieldsChanged in that branch would push an UNCONFIRMED address onto
     * every car the owner has.
     *
     * Only a comment enforces this today, and a mutation adding the flag to that
     * branch survives the whole suite. This pins it by source inspection: the
     * page cannot be included in a unit test (it executes on load and depends on
     * UserSpice globals), which is why this file already inspects source text
     * elsewhere.
     */
    public function testUnconfirmedEmailBranchDoesNotTriggerCarSync(): void
    {
        $content = $this->readEndpointSource('usersc/user_settings.php');

        // Anchor on the `if` statement itself, not a bare 'email_act == 1'
        // substring — the comment above the email_act == 0 branch mentions
        // 'email_act == 1' by name, and matching that would extract the wrong
        // branch entirely (and pass for the wrong reason).
        $marker = 'if ($emailR->email_act == 1) {';
        $branchStart = strpos($content, $marker);
        $this->assertNotFalse($branchStart, 'user_settings.php must still branch on email_act == 1');

        // Bound the window at the branch's own closing brace rather than a fixed
        // character count: a fixed window either truncates the branch (making the
        // assertion vacuous) or overruns into later code (producing a false hit).
        // The email_act == 1 block is the last `if` inside the confirmed-email
        // block, so its body ends at the next line that dedents to its own brace.
        $branchEnd = strpos($content, "\n                            }", $branchStart);
        $this->assertNotFalse($branchEnd, 'Could not locate the end of the email_act == 1 branch');
        $branchBody = substr($content, $branchStart, $branchEnd - $branchStart);
        $this->assertGreaterThan(
            200,
            strlen($branchBody),
            'The extracted email_act == 1 branch body is implausibly short — the '
            . 'delimiter above probably no longer matches, which would make the '
            . 'assertions below vacuous'
        );

        $this->assertStringContainsString(
            'email_new',
            $branchBody,
            'The email_act == 1 branch must still be the one that stages email_new'
        );
        $this->assertStringNotContainsString(
            '$profileFieldsChanged = true;',
            $branchBody,
            'The email_act == 1 branch stages email_new only — users.email is unchanged '
            . 'until the owner confirms, so it must NOT set $profileFieldsChanged or an '
            . 'unconfirmed address would be synced onto every car (#1873)'
        );
    }

    /**
     * The sync must remain gated on $profileFieldsChanged, and the flag must be
     * set by each branch that actually persists a synced owner-contact field.
     *
     * Mutations that drop a `$profileFieldsChanged = true;` (e.g. from the
     * location block) silently stop syncing that field class — the exact defect
     * #1873 was filed to fix — and no behavioral test catches it.
     */
    public function testSyncIsGatedOnProfileFieldsChangedFlag(): void
    {
        $content = $this->readEndpointSource('usersc/user_settings.php');

        $this->assertMatchesRegularExpression(
            '/if\s*\(\s*\$profileFieldsChanged\s*\)/',
            $content,
            'The car sync must stay gated on $profileFieldsChanged so a save that '
            . 'persisted nothing does not push stale values onto the cars'
        );

        $this->assertStringContainsString(
            'syncOwnerFieldsToCars()',
            $content,
            'user_settings.php must call syncOwnerFieldsToCars() (#1873)'
        );

        // fname, lname, location, website, and the confirmed-email branch each
        // persist a synced column, so each must set the flag. The unconfirmed
        // email branch must not — asserted separately above.
        $this->assertSame(
            5,
            substr_count($content, '$profileFieldsChanged = true;'),
            'Exactly five branches persist a synced owner-contact field (fname, lname, '
            . 'location, website, confirmed email) and each must set $profileFieldsChanged. '
            . 'A change here means either a sync trigger was dropped or the unconfirmed-email '
            . 'branch gained one (#1873)'
        );
    }
}
