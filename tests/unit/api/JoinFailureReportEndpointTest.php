<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for app/api/shared/join-failure-report.php — the client-side
 * join-failure beacon added for Issue #1690.
 *
 * This file is a top-level, procedural API endpoint script (require_once
 * '../../../users/init.php' at the top, ApiResponse::send() calls that
 * return `never` / exit the process) — it cannot be require()'d directly
 * inside a PHPUnit process without terminating the test run, and this repo
 * has no HTTP-level harness for app/api/ endpoints (confirmed: no existing
 * test in tests/unit/api/ executes an endpoint file directly; ApiResponseTest
 * only tests the ApiResponse class itself).
 *
 * Following the same source-text regression pattern used by
 * Issue1406RegressionTest for usersc/join.php, this test asserts the
 * endpoint's control flow (CSRF check first, then rate limit, then
 * reason/detail normalization, then logging with the correct category and
 * stage) directly against the live source. The pure reason/detail
 * normalization logic is also duplicated and exercised directly below
 * (dataProvider-driven) since it has no side effects and is safe to
 * evaluate in isolation — this at least proves the documented normalization
 * rules are self-consistent and gives fast feedback if they're ever
 * tightened/loosened, even though it can't prove the endpoint file wires
 * them up identically (the source-text tests below cover that gap).
 *
 * @issue 1690
 * @link https://github.com/elan-registry/registry/issues/1690
 */
#[Group('regression')]
#[Group('fast')]
#[Group('api')]
final class JoinFailureReportEndpointTest extends TestCase
{
    private const ENDPOINT_PATH = __DIR__ . '/../../../app/api/shared/join-failure-report.php';

    private function endpointSource(): string
    {
        $source = file_get_contents(self::ENDPOINT_PATH);
        $this->assertIsString($source, 'app/api/shared/join-failure-report.php must be readable');
        return $source;
    }

    // ---------------------------------------------------------------
    // Source-text assertions: control flow, ordering, category/stage
    // ---------------------------------------------------------------

    public function testRejectsNonPostMethod(): void
    {
        $source = $this->endpointSource();

        $this->assertStringContainsString(
            "if (\$method !== 'POST')",
            $source,
            'The endpoint must reject non-POST requests'
        );
    }

    public function testChecksCsrfTokenBeforeRateLimit(): void
    {
        $source = $this->endpointSource();

        $csrfPos = strpos($source, '!Token::check(Input::get(\'csrf\'))');
        $this->assertNotFalse($csrfPos, 'Could not locate the CSRF check');

        $rateLimitPos = strpos($source, "checkRateLimit('join_failure_beacon')");
        $this->assertNotFalse($rateLimitPos, 'Could not locate the rate-limit check');

        $this->assertLessThan(
            $rateLimitPos,
            $csrfPos,
            'CSRF must be validated before the rate limit is checked'
        );
    }

    public function testCsrfFailureSendsForbiddenAndLogsSecurityCategory(): void
    {
        $source = $this->endpointSource();

        $csrfPos = strpos($source, '!Token::check(Input::get(\'csrf\'))');
        $this->assertNotFalse($csrfPos);

        $rateLimitPos = strpos($source, "checkRateLimit('join_failure_beacon')");
        $this->assertNotFalse($rateLimitPos);

        $csrfBranch = substr($source, $csrfPos, $rateLimitPos - $csrfPos);

        $this->assertStringContainsString('ApiResponse::forbidden(', $csrfBranch);
        $this->assertStringContainsString('LogCategories::LOG_CATEGORY_SECURITY', $csrfBranch);
        $this->assertStringContainsString('->send()', $csrfBranch);

        // Ordering, not just containment: forbidden() must be constructed
        // before the security-category logging is attached, and the whole
        // chain must terminate in ->send() — otherwise a refactor could
        // split withLogging()'s category argument onto an unrelated call
        // within this same branch and this test would still pass.
        $forbiddenPos = strpos($csrfBranch, 'ApiResponse::forbidden(');
        $securityCategoryPos = strpos($csrfBranch, 'LogCategories::LOG_CATEGORY_SECURITY');
        $sendPosInBranch = strpos($csrfBranch, '->send()');

        $this->assertLessThan(
            $securityCategoryPos,
            $forbiddenPos,
            'ApiResponse::forbidden() must be constructed before the security-category logging call'
        );
        $this->assertLessThan(
            $sendPosInBranch,
            $securityCategoryPos,
            'The security-category logging must be attached before ->send() terminates the chain'
        );
    }

    public function testRateLimitFailureReturns429(): void
    {
        $source = $this->endpointSource();

        $rateLimitPos = strpos($source, "checkRateLimit('join_failure_beacon')");
        $this->assertNotFalse($rateLimitPos);

        $reasonPos = strpos($source, '$allowedReasons');
        $this->assertNotFalse($reasonPos, 'Could not locate the reason-normalization block');

        $rateLimitBranch = substr($source, $rateLimitPos, $reasonPos - $rateLimitPos);

        $this->assertStringContainsString(
            'ApiResponse::error(getRateLimitErrorMessage(\'join_failure_beacon\'), 429)',
            $rateLimitBranch,
            'A rate-limited request must return HTTP 429 via ApiResponse::error()'
        );

        $this->assertStringContainsString(
            '!$rateLimitAllowed',
            $rateLimitBranch,
            'The 429 response must be gated on the rate-limit result, not the raw checkRateLimit() call — '
                . 'the raw call is wrapped in try/catch so a thrown error fails open instead of fataling '
                . '(see LocationService::rateLimiterAllows() for the same documented pattern)'
        );
    }

    public function testRateLimitCheckFailsOpenOnThrow(): void
    {
        $source = $this->endpointSource();

        $rateLimitPos = strpos($source, "checkRateLimit('join_failure_beacon')");
        $this->assertNotFalse($rateLimitPos, 'Could not locate the rate-limit check');

        $reasonPos = strpos($source, '$allowedReasons');
        $this->assertNotFalse($reasonPos, 'Could not locate the reason-normalization block');

        $rateLimitBranch = substr($source, $rateLimitPos - 400, $reasonPos - $rateLimitPos + 400);

        $this->assertStringContainsString(
            'try {',
            $rateLimitBranch,
            'checkRateLimit() opens a lazily-constructed \RateLimit, whose constructor can throw on a '
                . 'DB connection failure — this endpoint must not let that become an uncaught fatal'
        );
        $this->assertStringContainsString('catch (\Throwable $e)', $rateLimitBranch);
        $this->assertStringContainsString(
            '$rateLimitAllowed = true;',
            $rateLimitBranch,
            'A thrown rate-limiter error must fail open (treat the request as allowed), matching '
                . 'LocationService::rateLimiterAllows()\'s documented fail-open behavior'
        );
    }

    public function testAllowedReasonsEnumMatchesDocumentedSet(): void
    {
        $source = $this->endpointSource();

        $this->assertStringContainsString(
            "\$allowedReasons = ['turnstile_error', 'turnstile_expired', 'js_exception', "
                . "'turnstile_not_loaded', 'location_gps_failed']",
            $source,
            'The allowed-reasons enum must match the documented set of client-side failure reasons '
                . '(Turnstile failures plus location_gps_failed for the required-field GPS blocker)'
        );
    }

    public function testUnrecognizedReasonNormalizesToUnknown(): void
    {
        $source = $this->endpointSource();

        $this->assertStringContainsString(
            "if (!in_array(\$reason, \$allowedReasons, true)) {\n    \$reason = 'unknown';\n}",
            $source,
            'A reason value outside the allowed enum must be normalized to \'unknown\', not rejected outright'
        );
    }

    public function testDetailIsTruncatedTo300CharsViaMbSubstr(): void
    {
        $source = $this->endpointSource();

        $this->assertStringContainsString(
            "mb_substr(Input::raw('detail') ?? '', 0, 300)",
            $source,
            'detail must be truncated to 300 chars using a multibyte-safe substr, not the byte-unsafe substr()'
        );

        $this->assertStringNotContainsString(
            "substr(Input::raw('detail')",
            str_replace('mb_substr', '', $source),
            'detail truncation must use mb_substr, not the byte-unsafe substr()'
        );
    }

    public function testLogsWithRegistrationFailedCategoryAndClientBlockedStage(): void
    {
        $source = $this->endpointSource();

        $loggerPos = strpos($source, 'logger(0, LogCategories::LOG_CATEGORY_REGISTRATION_FAILED');
        $this->assertNotFalse(
            $loggerPos,
            'The endpoint must log via LogCategories::LOG_CATEGORY_REGISTRATION_FAILED'
        );

        $sendPos = strpos($source, "ApiResponse::success('Reported')->send()");
        $this->assertNotFalse($sendPos, 'Could not locate the success response');

        $call = substr($source, $loggerPos, $sendPos - $loggerPos);

        $this->assertStringContainsString("'stage'      => 'client_blocked'", $call);
        $this->assertStringContainsString("'reason'     => \$reason", $call);
        $this->assertStringContainsString("'detail'     => \$detail", $call);
        $this->assertStringContainsString("'user_agent' => \$user_agent ?? ''", $call);

        // Confirm all four metadata keys live inside THIS logger() call's
        // own metadata array, not just somewhere in the wider
        // logger()...send() window — bound the search to the metadata
        // array's own brackets (the first "[" after the logger() call
        // opens, up to its matching top-level "]" before the closing ");").
        $metadataStart = strpos($call, '[');
        $this->assertNotFalse($metadataStart, 'Could not locate the metadata array');
        $metadataEnd = strpos($call, ']);', $metadataStart);
        $this->assertNotFalse($metadataEnd, 'Could not locate the end of the metadata array');
        $metadataArray = substr($call, $metadataStart, $metadataEnd - $metadataStart);

        foreach (["'stage'", "'reason'", "'detail'", "'user_agent'"] as $key) {
            $this->assertStringContainsString(
                $key,
                $metadataArray,
                "Metadata key {$key} must be inside this logger() call's own array, not elsewhere in the file"
            );
        }
    }

    public function testLoggingHappensBeforeSuccessResponse(): void
    {
        $source = $this->endpointSource();

        $loggerPos = strpos($source, 'logger(0, LogCategories::LOG_CATEGORY_REGISTRATION_FAILED');
        $sendPos = strpos($source, "ApiResponse::success('Reported')->send()");

        $this->assertNotFalse($loggerPos);
        $this->assertNotFalse($sendPos);
        $this->assertLessThan(
            $sendPos,
            $loggerPos,
            'The failure must be logged before the success response is sent'
        );
    }

    public function testNotAddedToSecurePagePathArray(): void
    {
        // Per the implementation plan and CLAUDE.md convention: pure API
        // endpoints with no securePage() call are not added to
        // z_us_root.php's $path array. Confirm this endpoint has no
        // securePage() call (which would make omission from $path a bug).
        $source = $this->endpointSource();

        $this->assertStringNotContainsString(
            'securePage(',
            $source,
            'join-failure-report.php must not call securePage() — it is an anonymous, '
                . 'CSRF-protected, rate-limited endpoint, consistent with other app/api/shared/ scripts'
        );
    }

    // ---------------------------------------------------------------
    // Pure logic checks: reason normalization / detail truncation,
    // evaluated directly (documents the intended behavior; the
    // source-text tests above confirm the endpoint file matches it).
    // ---------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function reasonProvider(): array
    {
        return [
            'turnstile_error is allowed' => ['turnstile_error', 'turnstile_error'],
            'turnstile_expired is allowed' => ['turnstile_expired', 'turnstile_expired'],
            'js_exception is allowed' => ['js_exception', 'js_exception'],
            'turnstile_not_loaded is allowed' => ['turnstile_not_loaded', 'turnstile_not_loaded'],
            'location_gps_failed is allowed' => ['location_gps_failed', 'location_gps_failed'],
            'unrecognized string normalizes to unknown' => ['bogus_reason', 'unknown'],
            'empty string normalizes to unknown' => ['', 'unknown'],
            'sql-injection-shaped input normalizes to unknown' => ["'; DROP TABLE logs; --", 'unknown'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('reasonProvider')]
    public function testReasonNormalizationLogic(string $input, string $expected): void
    {
        $allowedReasons = ['turnstile_error', 'turnstile_expired', 'js_exception', 'turnstile_not_loaded', 'location_gps_failed'];
        $reason = $input;
        if (!in_array($reason, $allowedReasons, true)) {
            $reason = 'unknown';
        }

        $this->assertSame($expected, $reason);
    }

    public function testDetailTruncationLogicCapsAt300Chars(): void
    {
        $detail = mb_substr(str_repeat('a', 500), 0, 300);

        $this->assertSame(300, mb_strlen($detail));
    }

    public function testDetailTruncationLogicPreservesShorterStrings(): void
    {
        $detail = mb_substr('short detail', 0, 300);

        $this->assertSame('short detail', $detail);
    }

    public function testDetailTruncationLogicIsMultibyteSafe(): void
    {
        // 300 multi-byte characters (each 3 bytes in UTF-8) — byte-based
        // substr(...,0,300) would cut mid-character; mb_substr must not.
        $input = str_repeat('€', 400);
        $detail = mb_substr($input, 0, 300);

        $this->assertSame(300, mb_strlen($detail));
        $this->assertSame(str_repeat('€', 300), $detail);
    }
}
