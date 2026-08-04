<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * Namespace-scoped overrides for function_exists()/apcu_fetch()/apcu_store(),
 * used only by LocationServiceCacheTest's APCu-fallback test to
 * deterministically simulate "extension present but non-functional" (#1470)
 * — the exact bug class LocationService::getCache()/setCache() were fixed
 * to handle (function_exists() proves the extension is loaded, not that it
 * actually works; apc.enable_cli=Off is PHP's CLI-SAPI default and makes
 * every apcu_store()/apcu_fetch() call fail silently).
 *
 * PHP resolves unqualified function calls against the CURRENT namespace
 * first, falling back to the global namespace only if nothing is defined
 * here. usersc/classes/LocationService.php is also in the ElanRegistry
 * namespace and calls all three functions unqualified, so these overrides
 * take precedence over the real global ones there — without affecting any
 * other file, since PHP namespace resolution is per-call-site, not global.
 *
 * Each override delegates transparently to the real global function unless
 * $GLOBALS['mockApcuSimulateFailure'] is explicitly set true, so this has
 * zero effect on any of this suite's other 19+ tests (or any other test
 * file) regardless of whether the real environment's apcu extension is
 * loaded, loaded-but-disabled, or absent entirely.
 *
 * IMPORTANT: PHP has no per-file function scoping — once this file is
 * require_once'd, these three ElanRegistry\* symbols are declared for the
 * rest of the PHPUnit process, not just this test file. Only one override
 * source per function/namespace pair can exist across the whole suite; a
 * second file attempting to redeclare any of these would fatal with
 * "cannot redeclare". If a future test needs different simulated behavior
 * for the same functions, extend this file's flag-driven logic rather than
 * adding a second override file.
 */
if (!\function_exists(__NAMESPACE__ . '\\function_exists')) {
    function function_exists(string $name): bool
    {
        if (($GLOBALS['mockApcuSimulateFailure'] ?? false) && \in_array($name, ['apcu_fetch', 'apcu_store'], true)) {
            return true;
        }
        return \function_exists($name);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\apcu_fetch')) {
    function apcu_fetch(string $key, &$success = null)
    {
        if ($GLOBALS['mockApcuSimulateFailure'] ?? false) {
            $success = false;
            return false;
        }
        return \apcu_fetch($key, $success);
    }
}

if (!\function_exists(__NAMESPACE__ . '\\apcu_store')) {
    function apcu_store(string $key, mixed $value, int $ttl = 0): bool
    {
        if ($GLOBALS['mockApcuSimulateFailure'] ?? false) {
            return false;
        }
        return \apcu_store($key, $value, $ttl);
    }
}
