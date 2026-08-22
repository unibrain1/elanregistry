<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * Namespace-scoped overrides for curl_init()/curl_setopt()/curl_exec()/
 * curl_getinfo()/curl_errno()/curl_error()/file_get_contents(), used only by
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
 * $GLOBALS['mockCurlFailure'] / $GLOBALS['mockFileGetContentsFailure'])
 * explicitly set, every override delegates transparently to the real global
 * function, so this has zero effect on any other test in the suite.
 *
 * $GLOBALS['mockFileGetContentsFailure'] (issue #1621) makes the
 * file_get_contents() override return false, deterministically driving
 * makeHttpRequest()'s file_get_contents fallback-failure branch regardless
 * of whether the test environment happens to have outbound network access.
 * This is used together with _apcu_namespace_overrides.php's
 * $GLOBALS['mockCurlInitMissing'] flag, which makes function_exists('curl_init')
 * report false so makeHttpRequest() takes the fallback branch at all.
 *
 * $GLOBALS['mockCurlFailureForUrlSubstring'] (issue #1621) makes curl_exec()
 * fail (and curl_errno()/curl_error() report a matching mocked failure) only
 * for requests whose URL contains this substring, leaving requests to any
 * other host untouched — e.g. 'photon.komoot.io' to fail only the Photon
 * call while letting the subsequent Nominatim fallback call proceed
 * normally. $GLOBALS['mockCurlResponseByUrlSubstring'] (array<string,
 * string>, substring => response body) complements it, letting that
 * untouched call return a specific mocked success body rather than
 * attempting a real transfer. Both are needed because LocationService::
 * searchLocation() calls makeHttpRequest() once per provider and the
 * flat mockCurlFailure/mockCurlResponse globals apply uniformly to both
 * calls. Tracked via a handle-keyed lookup table populated by the
 * curl_init() override below, since curl_exec()/curl_errno()/curl_error()
 * only receive the handle, not the URL.
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
        $handle = \curl_init($url ?? '');
        // WeakMap avoids leaking handle=>URL entries once a handle is
        // garbage-collected; keyed by the real \CurlHandle object identity.
        if (!isset($GLOBALS['__curlHandleUrls']) || !($GLOBALS['__curlHandleUrls'] instanceof \WeakMap)) {
            $GLOBALS['__curlHandleUrls'] = new \WeakMap();
        }
        $GLOBALS['__curlHandleUrls'][$handle] = $url ?? '';
        return $handle;
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

if (!\function_exists(__NAMESPACE__ . '\\_handleUrl')) {
    /**
     * Returns the URL a mocked handle was curl_init()'d with, or '' if
     * untracked (e.g. the real curl_init() ran before this override loaded).
     */
    function _handleUrl($handle): string
    {
        return ($GLOBALS['__curlHandleUrls'] ?? null)?->offsetExists($handle) === true
            ? $GLOBALS['__curlHandleUrls'][$handle]
            : '';
    }
}

if (!\function_exists(__NAMESPACE__ . '\\_urlSubstringFailureMatches')) {
    /**
     * True when $GLOBALS['mockCurlFailureForUrlSubstring'] is set and the
     * given handle's originating URL contains that substring.
     */
    function _urlSubstringFailureMatches($handle): bool
    {
        $substring = $GLOBALS['mockCurlFailureForUrlSubstring'] ?? null;
        return $substring !== null && str_contains(_handleUrl($handle), $substring);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\_urlSubstringResponse')) {
    /**
     * Returns the mocked response body for this handle's URL from
     * $GLOBALS['mockCurlResponseByUrlSubstring'], or null if no substring
     * key matches.
     */
    function _urlSubstringResponse($handle): ?string
    {
        $byUrl = $GLOBALS['mockCurlResponseByUrlSubstring'] ?? null;
        if (!is_array($byUrl)) {
            return null;
        }
        $url = _handleUrl($handle);
        foreach ($byUrl as $substring => $response) {
            if (str_contains($url, $substring)) {
                return $response;
            }
        }
        return null;
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_exec')) {
    function curl_exec($handle)
    {
        if (($GLOBALS['mockCurlFailure'] ?? false) || _urlSubstringFailureMatches($handle)) {
            return false;
        }
        $byUrlResponse = _urlSubstringResponse($handle);
        if ($byUrlResponse !== null) {
            return $byUrlResponse;
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
        // Not reached when curl_exec() returned false (makeHttpRequest()
        // returns early via curl_errno()/curl_error() in that case), so no
        // URL-substring-failure branch is needed here — only the success
        // (by-URL-response or flat mockCurlResponse) cases apply.
        if (
            isset($GLOBALS['mockCurlResponse'])
            || ($GLOBALS['mockCurlFailure'] ?? false)
            || _urlSubstringResponse($handle) !== null
        ) {
            // Only CURLINFO_HTTP_CODE is read by LocationService::makeHttpRequest().
            return $GLOBALS['mockCurlHttpCode'] ?? 200;
        }
        return \curl_getinfo($handle, $option);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_errno')) {
    function curl_errno($handle): int
    {
        if (($GLOBALS['mockCurlFailure'] ?? false) || _urlSubstringFailureMatches($handle)) {
            return 7; // CURLE_COULDNT_CONNECT — arbitrary non-zero mock value
        }
        return \curl_errno($handle);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\curl_error')) {
    function curl_error($handle): string
    {
        if (($GLOBALS['mockCurlFailure'] ?? false) || _urlSubstringFailureMatches($handle)) {
            return 'mocked cURL failure';
        }
        return \curl_error($handle);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\file_get_contents')) {
    function file_get_contents(string $filename, bool $use_include_path = false, $context = null, int $offset = 0, ?int $length = null): string|false
    {
        if ($GLOBALS['mockFileGetContentsFailure'] ?? false) {
            return false;
        }
        return $length !== null
            ? \file_get_contents($filename, $use_include_path, $context, $offset, $length)
            : \file_get_contents($filename, $use_include_path, $context, $offset);
    }
}
