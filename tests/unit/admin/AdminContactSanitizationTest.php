<?php

declare(strict_types=1);

namespace Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Regression tests for admin contact sanitization bugs.
 *
 * #660: CR/LF stripping on $qualityIssue before use in SMTP subject line.
 * #661: filter_var validation on target_email in the Multiple-owner path.
 *
 * The action file (process-admin-contact.php) cannot be unit-tested directly
 * (requires full framework bootstrap), so the #660 behavioral tests validate
 * a mirrored copy of the sanitization pattern in isolation, covering
 * input/output edge cases (CR, LF, tabs, combined injection, clean values,
 * null). Those behavioral tests are backed by a source-inspection guard (see
 * the source-inspection guard section below) that reads the real
 * process-admin-contact.php source and asserts the exact sanitization is
 * present, so a regression in production would be caught even though the
 * file itself cannot be executed in a unit test.
 */
class AdminContactSanitizationTest extends TestCase
{
    private const PROCESS_ADMIN_CONTACT_FILE = 'app/admin/includes/process-admin-contact.php';

    /**
     * Mirrors the pattern in process-admin-contact.php — kept as one constant
     * so the mirror below and the source-inspection guard can't drift apart.
     */
    private const HEADER_STRIP_PATTERN = '/[\r\n\t]/';

    private string $rootDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = dirname(__DIR__, 3);
    }

    /**
     * Mirrors the CR/LF-stripping pattern used in process-admin-contact.php
     * for header-bound values ($toEmail, $fromEmail, $qualityIssue).
     */
    private function stripHeaderChars(?string $value): string
    {
        return preg_replace(self::HEADER_STRIP_PATTERN, '', (string) $value);
    }

    private function readProcessAdminContactSource(): string
    {
        $filePath = $this->rootDir . '/' . self::PROCESS_ADMIN_CONTACT_FILE;
        $this->assertFileExists($filePath, 'process-admin-contact.php must exist (#660)');

        return (string) file_get_contents($filePath);
    }

    // -------------------------------------------------------------------------
    // #660 — CR/LF stripping on $qualityIssue
    // -------------------------------------------------------------------------

    public function testQualityIssueCarriageReturnStripped(): void
    {
        $sanitized = $this->stripHeaderChars("Missing documents\rX-Injected: evil");
        $this->assertStringNotContainsString("\r", $sanitized);
        $this->assertSame('Missing documentsX-Injected: evil', $sanitized);
    }

    public function testQualityIssueLineFeedStripped(): void
    {
        $sanitized = $this->stripHeaderChars("Missing documents\nBcc: attacker@example.com");
        $this->assertStringNotContainsString("\n", $sanitized);
        $this->assertSame('Missing documentsBcc: attacker@example.com', $sanitized);
    }

    public function testQualityIssueCombinedInjectionStripped(): void
    {
        $sanitized = $this->stripHeaderChars("Scratch\r\nBcc: attacker@example.com");
        $this->assertStringNotContainsString("\r", $sanitized);
        $this->assertStringNotContainsString("\n", $sanitized);
        $this->assertSame('ScratchBcc: attacker@example.com', $sanitized);
    }

    public function testQualityIssueTabStripped(): void
    {
        $sanitized = $this->stripHeaderChars("Missing\tdocuments");
        $this->assertStringNotContainsString("\t", $sanitized);
        $this->assertSame('Missingdocuments', $sanitized);
    }

    public function testQualityIssueCleanValueUnchanged(): void
    {
        $raw = 'Missing registration documents';
        $this->assertSame($raw, $this->stripHeaderChars($raw));
    }

    public function testQualityIssueNullHandledSafely(): void
    {
        $this->assertSame('', $this->stripHeaderChars(null));
    }

    // -------------------------------------------------------------------------
    // #660 — source-inspection guard against the real process-admin-contact.php
    // -------------------------------------------------------------------------

    /**
     * Deliberately checks only "preg_replace(PATTERN, '', ... <expression> ..."
     * rather than pinning the full statement (assignment target, casts,
     * ternary wrapper) — a harmless refactor of the surrounding syntax
     * shouldn't break this guard; only a change to the regex itself or to
     * which value it's applied to should.
     */
    #[DataProvider('headerStripCallSitesProvider')]
    public function testProductionStripsHeaderCharsForCallSite(string $description, string $targetExpression): void
    {
        $content = $this->readProcessAdminContactSource();

        $pattern = '/' . preg_quote("preg_replace('" . self::HEADER_STRIP_PATTERN . "', ''", '/')
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

        $sanitizationCount = substr_count($content, "preg_replace('" . self::HEADER_STRIP_PATTERN . "', ''");
        $this->assertSame(
            4,
            $sanitizationCount,
            'process-admin-contact.php should apply the header-char sanitization pattern exactly 4 times ' .
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
}
