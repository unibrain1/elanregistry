<?php

declare(strict_types=1);

use ElanRegistry\LogCategories;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

require_once __DIR__ . '/../../../usersc/includes/turnstile.php';

/**
 * Unit tests for verifyTurnstile()'s empty-token logging (issue #1798).
 *
 * Every other failure path in turnstile.php logs (Cloudflare rejection,
 * cURL failure, invalid JSON); the empty-token branch previously returned
 * false silently. These tests lock in that it now logs via logger() with
 * LogCategories::LOG_CATEGORY_SECURITY and the client IP, matching the
 * sibling rejection-log's category and message-wording convention.
 *
 * Known gap: no test here exercises verifyTurnstile() with a non-empty
 * token, because the very next line in that function falls through to
 * _verifyTurnstileToken(), a real network call to Cloudflare's siteverify
 * endpoint with no mock seam (plain function, not a class/interface) —
 * a fast unit test can't safely depend on that. Not closed in this PR.
 */
#[Group('fast')]
#[Group('unit')]
#[Group('security')]
class TurnstileTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        global $mockLogEntries, $is_https, $remote_addr;
        $mockLogEntries = [];
        $is_https = true;
        $remote_addr = '203.0.113.42';
        $_ENV['TURNSTILE_SITE_KEY'] = 'test-site-key';
        $_ENV['TURNSTILE_SECRET_KEY'] = 'test-secret-key';
        $_POST = [];
    }

    protected function tearDown(): void
    {
        unset($_ENV['TURNSTILE_SITE_KEY'], $_ENV['TURNSTILE_SECRET_KEY'], $_POST['cf-turnstile-response']);
        parent::tearDown();
    }

    #[Group('fast')]
    public function testEmptyTokenLogsSecurityEntryWithClientIp(): void
    {
        global $mockLogEntries;
        unset($_POST['cf-turnstile-response']);

        $result = verifyTurnstile();

        $this->assertFalse($result);
        $this->assertCount(1, $mockLogEntries);
        $this->assertSame(LogCategories::LOG_CATEGORY_SECURITY, $mockLogEntries[0]['category']);
        $this->assertStringContainsString('empty token', $mockLogEntries[0]['message']);
        $this->assertStringContainsString('203.0.113.42', $mockLogEntries[0]['message']);
    }

    #[Group('fast')]
    public function testEmptyStringTokenIsLoggedTheSameAsAbsentKey(): void
    {
        global $mockLogEntries;
        $_POST['cf-turnstile-response'] = '';

        $result = verifyTurnstile();

        $this->assertFalse($result);
        $this->assertCount(1, $mockLogEntries);
        $this->assertSame(LogCategories::LOG_CATEGORY_SECURITY, $mockLogEntries[0]['category']);
    }

    #[Group('fast')]
    public function testDisabledTurnstileSkipsVerificationAndDoesNotLog(): void
    {
        global $mockLogEntries, $is_https;
        $is_https = false;
        unset($_POST['cf-turnstile-response']);

        $result = verifyTurnstile();

        $this->assertTrue($result);
        $this->assertEmpty($mockLogEntries);
    }

    #[Group('fast')]
    public function testEmptyTokenLogMessageIsDistinctFromRejectionLogMessage(): void
    {
        global $mockLogEntries;
        unset($_POST['cf-turnstile-response']);

        verifyTurnstile();

        // The existing Cloudflare-rejection log (turnstile.php's
        // _verifyTurnstileToken()) reads "Turnstile rejected token from
        // <ip>: <codes>" — the empty-token message must not collide with
        // that wording, so the two failure classes stay distinguishable
        // when triaging Security-category logs.
        $this->assertStringNotContainsString('rejected', $mockLogEntries[0]['message']);
    }

    /**
     * Deterministic coverage of addTurnstile(true)'s actual HTML output —
     * independent of live Cloudflare keys/HTTPS, unlike the Playwright
     * attribute-wiring test which skips whenever Turnstile isn't configured
     * (no TURNSTILE_SITE_KEY/TURNSTILE_SECRET_KEY in this repo's CI). Proves
     * addTurnstile(true) itself works correctly — testLoginHook... below
     * proves the login hook actually calls it with true, which this test
     * alone does not.
     */
    #[Group('fast')]
    public function testAddTurnstileWithFailureCallbacksEmitsCallbackAttributesAndScriptId(): void
    {
        ob_start();
        addTurnstile(true);
        $html = ob_get_clean();

        $this->assertStringContainsString('data-error-callback="elanTurnstileError"', $html);
        $this->assertStringContainsString('data-expired-callback="elanTurnstileExpired"', $html);
        $this->assertStringContainsString('id="elan-turnstile-script"', $html);
    }

    /**
     * The exact regression #1798 fixes: login_form_turnstile.php must call
     * addTurnstile(true), not addTurnstile() — a revert back to the no-arg
     * form would leave the login widget with no working reset path again,
     * but every other test in this suite would stay green (they test
     * addTurnstile() itself, not this hook file's call to it). This is the
     * only CI-enforced (non-skippable) guard against that specific revert —
     * the Playwright attribute-wiring test would also catch it, but that one
     * skips whenever live Turnstile keys aren't configured, which CI never
     * sets.
     */
    #[Group('fast')]
    public function testLoginHookCallsAddTurnstileWithFailureCallbacksEnabled(): void
    {
        ob_start();
        require __DIR__ . '/../../../usersc/plugins/hooker/hooks/login_form_turnstile.php';
        $html = ob_get_clean();

        $this->assertStringContainsString(
            'data-error-callback="elanTurnstileError"',
            $html,
            'login_form_turnstile.php must call addTurnstile(true) — reverting to addTurnstile() ' .
            'reintroduces the #1798 regression (no reset path on the login form)'
        );
        $this->assertStringContainsString('data-expired-callback="elanTurnstileExpired"', $html);
    }

    #[Group('fast')]
    public function testAddTurnstileWithoutFailureCallbacksOmitsCallbackAttributesAndScriptId(): void
    {
        ob_start();
        addTurnstile(false);
        $html = ob_get_clean();

        $this->assertStringNotContainsString('data-error-callback', $html);
        $this->assertStringNotContainsString('data-expired-callback', $html);
        $this->assertStringNotContainsString('id="elan-turnstile-script"', $html);
    }
}
