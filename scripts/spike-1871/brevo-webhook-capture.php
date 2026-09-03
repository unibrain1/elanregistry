<?php

declare(strict_types=1);

namespace ElanRegistry\Spike1871;

/**
 * Brevo Webhook Capture Endpoint (spike #1871)
 *
 * Throwaway capture endpoint used to observe real Brevo transactional webhook
 * traffic on the test server: the payload shape of each event type
 * (hard_bounce, soft_bounce, blocked, invalid_email, spam, delivered, opened),
 * which request header carries the Token auth and under which $_SERVER key it
 * survives A2 Hosting's shared-hosting SAPI, and whether Brevo batches events
 * into a single JSON list.
 *
 * TEMPORARY. This file is deleted once the spike is closed; nothing here is
 * production code, and the deploy hook removes scripts/ on the servers, so it
 * only reaches a server via a manual scp. See scripts/spike-1871/README.md for
 * the runbook.
 *
 * Security posture:
 * - Deliberately no UserSpice bootstrap, no session, no CSRF — Brevo cannot
 *   present any of those. Access is gated by a shared secret in the query
 *   string (?k=...) on a test-only, unlinked URL. That secret appears in
 *   server access logs; it is single-purpose, rotated by regenerating it, and
 *   grants nothing beyond appending to the capture file.
 * - Refuses to run while CAPTURE_SECRET is still the placeholder.
 * - Authorization/token-shaped header values are redacted to a 4-character
 *   prefix plus length before being written.
 * - The capture file lives outside the web root so captured payloads (which
 *   contain recipient email addresses) are never served over HTTP.
 *
 * @author Elan Registry Development Team
 */

// --- edit before scp -------------------------------------------------------
// Replace with a random 32 hex characters (e.g. `openssl rand -hex 16`).
// The endpoint returns 404 for every request while this is the placeholder.
const CAPTURE_SECRET = 'CHANGE-ME-32-HEX';

// Absolute path to the capture file. MUST be outside the web root.
const CAPTURE_FILE = '/home/unibrain/spike-1871/capture.jsonl';
// --- end edit --------------------------------------------------------------

/**
 * Send a response and stop.
 *
 * @param int $status HTTP status code
 * @param string|null $body JSON body, or null for an empty response
 * @return never
 */
function respond(int $status, ?string $body): never
{
    http_response_code($status);

    if ($body !== null) {
        header('Content-Type: application/json');
        echo $body;
    }

    exit;
}

/**
 * Redact authorization- and token-shaped header values.
 *
 * Matches on the key name (normalising `-` to `_` so getallheaders() and
 * $_SERVER spellings both hit) or on an auth-scheme prefix in the value, so a
 * token arriving under an unexpected header name is still redacted.
 *
 * @param string $key Header or $_SERVER key name
 * @param string $value Raw value
 * @return string Redacted value, or the original when nothing matched
 */
function redact(string $key, string $value): string
{
    $normalisedKey = str_replace('-', '_', strtoupper($key));

    $sensitive = [
        'AUTHORIZATION',
        'TOKEN',
        'SECRET',
        'API_KEY',
        'APIKEY',
        'X_MAILIN',
        'PHP_AUTH_PW',
        // Rewrite-derived keys (REDIRECT_QUERY_STRING, REDIRECT_URL, REQUEST_URI)
        // can echo the ?k= capture secret back into the log.
        'QUERY_STRING',
        'REQUEST_URI',
        'REDIRECT_URL',
    ];

    foreach ($sensitive as $needle) {
        if (str_contains($normalisedKey, $needle)) {
            return substr($value, 0, 4) . '…(len=' . strlen($value) . ')';
        }
    }

    if (preg_match('/^(Bearer|Basic|Token)\s+/i', $value) === 1) {
        return substr($value, 0, 4) . '…(len=' . strlen($value) . ')';
    }

    return $value;
}

// Refuse to run unless CAPTURE_SECRET has been replaced with the 32 hex
// characters the runbook specifies. This rejects the shipped placeholder
// (it contains '-') and any other unedited value, so a forgotten placeholder
// cannot be used. Shape-checked rather than compared to the placeholder
// literal, which static analysis folds into an always-true comparison.
if (preg_match('/^[0-9a-f]{32}$/', CAPTURE_SECRET) !== 1) {
    respond(404, null);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, '{"ok":false}');
}

if (!isset($_GET['k']) || !hash_equals(CAPTURE_SECRET, (string) $_GET['k'])) {
    respond(404, null);
}

$serverKeys = [];
foreach ($_SERVER as $key => $value) {
    if (!is_string($key) || !is_scalar($value)) {
        continue;
    }

    if (str_starts_with($key, 'HTTP_')
        || str_starts_with($key, 'REDIRECT_')
        || str_starts_with($key, 'CONTENT_')
        || str_starts_with($key, 'PHP_AUTH_')
    ) {
        $serverKeys[$key] = redact($key, (string) $value);
    }
}

$headers = null;
if (function_exists('getallheaders')) {
    $headers = [];
    foreach (getallheaders() as $name => $value) {
        $headers[$name] = redact((string) $name, (string) $value);
    }
}

$rawBody = (string) file_get_contents('php://input');

// json_validate() is PHP 8.3; the test server is 8.2.
$decoded = json_decode($rawBody, true);
$jsonValid = json_last_error() === JSON_ERROR_NONE;

$bodyIsList = $jsonValid && is_array($decoded) && array_is_list($decoded);

$eventCount = 0;
if ($bodyIsList) {
    /** @var array<int, mixed> $decoded */
    $eventCount = count($decoded);
} elseif ($jsonValid && is_array($decoded) && isset($decoded['event'])) {
    $eventCount = 1;
}

$record = [
    'received_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP'),
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? null,
    'sapi' => PHP_SAPI,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? null,
    'server_keys' => $serverKeys,
    'headers' => $headers,
    'raw_body' => $rawBody,
    'json_valid' => $jsonValid,
    'body_is_list' => $bodyIsList,
    'event_count' => $eventCount,
];

$line = json_encode($record, JSON_UNESCAPED_SLASHES) . "\n";
$written = file_put_contents(CAPTURE_FILE, $line, FILE_APPEND | LOCK_EX);

if ($written === false) {
    respond(500, '{"ok":false}');
}

// Always 200 on a successful capture so Brevo never retries mid-spike.
respond(200, '{"ok":true}');
