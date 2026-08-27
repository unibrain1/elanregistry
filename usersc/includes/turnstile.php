<?php
declare(strict_types=1);

use ElanRegistry\LogCategories;

/**
 * Cloudflare Turnstile CAPTCHA integration.
 *
 * Keys loaded from $_ENV via phpdotenv:
 *   TURNSTILE_SITE_KEY   — widget site key (public, rendered in HTML)
 *   TURNSTILE_SECRET_KEY — server-side verification key (private)
 *
 * Off mode: omit either key from .env — forms work without CAPTCHA.
 * Fail closed: API errors block submission and are logged.
 */

/**
 * Check whether Cloudflare Turnstile is enabled and configured.
 *
 * Requires HTTPS — the Turnstile iframe is served over https:// and browsers
 * block cross-protocol frame access, producing error 110200 on plain HTTP.
 *
 * @return bool True when both env keys are present and the connection is HTTPS.
 */
function isTurnstileEnabled(): bool
{
    global $is_https;
    return !empty($is_https)
        && !empty($_ENV['TURNSTILE_SECRET_KEY'])
        && !empty($_ENV['TURNSTILE_SITE_KEY']);
}

/**
 * Render the Cloudflare Turnstile widget into the current form.
 *
 * Outputs a .cf-turnstile div wrapped in a Bootstrap .d-flex.justify-content-center.my-2
 * flex container, followed by the Turnstile api.js script tag.
 *
 * When $withFailureCallbacks is true, the div also carries
 * data-error-callback="elanTurnstileError" and data-expired-callback="elanTurnstileExpired"
 * attributes, and the script tag gets id="elan-turnstile-script" so a
 * page-appropriate handler can attach a same-origin addEventListener('error', ...)
 * listener to it (an inline onerror="..." attribute would be silently
 * blocked by this site's CSP — script-src has no 'unsafe-inline'/
 * script-src-attr exception, and browsers refuse inline event-handler
 * attributes under that policy; a JS-attached listener isn't subject to
 * that restriction).
 *
 * elanTurnstileError/elanTurnstileExpired are defined by the shared
 * app/assets/js/turnstile-reset.js (loaded by both usersc/login.php and
 * usersc/views/_join.php — see issue #1798), so both call sites can safely
 * pass true. On the join page, join-form-beacon.js (loaded after
 * turnstile-reset.js) redefines these two names with its own richer
 * versions — status-message updates and failure-beacon reporting — that
 * delegate to the shared reset via window.elanTurnstileReset() internally.
 * elanTurnstileNotLoaded remains defined only by join-form-beacon.js and is
 * join-page-specific (covers the api.js script-tag error listener and the
 * widget-render poll, both scoped to #join-form) — passing true from any
 * page that doesn't load join-form-beacon.js is still safe for the reset
 * behavior itself, but won't get the join page's richer error reporting.
 * No-ops silently when Turnstile is disabled (off mode or plain HTTP).
 *
 * @param bool $withFailureCallbacks Wire data-error-callback/data-expired-callback
 *        to elanTurnstileError/elanTurnstileExpired. Safe to pass true from
 *        any page that loads turnstile-reset.js (currently login and join).
 * @return void
 */
function addTurnstile(bool $withFailureCallbacks = false): void
{
    if (!isTurnstileEnabled()) {
        return;
    }
    $siteKey = htmlspecialchars($_ENV['TURNSTILE_SITE_KEY'], ENT_QUOTES, 'UTF-8');
    $callbackAttrs = $withFailureCallbacks
        ? ' data-error-callback="elanTurnstileError" data-expired-callback="elanTurnstileExpired"'
        : '';
    echo '<div class="d-flex justify-content-center my-2">' . "\n";
    echo '    <div class="cf-turnstile" data-sitekey="' . $siteKey . '" data-appearance="always"' . $callbackAttrs . '></div>' . "\n";
    echo '</div>' . "\n";
    // The script-load-failure case (network block, DNS failure) — the one
    // data-error-callback cannot see, since that only fires once Turnstile's
    // own JS is already running — is covered by join-form-beacon.js
    // attaching a real addEventListener('error', ...) to this tag via the id
    // below, not an inline onerror attribute (CSP-blocked; see the docblock
    // above). This listener is currently only attached on the join page
    // (join-form-beacon.js is join-specific); the id is still emitted
    // whenever $withFailureCallbacks is true so any future page loading an
    // equivalent listener can also target it, same gate as the
    // data-*-callback attributes above.
    $scriptId = $withFailureCallbacks ? ' id="elan-turnstile-script"' : '';
    echo '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer'
        . $scriptId . '></script>' . "\n";
}

/**
 * Verify the Turnstile token submitted with the current POST request.
 *
 * Returns true immediately when Turnstile is disabled (off mode).
 * Returns false when the token is absent or when the Cloudflare API rejects it.
 * API errors are logged and treated as failures (fail-closed).
 *
 * @return bool True when the challenge passes or Turnstile is disabled.
 */
function verifyTurnstile(): bool
{
    if (!isTurnstileEnabled()) {
        return true;
    }
    global $remote_addr;
    $token = $_POST['cf-turnstile-response'] ?? '';
    if (empty($token)) {
        logger(0, LogCategories::LOG_CATEGORY_SECURITY, 'Turnstile: empty token submitted from ' . $remote_addr);
        return false;
    }
    return _verifyTurnstileToken($_ENV['TURNSTILE_SECRET_KEY'], $token, $remote_addr);
}

/**
 * POST the token to the Cloudflare siteverify endpoint and return the result.
 *
 * @param string $secret Server-side Turnstile secret key.
 * @param string $token  cf-turnstile-response token from the POST body.
 * @param string $ip     Client IP address passed to Cloudflare as a risk signal for bot
 *                       and fraud detection. Optional per the API but improves challenge accuracy.
 * @return bool True when Cloudflare returns success:true.
 */
function _verifyTurnstileToken(string $secret, string $token, string $ip): bool
{
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    if ($ch === false) {
        logger(0, LogCategories::LOG_CATEGORY_SYSTEM_ERROR, 'Turnstile: curl_init() failed — cURL extension may be unavailable');
        return false;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]),
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $result    = curl_exec($ch);
    $curlErrno = curl_errno($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlErrno || $result === false) {
        logger(0, LogCategories::LOG_CATEGORY_SYSTEM_ERROR, 'Turnstile cURL error: ' . $curlError);
        return false;
    }
    $data = json_decode((string) $result, true);
    if (!is_array($data)) {
        logger(0, LogCategories::LOG_CATEGORY_SYSTEM_ERROR, 'Turnstile returned invalid JSON: ' . substr((string) $result, 0, 200));
        return false;
    }
    if (!($data['success'] ?? false)) {
        logger(0, LogCategories::LOG_CATEGORY_SECURITY, 'Turnstile rejected token from ' . $ip . ': ' . implode(', ', $data['error-codes'] ?? ['unknown']));
    }
    return (bool) ($data['success'] ?? false);
}
