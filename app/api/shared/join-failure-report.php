<?php
declare(strict_types=1);

use ElanRegistry\ApiResponse;
use ElanRegistry\Input;
use ElanRegistry\LogCategories;

/**
 * AJAX Endpoint: Join Form Client-Side Failure Beacon
 *
 * Reports a join submission blocked entirely client-side (Turnstile
 * failed to load/error/expire, or a JS exception before submit) — cases
 * where the POST to join.php never happens and would otherwise leave
 * zero server-side trace.
 *
 * @package ElanRegistry
 * @since v2.29.2
 * @link https://github.com/elan-registry/registry/issues/1690
 */

require_once '../../../users/init.php';

// Only allow POST requests
if ($method !== 'POST') {
    ApiResponse::error('Method not allowed', 405)->send();
}

// Reuses the join page's existing session-bound CSRF token — same token
// rendered in the join form's hidden csrf input. No anonymous-write CSRF
// exception is introduced.
if (!Token::check(Input::get('csrf'))) {
    ApiResponse::forbidden('Invalid CSRF token')
        ->withLogging(0, LogCategories::LOG_CATEGORY_SECURITY, 'Invalid CSRF token in join-failure-report beacon')
        ->send();
}

// Uses its own dedicated rate limit ('join_failure_beacon'), deliberately
// separate from 'registration_attempt' — sharing that tight bucket
// (ip_max=5/hr) would let beacon traffic (Turnstile retries, GPS failures,
// JS exceptions — none of them a real registration attempt) exhaust the cap
// for every visitor behind a shared/NAT IP before any of them could submit
// the form. See usersc/includes/rate_limits.php for the current values.
//
// checkRateLimit() lazily constructs \RateLimit on first call per request,
// whose constructor opens a database connection and can throw — the same
// failure mode LocationService::rateLimiterAllows() already documents and
// fails open around. This endpoint's whole purpose is to never lose a
// server-side trace of a failed join attempt, so a DB hiccup here must not
// turn into an uncaught fatal; fail open (treat as allowed) and log instead.
try {
    $rateLimitAllowed = checkRateLimit('join_failure_beacon');
} catch (\Throwable $e) {
    logger(0, LogCategories::LOG_CATEGORY_REGISTRATION_FAILED, 'join-failure-report: rate limit check failed, failing open: ' . $e->getMessage());
    $rateLimitAllowed = true;
}
if (!$rateLimitAllowed) {
    ApiResponse::error(getRateLimitErrorMessage('join_failure_beacon'), 429)->send();
}

// Client sends a short enum reason, not free-text, to keep log payloads
// bounded — this is not an arbitrary-text logging endpoint.
$allowedReasons = ['turnstile_error', 'turnstile_expired', 'js_exception', 'turnstile_not_loaded', 'location_gps_failed'];
$reason = Input::raw('reason') ?? '';
if (!in_array($reason, $allowedReasons, true)) {
    $reason = 'unknown';
}
$detail = mb_substr(Input::raw('detail') ?? '', 0, 300);

logger(0, LogCategories::LOG_CATEGORY_REGISTRATION_FAILED,
    'join-failure-report: Client-side submission blocked — ' . $reason,
    [
        'stage'      => 'client_blocked',
        'reason'     => $reason,
        'detail'     => $detail,
        'user_agent' => $user_agent ?? '',
    ]);

ApiResponse::success('Reported')->send();
