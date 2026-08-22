<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * Namespace-scoped overrides for curl_init()/curl_setopt()/curl_exec()/
 * curl_getinfo()/curl_errno()/curl_error(), used only by
 * LocationServiceRateLimitTest to deterministically stub the upstream HTTP
 * call inside LocationService::makeHttpRequest() without a live network call.
 *
 * PHP resolves unqualified function calls against the CURRENT namespace
 * first, falling back to the global namespace only if nothing is defined
 * here. usersc/classes/LocationService.php is in the ElanRegistry namespace
 * and calls all of these functions unqualified, so these overrides take
 * precedence there — mirroring the technique already established by
 * _apcu_namespace_overrides.php in this same directory (see that file's
 * docblock for the full rationale). Without $GLOBALS['mockCurlResponse'] (or
 * $GLOBALS['mockCurlFailure']) explicitly set, every override delegates
 * transparently to the real global cURL function, so this has zero effect on
 * any other test in the suite.
 *
 * IMPORTANT: PHP has no per-file function scoping — once this file is
 * require_once'd, these ElanRegistry\* symbols are declared for the rest of
 * the PHPUnit process. Only one override source per function/namespace pair
 * can exist across the whole suite.
 *
 * Handle identity: curl_init() returns a real \CurlHandle so curl_setopt()
 * calls made against it (which this override does not intercept) remain
 * harmless no-ops against a handle nothing ever executes for real.
 */
if (!\function_exists(__NAMESPACE__ . '\\curl_init')) {
    function curl_init(?string $url = null)
    {
        return \curl_init($url ?? '');
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_setopt')) {
    function curl_setopt($handle, int $option, $value): bool
    {
        if (isset($GLOBALS['mockCurlResponse']) || ($GLOBALS['mockCurlFailure'] ?? false)) {
            // Mocked mode: no real transfer is ever executed, so setting
            // options on the real handle is unnecessary but harmless.
            return true;
        }
        return \curl_setopt($handle, $option, $value);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_exec')) {
    function curl_exec($handle)
    {
        if ($GLOBALS['mockCurlFailure'] ?? false) {
            return false;
        }
        if (isset($GLOBALS['mockCurlResponse'])) {
            return $GLOBALS['mockCurlResponse'];
        }
        return \curl_exec($handle);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_getinfo')) {
    function curl_getinfo($handle, ?int $option = null)
    {
        if (isset($GLOBALS['mockCurlResponse']) || ($GLOBALS['mockCurlFailure'] ?? false)) {
            // Only CURLINFO_HTTP_CODE is read by LocationService::makeHttpRequest().
            return $GLOBALS['mockCurlHttpCode'] ?? 200;
        }
        return \curl_getinfo($handle, $option);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_errno')) {
    function curl_errno($handle): int
    {
        if ($GLOBALS['mockCurlFailure'] ?? false) {
            return 7; // CURLE_COULDNT_CONNECT — arbitrary non-zero mock value
        }
        return \curl_errno($handle);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_error')) {
    function curl_error($handle): string
    {
        if ($GLOBALS['mockCurlFailure'] ?? false) {
            return 'mocked cURL failure';
        }
        return \curl_error($handle);
    }
}
