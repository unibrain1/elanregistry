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
 * attributes, and the script tag gets id="elan-turnstile-script" so
 * join-form-beacon.js can attach a same-origin addEventListener('error', ...)
 * listener to it (an inline onerror="..." attribute would be silently
 * blocked by this site's CSP — script-src has no 'unsafe-inline'/
 * script-src-attr exception, and browsers refuse inline event-handler
 * attributes under that policy; a JS-attached listener isn't subject to
 * that restriction). elanTurnstileError/elanTurnstileExpired/
 * elanTurnstileNotLoaded are defined only by app/assets/js/join-form-beacon.js,
 * loaded solely by usersc/views/_join.php — pass true only from the join
 * page's own call site (usersc/plugins/hooker/hooks/join_form_turnstile.php).
 * Passing true from a page that doesn't load join-form-beacon.js would have
 * Cloudflare's widget invoke undefined window functions; whether that's
 * actually tolerated by Cloudflare's widget isn't verifiable in this repo
 * (the CDN script isn't vendored here), so this is opt-in rather than
 * assumed-safe everywhere. No-ops silently when Turnstile is disabled (off
 * mode or plain HTTP).
 *
 * @param bool $withFailureCallbacks Wire the join page's failure-reporting
 *        callbacks. Defaults to false — safe for login/forgot-password.
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
    // above). Only given an id when $withFailureCallbacks is true (join page
    // only), same gate as the data-*-callback attributes above.
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
    $token = $_POST['cf-turnstile-response'] ?? '';
    if (empty($token)) {
        return false;
    }
    global $remote_addr;
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
