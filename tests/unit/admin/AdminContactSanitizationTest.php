<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use ElanRegistry\InputSanitizer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression tests for admin contact sanitization bugs.
 *
 * #660: CR/LF stripping on $qualityIssue before use in SMTP subject line.
 * #661: filter_var validation on target_email in the Multiple-owner path.
 *
 * The action file (process-admin-contact.php) cannot be unit-tested directly
 * (requires full framework bootstrap), so the #660 behavioral tests call the
 * real ElanRegistry\InputSanitizer::stripHeaderInjectionChars() helper
 * directly (not a mirrored copy), covering input/output edge cases (CR, LF,
 * tabs, combined injection, clean values, null). Those behavioral tests are
 * backed by a source-inspection guard (see the source-inspection guard
 * section below) that reads the real process-admin-contact.php source and
 * asserts the exact sanitization is present, so a regression in production
 * would be caught even though the file itself cannot be executed in a unit
 * test. Updated for #1759 to remove the mirrored regex in favor of the real
 * shared helper.
 *
 * The #661 behavioral tests below exercise PHP's own filter_var() semantics
 * directly and are similarly backed by a source-inspection guard tying them
 * to the real target_email validation call site.
 */
class AdminContactSanitizationTest extends TestCase
{
    private const PROCESS_ADMIN_CONTACT_FILE = 'app/admin/includes/process-admin-contact.php';

    private string $rootDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = dirname(__DIR__, 3);
    }

    private function readProcessAdminContactSource(): string
    {
        $filePath = $this->rootDir . '/' . self::PROCESS_ADMIN_CONTACT_FILE;
        $this->assertFileExists($filePath, 'process-admin-contact.php must exist (#660, #661)');

        return (string) file_get_contents($filePath);
    }

    // -------------------------------------------------------------------------
    // #660 — CR/LF stripping on $qualityIssue
    // -------------------------------------------------------------------------

    public function testQualityIssueCarriageReturnStripped(): void
    {
        $sanitized = InputSanitizer::stripHeaderInjectionChars("Missing documents\rX-Injected: evil");
        $this->assertStringNotContainsString("\r", $sanitized);
        $this->assertSame('Missing documentsX-Injected: evil', $sanitized);
    }

    public function testQualityIssueLineFeedStripped(): void
    {
        $sanitized = InputSanitizer::stripHeaderInjectionChars("Missing documents\nBcc: attacker@example.com");
        $this->assertStringNotContainsString("\n", $sanitized);
        $this->assertSame('Missing documentsBcc: attacker@example.com', $sanitized);
    }

    public function testQualityIssueCombinedInjectionStripped(): void
    {
        $sanitized = InputSanitizer::stripHeaderInjectionChars("Scratch\r\nBcc: attacker@example.com");
        $this->assertStringNotContainsString("\r", $sanitized);
        $this->assertStringNotContainsString("\n", $sanitized);
        $this->assertSame('ScratchBcc: attacker@example.com', $sanitized);
    }

    public function testQualityIssueTabStripped(): void
    {
        $sanitized = InputSanitizer::stripHeaderInjectionChars("Missing\tdocuments");
        $this->assertStringNotContainsString("\t", $sanitized);
        $this->assertSame('Missingdocuments', $sanitized);
    }

    public function testQualityIssueCleanValueUnchanged(): void
    {
        $raw = 'Missing registration documents';
        $this->assertSame($raw, InputSanitizer::stripHeaderInjectionChars($raw));
    }

    public function testQualityIssueNullHandledSafely(): void
    {
        $this->assertSame('', InputSanitizer::stripHeaderInjectionChars(null));
    }

    // -------------------------------------------------------------------------
    // #660 — source-inspection guard against the real process-admin-contact.php
    // -------------------------------------------------------------------------

    /**
     * Deliberately checks only "InputSanitizer::stripHeaderInjectionChars(
     * ... <expression> ..." rather than pinning the full statement
     * (assignment target, casts, ternary wrapper) — a harmless refactor of
     * the surrounding syntax shouldn't break this guard; only a change to
     * the helper call itself or to which value it's applied to should.
     */
    #[DataProvider('headerStripCallSitesProvider')]
    public function testProductionStripsHeaderCharsForCallSite(string $description, string $targetExpression): void
    {
        $content = $this->readProcessAdminContactSource();

        $pattern = '/' . preg_quote('InputSanitizer::stripHeaderInjectionChars(', '/')
            . '.*?' . preg_quote($targetExpression, '/') . '/';

        $this->assertSame(
            1,
            preg_match($pattern, $content),
            "process-admin-contact.php must strip CR/LF/tab from {$description} ({$targetExpression}) (#660)"
        );
    }

    public static function headerStripCallSitesProvider(): array
    {
        return [
            'owner email' => ['owner email', '$ownerData->email'],
            'admin email' => ['admin email', '$adminData->email'],
            'quality issue' => ['quality issue', '$qualityIssue'],
            'delivery error message' => ['delivery error message', '$result'],
        ];
    }

    /**
     * This is a review gate, not proof every header-bound value is
     * sanitized: it only catches an existing call site being silently
     * dropped or duplicated. A newly added, unsanitized header-bound value
     * would leave the count unchanged and this test green — it must be
     * caught by review, not by this count.
     */
    public function testProductionSanitizationPatternAppliesExactlyFourTimes(): void
    {
        $content = $this->readProcessAdminContactSource();

        $sanitizationCount = substr_count($content, 'InputSanitizer::stripHeaderInjectionChars(');
        $this->assertSame(
            4,
            $sanitizationCount,
            'process-admin-contact.php should apply InputSanitizer::stripHeaderInjectionChars() exactly 4 times ' .
            '(owner email, admin email, quality issue, delivery error message) — update this count deliberately ' .
            'when adding or removing a sanitized call site (#660)'
        );
    }

    // -------------------------------------------------------------------------
    // #661 — filter_var validation on target_email
    // -------------------------------------------------------------------------

    public function testTargetEmailValidAccepted(): void
    {
        $email = 'owner@example.com';
        $result = filter_var($email, FILTER_VALIDATE_EMAIL);
        $this->assertSame($email, $result);
    }

    public function testTargetEmailInvalidRejected(): void
    {
        $email = 'not-an-email';
        $result = filter_var($email, FILTER_VALIDATE_EMAIL);
        $this->assertFalse($result);
    }

    public function testTargetEmailInjectionAttemptRejected(): void
    {
        $email = "victim@example.com\r\nBcc: attacker@evil.com";
        $result = filter_var($email, FILTER_VALIDATE_EMAIL);
        $this->assertFalse($result);
    }

    public function testTargetEmailEmptyRejected(): void
    {
        $result = filter_var('', FILTER_VALIDATE_EMAIL);
        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // #661 — source-inspection guards against the real process-admin-contact.php
    // -------------------------------------------------------------------------

    /**
     * Joins the filter_var check and its throw with [\s\S]*? (tolerant of the
     * newline between them) rather than pinning the full statement, mirroring
     * the #660 guard above: a harmless reformat of either line shouldn't break
     * this guard; only a change to the check itself, its target, or dropping
     * the rejection should.
     */
    public function testProductionValidatesTargetEmailFormat(): void
    {
        $content = $this->readProcessAdminContactSource();

        $pattern = '/if\s*\(\s*!\s*filter_var\s*\(\s*\$targetEmail\s*,\s*FILTER_VALIDATE_EMAIL\s*\)\s*\)' .
            '[\s\S]*?throw\s+new\s+AdminContactException\s*\(\s*[\'"]Invalid target email address format[\'"]\s*\)/';

        $this->assertSame(
            1,
            preg_match($pattern, $content),
            'process-admin-contact.php must validate target_email with filter_var(..., FILTER_VALIDATE_EMAIL) ' .
            'and reject it via AdminContactException (#661)'
        );
    }

    /**
     * Companion to testProductionValidatesTargetEmailFormat(): target_email
     * validation has two distinct branches (empty-check, then format-check),
     * each with its own rejection message — this guards the empty-check
     * branch so a dropped or reworded check there is caught too, not just
     * the format check.
     */
    public function testProductionRejectsEmptyTargetEmail(): void
    {
        $content = $this->readProcessAdminContactSource();

        $pattern = '/if\s*\(\s*empty\s*\(\s*\$targetEmail\s*\)\s*\)' .
            '[\s\S]*?throw\s+new\s+AdminContactException\s*\(\s*[\'"]Target email not provided for multiple users[\'"]\s*\)/';

        $this->assertSame(
            1,
            preg_match($pattern, $content),
            'process-admin-contact.php must reject an empty target_email via AdminContactException (#661)'
        );
    }
}
