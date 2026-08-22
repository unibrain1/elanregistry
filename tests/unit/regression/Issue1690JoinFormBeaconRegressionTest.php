<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Confirms the cross-file JS naming contract between
 * app/assets/js/join-form-beacon.js and the PHP files that reference its
 * globals by string (usersc/includes/turnstile.php's data-*-callback
 * attributes and elan-turnstile-script id, usersc/join.php's onGPSError
 * callback, usersc/views/_join.php's #join-form id).
 *
 * These are string-matched names across independently-edited files with no
 * compiler/linker to catch a drift — a rename on either side breaks the
 * wiring silently (the callback simply never fires, no error anywhere).
 * That is exactly the failure class #1690 exists to close, so it earns its
 * own regression coverage rather than being left as an unasserted
 * assumption (flagged by local PR review as a real, if narrow, gap).
 *
 * @issue 1690
 * @link https://github.com/elan-registry/registry/issues/1690
 */
#[Group('regression')]
#[Group('fast')]
final class Issue1690JoinFormBeaconRegressionTest extends TestCase
{
    private const BEACON_JS_PATH = __DIR__ . '/../../../app/assets/js/join-form-beacon.js';
    private const TURNSTILE_PHP_PATH = __DIR__ . '/../../../usersc/includes/turnstile.php';
    private const JOIN_PHP_PATH = __DIR__ . '/../../../usersc/join.php';
    private const JOIN_VIEW_PATH = __DIR__ . '/../../../usersc/views/_join.php';

    private function readFile(string $path): string
    {
        $source = file_get_contents($path);
        $this->assertIsString($source, "{$path} must be readable");
        return $source;
    }

    public function testBeaconDefinesElanTurnstileErrorAndTurnstilePhpReferencesIt(): void
    {
        $beaconSource = $this->readFile(self::BEACON_JS_PATH);
        $turnstileSource = $this->readFile(self::TURNSTILE_PHP_PATH);

        $this->assertStringContainsString(
            'window.elanTurnstileError = function',
            $beaconSource,
            'join-form-beacon.js must define window.elanTurnstileError'
        );
        $this->assertStringContainsString(
            'data-error-callback="elanTurnstileError"',
            $turnstileSource,
            'turnstile.php must wire the Turnstile widget\'s error callback to the exact name '
                . 'join-form-beacon.js defines — a mismatch here means Turnstile errors are silently unreported'
        );
    }

    public function testBeaconDefinesElanTurnstileExpiredAndTurnstilePhpReferencesIt(): void
    {
        $beaconSource = $this->readFile(self::BEACON_JS_PATH);
        $turnstileSource = $this->readFile(self::TURNSTILE_PHP_PATH);

        $this->assertStringContainsString(
            'window.elanTurnstileExpired = function',
            $beaconSource,
            'join-form-beacon.js must define window.elanTurnstileExpired'
        );
        $this->assertStringContainsString(
            'data-expired-callback="elanTurnstileExpired"',
            $turnstileSource,
            'turnstile.php must wire the Turnstile widget\'s expired callback to the exact name '
                . 'join-form-beacon.js defines — a mismatch here means token expiry is silently unreported'
        );
    }

    public function testBeaconDefinesElanTurnstileNotLoadedAndAttachesItToTheScriptTagId(): void
    {
        $beaconSource = $this->readFile(self::BEACON_JS_PATH);
        $turnstileSource = $this->readFile(self::TURNSTILE_PHP_PATH);

        $this->assertStringContainsString(
            'window.elanTurnstileNotLoaded = function',
            $beaconSource,
            'join-form-beacon.js must define window.elanTurnstileNotLoaded'
        );

        // Wired via a same-origin addEventListener('error', ...) on the
        // script tag's id, not an inline onerror="..." attribute — this
        // site's CSP has no 'unsafe-inline'/script-src-attr exception, so an
        // inline event-handler attribute is silently blocked and never
        // fires (see turnstile.php's docblock).
        $this->assertStringContainsString(
            "id=\"elan-turnstile-script\"",
            $turnstileSource,
            'turnstile.php\'s script tag must carry the exact id join-form-beacon.js queries for — '
                . 'a mismatch means a blocked/failed script load is never wired to a listener at all'
        );
        $this->assertStringContainsString(
            "getElementById('elan-turnstile-script')",
            $beaconSource,
            'join-form-beacon.js must query for the exact id turnstile.php sets — a mismatch means '
                . 'the addEventListener never attaches and a blocked/failed script load goes unreported '
                . 'until the render-poll fallback catches it up to 20s later'
        );
        $this->assertStringNotContainsString(
            "' onerror=\"",
            $turnstileSource,
            'turnstile.php must not echo an inline onerror="..." attribute into the Turnstile script tag — '
                . 'this site\'s CSP silently blocks inline event-handler attributes, so it would never fire'
        );
    }

    public function testBeaconExportsElanReportJoinFailureAndJoinPhpReferencesIt(): void
    {
        $beaconSource = $this->readFile(self::BEACON_JS_PATH);
        $joinSource = $this->readFile(self::JOIN_PHP_PATH);

        $this->assertStringContainsString(
            'window.elanReportJoinFailure = reportJoinFailure',
            $beaconSource,
            'join-form-beacon.js must export window.elanReportJoinFailure'
        );
        $this->assertStringContainsString(
            'window.elanReportJoinFailure',
            $joinSource,
            'join.php\'s onGPSError callback must call the exact global name join-form-beacon.js exports — '
                . 'a mismatch means a GPS failure on the required location field is silently unreported'
        );
    }

    public function testBeaconAndJoinViewAgreeOnTheFormId(): void
    {
        $beaconSource = $this->readFile(self::BEACON_JS_PATH);
        $viewSource = $this->readFile(self::JOIN_VIEW_PATH);

        $this->assertStringContainsString(
            "getElementById('join-form')",
            $beaconSource,
            'join-form-beacon.js must scope its page-level error listeners to the join form\'s actual id'
        );
        $this->assertStringContainsString(
            'id="join-form"',
            $viewSource,
            '_join.php\'s <form> must use the exact id join-form-beacon.js checks for — a mismatch means '
                . 'the beacon\'s error/unhandledrejection listeners never scope-match and silently never fire'
        );
    }

    public function testBeaconAndEndpointAgreeOnDetailTruncationLength(): void
    {
        $beaconSource = $this->readFile(self::BEACON_JS_PATH);
        $endpointSource = $this->readFile(
            __DIR__ . '/../../../app/api/shared/join-failure-report.php'
        );

        $this->assertStringContainsString(
            '.slice(0, 300)',
            $beaconSource,
            'join-form-beacon.js must truncate detail client-side to the same length the server enforces'
        );
        $this->assertStringContainsString(
            'mb_substr(Input::raw(\'detail\') ?? \'\', 0, 300)',
            $endpointSource,
            'join-failure-report.php must truncate detail to the same length the client already applies — '
                . 'a mismatch is harmless today (server truncation is the authoritative one) but a widening '
                . 'drift would be a silent behavior change worth catching'
        );
    }
}
