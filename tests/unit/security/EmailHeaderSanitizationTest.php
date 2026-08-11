<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for email header field sanitization — issue #1322 (Bug B).
 *
 * ROOT CAUSE
 * ----------
 * Before the fix, $fromName was built as:
 *   $fromName = $fromData->fname . ' ' . $fromData->lname;
 * This introduced two vulnerabilities:
 *   1. lname was included, leaking the sender's full surname into reply-to headers
 *      and the email template, violating the first-name-only privacy contract.
 *   2. $fromName was not passed through preg_replace('/[\r\n\t]/', '', ...), so a
 *      CR/LF sequence in the database fname or lname value could inject additional
 *      MIME headers via PHPMailer's addReplyTo() call.
 *
 * TESTING GAP
 * -----------
 * No test verified which name fields flow into email headers or the reply_name
 * parameter, nor that those values are stripped of control characters before use.
 * The fix was therefore untested, making regression possible on future refactors.
 *
 * PREVENTION
 * ----------
 * These tests pin the exact string-derivation contract from send-owner-email.php
 * lines 113-116 so that any future change to $toName or $fromName construction
 * is caught immediately without requiring an end-to-end email send.
 *
 * The behavioral tests below validate a mirrored copy of the derivation
 * expressions in isolation, covering input/output edge cases (CR, LF, tabs,
 * clean values, null, lname-exclusion). Those behavioral tests are backed by
 * a source-inspection guard (see the source-inspection guard section below)
 * that reads the real send-owner-email.php source and asserts the exact
 * derivation is unchanged, so a production regression would be caught even
 * though the file itself cannot be executed directly in a unit test (it
 * requires full framework bootstrap).
 */
#[Group('fast')]
final class EmailHeaderSanitizationTest extends TestCase
{
    private const SEND_OWNER_EMAIL_FILE = 'app/api/contact/send-owner-email.php';

    /**
     * Mirrors the pattern in send-owner-email.php — kept as one constant so
     * the mirror in the behavioral tests and the source-inspection guard
     * below can't drift apart.
     */
    private const HEADER_STRIP_PATTERN = '/[\r\n\t]/';

    private string $rootDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = dirname(__DIR__, 3);
    }

    private function readSendOwnerEmailSource(): string
    {
        $filePath = $this->rootDir . '/' . self::SEND_OWNER_EMAIL_FILE;
        $this->assertFileExists($filePath, 'send-owner-email.php must exist (#1600)');

        return (string) file_get_contents($filePath);
    }

    /**
     * Extracts the single line assigning $varName (e.g. 'toEmail') from the
     * send-owner-email.php source, failing the test if it's missing or
     * duplicated. Centralizes the lookup so the four testProduction* field
     * checks below can't drift apart.
     */
    private function extractAssignmentLine(string $content, string $varName): string
    {
        $this->assertSame(
            1,
            preg_match_all('/^\s*\$' . $varName . '\s*=.*?;/m', $content, $matches),
            "send-owner-email.php must contain exactly one \$$varName assignment (#1600)"
        );

        return $matches[0][0];
    }

    // ------------------------------------------------------------------
    // $fromName — derived from $fromData->fname with CR/LF stripping
    // Replicates send-owner-email.php:116
    // ------------------------------------------------------------------

    /**
     * \r\n in fname must be stripped before the value reaches addReplyTo().
     *
     * A malicious or corrupted fname such as "Alice\r\nBcc: attacker@evil.com"
     * would inject an extra MIME header if passed unsanitized. The sanitization
     * removes the line-break characters so the value is collapsed into a single
     * line — the injected text no longer begins on its own line and cannot be
     * interpreted as a separate header by a MIME parser.
     */
    public function testFromName_CrLfInFname_IsStripped(): void
    {
        $fromData = (object)['fname' => "Alice\r\nBcc: attacker@evil.com", 'lname' => 'Smith'];
        $fromName = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));

        // The line-break characters are gone — the value is now a single line
        $this->assertStringNotContainsString("\r", $fromName);
        $this->assertStringNotContainsString("\n", $fromName);
        // The resulting collapsed string cannot be parsed as a separate MIME header
        $this->assertSame('AliceBcc: attacker@evil.com', $fromName);
    }

    /**
     * Bare \r (carriage-return only) is also stripped — covers legacy mailers
     * that treat \r alone as a line terminator.
     */
    public function testFromName_CrAloneInFname_IsStripped(): void
    {
        $fromData = (object)['fname' => "Alice\rSmith", 'lname' => 'Last'];
        $fromName = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));

        $this->assertStringNotContainsString("\r", $fromName);
        $this->assertSame('AliceSmith', $fromName);
    }

    /**
     * \t (tab) in fname is stripped — tabs are also illegal in MIME header values.
     */
    public function testFromName_TabInFname_IsStripped(): void
    {
        $fromData = (object)['fname' => "Alice\tTab", 'lname' => 'Last'];
        $fromName = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));

        $this->assertStringNotContainsString("\t", $fromName);
        $this->assertSame('AliceTab', $fromName);
    }

    /**
     * $fromName must not include lname — only fname flows into reply_name.
     *
     * The pre-fix code appended lname, leaking the sender's surname into
     * PHPMailer's addReplyTo() and the email template 'from' variable.
     */
    public function testFromName_DoesNotContainLname(): void
    {
        $fromData = (object)['fname' => 'Alice', 'lname' => 'Smith'];
        $fromName = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));

        $this->assertSame('Alice', $fromName);
        $this->assertStringNotContainsString('Smith', $fromName);
    }

    /**
     * A clean fname with no control characters is preserved unchanged.
     */
    public function testFromName_CleanFname_IsPreservedUnchanged(): void
    {
        $fromData = (object)['fname' => 'Alice', 'lname' => 'Smith'];
        $fromName = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));

        $this->assertSame('Alice', $fromName);
    }

    /**
     * Empty fname sanitizes to an empty string without error.
     */
    public function testFromName_EmptyFname_YieldsEmptyString(): void
    {
        $fromData = (object)['fname' => '', 'lname' => 'Smith'];
        $fromName = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));

        $this->assertSame('', $fromName);
    }

    /**
     * Null fname (DEFAULT NULL column) sanitizes to an empty string without PHP deprecation.
     *
     * In PHP 8.2+ preg_replace() on a null subject would trigger a deprecation notice.
     * The (string)(...?? '') cast ensures a safe empty-string fallback.
     */
    public function testFromName_NullFname_YieldsEmptyString(): void
    {
        $fromData = (object)['fname' => null, 'lname' => 'Smith'];
        $fromName = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));

        $this->assertSame('', $fromName);
    }

    // ------------------------------------------------------------------
    // $toName — derived from $toData->fname only (no stripping needed for
    // template display, but the contract is first-name-only).
    // Replicates send-owner-email.php:114
    // ------------------------------------------------------------------

    /**
     * $toName must not include lname — the recipient's surname must not appear
     * in the email template greeting, preserving the privacy contract.
     */
    public function testToName_DoesNotContainLname(): void
    {
        $toData = (object)['fname' => 'Bob', 'lname' => 'Jones'];
        $toName = $toData->fname;

        $this->assertSame('Bob', $toName);
        $this->assertStringNotContainsString('Jones', $toName);
    }

    /**
     * $toName is exactly fname with no transformation.
     */
    public function testToName_IsFnameVerbatim(): void
    {
        $toData = (object)['fname' => 'Élodie', 'lname' => 'Dupont'];
        $toName = $toData->fname;

        $this->assertSame('Élodie', $toName);
    }

    /**
     * $toName intentionally does NOT receive preg_replace stripping.
     *
     * Unlike $fromName (which flows into reply_name — a MIME display-name header),
     * $toName is used only in the HTML template greeting where htmlspecialchars()
     * handles XSS. SMTP header injection is not a risk for body-only values, so
     * stripping is unnecessary. The (string)(... ?? '') null guard matches $fromName
     * for defensive consistency, but no preg_replace is applied. This test documents
     * the intentional asymmetry so future refactors don't add preg_replace here.
     */
    public function testToName_IsAssignedDirectly_WithoutPregreplace(): void
    {
        $toData = (object)['fname' => "Bob\r\nInjection", 'lname' => 'Jones'];

        // The contract: (string)($toData->fname ?? '') — null guard only, no stripping
        $toName = (string)($toData->fname ?? '');

        // The value is the raw fname — control chars are present (intentional)
        $this->assertSame("Bob\r\nInjection", $toName);
        // Template safety comes from htmlspecialchars() at render time, not pre-stripping
        $this->assertStringNotContainsString('Jones', $toName);
    }

    /**
     * Null fname (DEFAULT NULL column) produces an empty string without TypeError.
     *
     * Without the (string)(... ?? '') guard, $toName would be null, and
     * htmlspecialchars($to, ENT_QUOTES, 'UTF-8') in the email template would throw
     * a TypeError in PHP 8.1+, causing the ob_start() catch to suppress the error
     * and return a 500 to the sender.
     */
    public function testToName_NullFname_YieldsEmptyString(): void
    {
        $toData = (object)['fname' => null, 'lname' => 'Jones'];
        $toName = (string)($toData->fname ?? '');

        $this->assertSame('', $toName);
    }

    // ------------------------------------------------------------------
    // $fromEmail — preg_replace stripping (regression guard)
    // Replicates send-owner-email.php:115
    // ------------------------------------------------------------------

    /**
     * $fromEmail CR/LF is stripped — added by the fix alongside the $toEmail
     * stripping that already existed. Regression guard: don't drop this call.
     *
     * Stripping the line-break collapses the value to a single line so it
     * cannot be interpreted as a separate MIME header by the mailer.
     */
    public function testFromEmail_CrLfInEmail_IsStripped(): void
    {
        $fromData  = (object)['email' => "alice@example.com\r\nBcc: attacker@evil.com"];
        $fromEmail = preg_replace('/[\r\n\t]/', '', $fromData->email);

        $this->assertStringNotContainsString("\r", $fromEmail);
        $this->assertStringNotContainsString("\n", $fromEmail);
        // Value is now a single collapsed line — not a parseable injected header
        $this->assertSame('alice@example.comBcc: attacker@evil.com', $fromEmail);
    }

    /**
     * A clean fromEmail passes through unchanged.
     */
    public function testFromEmail_CleanEmail_IsPreservedUnchanged(): void
    {
        $fromData  = (object)['email' => 'alice@example.com'];
        $fromEmail = preg_replace('/[\r\n\t]/', '', $fromData->email);

        $this->assertSame('alice@example.com', $fromEmail);
    }

    // ------------------------------------------------------------------
    // $toEmail — regression guard: existing stripping must not be broken
    // Replicates send-owner-email.php:113
    // ------------------------------------------------------------------

    /**
     * $toEmail CR/LF stripping existed before the fix and must remain intact.
     *
     * This is a regression guard to ensure the existing sanitization is not
     * accidentally removed during future refactors of the name-field lines.
     * The strip collapses the injection attempt to a single line that a MIME
     * parser cannot interpret as a separate header.
     */
    public function testToEmail_CrLfInEmail_IsStripped(): void
    {
        $toData  = (object)['email' => "bob@example.com\r\nX-Injected: header"];
        $toEmail = preg_replace('/[\r\n\t]/', '', $toData->email);

        $this->assertStringNotContainsString("\r", $toEmail);
        $this->assertStringNotContainsString("\n", $toEmail);
        // Value is now a single collapsed line — not a parseable injected header
        $this->assertSame('bob@example.comX-Injected: header', $toEmail);
    }

    /**
     * A clean toEmail is preserved without modification.
     */
    public function testToEmail_CleanEmail_IsPreservedUnchanged(): void
    {
        $toData  = (object)['email' => 'bob@example.com'];
        $toEmail = preg_replace('/[\r\n\t]/', '', $toData->email);

        $this->assertSame('bob@example.com', $toEmail);
    }

    // ------------------------------------------------------------------
    // All four fields together — combined contract snapshot
    // ------------------------------------------------------------------

    /**
     * Snapshot: all four header-related assignments produce correct values
     * for a typical clean dataset.
     *
     * This makes it obvious at a glance when any of the four derivations
     * is changed, without requiring four separate failures to be investigated.
     */
    public function testAllHeaderFields_CleanInput_CorrectDerivation(): void
    {
        $toData   = (object)['fname' => 'Bob',   'lname' => 'Jones',  'email' => 'bob@example.com'];
        $fromData = (object)['fname' => 'Alice', 'lname' => 'Smith',  'email' => 'alice@example.com'];

        // Replicate send-owner-email.php:113-116 verbatim
        $toEmail   = preg_replace('/[\r\n\t]/', '', $toData->email);
        $toName    = (string)($toData->fname ?? '');
        $fromEmail = preg_replace('/[\r\n\t]/', '', $fromData->email);
        $fromName  = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));

        $this->assertSame('bob@example.com',   $toEmail);
        $this->assertSame('Bob',               $toName);
        $this->assertSame('alice@example.com', $fromEmail);
        $this->assertSame('Alice',             $fromName);

        // Confirm surnames are absent from the name fields
        $this->assertStringNotContainsString('Jones', $toName);
        $this->assertStringNotContainsString('Smith', $fromName);
    }

    // -------------------------------------------------------------------------
    // #1600 — source-inspection guard against the real send-owner-email.php
    // -------------------------------------------------------------------------

    /**
     * Pins $toEmail = preg_replace('/[\r\n\t]/', '', $toData->email); in the
     * real send-owner-email.php source (line 113).
     */
    public function testProductionToEmail_StripsCrLfTabFromEmail(): void
    {
        $line = $this->extractAssignmentLine($this->readSendOwnerEmailSource(), 'toEmail');

        $this->assertStringContainsString("preg_replace('" . self::HEADER_STRIP_PATTERN . "', ''", $line);
        $this->assertStringContainsString('$toData->email', $line);
    }

    /**
     * Pins $toName = (string)($toData->fname ?? ''); in the real
     * send-owner-email.php source (line 114) — and confirms it is NOT
     * stripped or lname-derived. $toName flows into the HTML template
     * greeting only (XSS-safe via htmlspecialchars() at render), not into
     * any header, so this asymmetry with the other three fields is
     * intentional.
     */
    public function testProductionToName_IsFnameOnly_NoLnameNoStripping(): void
    {
        $line = $this->extractAssignmentLine($this->readSendOwnerEmailSource(), 'toName');

        $this->assertStringContainsString("(string)(\$toData->fname ?? '')", $line);
        $this->assertStringNotContainsString(
            'lname',
            $line,
            '$toName must derive from fname only — including lname would leak the recipient surname (#1322)'
        );
        $this->assertStringNotContainsString(
            'preg_replace',
            $line,
            '$toName is intentionally unstripped — it is template-display-only (htmlspecialchars() at render), ' .
            'not header-bound; adding preg_replace here would be an unnecessary behavior change'
        );
    }

    /**
     * Pins $fromEmail = preg_replace('/[\r\n\t]/', '', $fromData->email); in
     * the real send-owner-email.php source (line 115).
     */
    public function testProductionFromEmail_StripsCrLfTabFromEmail(): void
    {
        $line = $this->extractAssignmentLine($this->readSendOwnerEmailSource(), 'fromEmail');

        $this->assertStringContainsString("preg_replace('" . self::HEADER_STRIP_PATTERN . "', ''", $line);
        $this->assertStringContainsString('$fromData->email', $line);
    }

    /**
     * Pins $fromName = preg_replace('/[\r\n\t]/', '', (string)($fromData->fname ?? ''));
     * in the real send-owner-email.php source (line 116) — and confirms
     * lname is not included. This is the direct regression guard for the
     * original #1322 bug, where the surname leaked into reply-to headers
     * via addReplyTo().
     */
    public function testProductionFromName_StripsCrLfTabFromFname_NoLname(): void
    {
        $line = $this->extractAssignmentLine($this->readSendOwnerEmailSource(), 'fromName');

        $this->assertStringContainsString("preg_replace('" . self::HEADER_STRIP_PATTERN . "', ''", $line);
        $this->assertStringContainsString("(string)(\$fromData->fname ?? '')", $line);
        $this->assertStringNotContainsString(
            'lname',
            $line,
            '$fromName must derive from fname only — including lname would reintroduce the #1322 header-injection bug'
        );
    }

    /**
     * This is a review gate, not proof every header-bound value is
     * sanitized: it only catches an existing call site being silently
     * dropped or duplicated within the $toEmail/$toName/$fromEmail/$fromName
     * derivation block. A newly added, unsanitized header-bound value would
     * leave the count unchanged and this test green — it must be caught by
     * review, not by this count.
     *
     * The count is scoped to the derivation block (not the whole file)
     * because send-owner-email.php applies the same strip pattern elsewhere
     * for unrelated purposes (e.g. sanitizing $action and log output), which
     * would otherwise inflate the count and make this guard meaningless.
     */
    public function testProductionHeaderStripPatternAppliesExactlyThreeTimes(): void
    {
        $content = $this->readSendOwnerEmailSource();

        $this->assertSame(
            1,
            preg_match('/\$toEmail\s*=.*?\$fromName\s*=.*?;/s', $content, $matches),
            'send-owner-email.php must contain the $toEmail..$fromName derivation block (#1600)'
        );
        $derivationBlock = $matches[0];

        $stripCount = substr_count($derivationBlock, "preg_replace('" . self::HEADER_STRIP_PATTERN . "', ''");
        $this->assertSame(
            3,
            $stripCount,
            'send-owner-email.php should apply the header-char sanitization pattern exactly 3 times ' .
            'within the toEmail/toName/fromEmail/fromName derivation block (toEmail, fromEmail, fromName ' .
            '— deliberately NOT toName) — update this count deliberately when adding or removing a ' .
            'sanitized call site (#1600)'
        );
    }
}
