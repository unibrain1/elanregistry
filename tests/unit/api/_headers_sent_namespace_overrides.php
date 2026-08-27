<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * Namespace-scoped override of headers_sent(), used only by ApiResponseTest's
 * headers-already-sent branch coverage (issues #1516/#1616).
 *
 * PHP resolves unqualified function calls against the CURRENT namespace
 * first, falling back to the global namespace only if nothing is defined
 * here. usersc/classes/ApiResponse.php is also in the ElanRegistry namespace
 * and calls headers_sent() unqualified, so this override takes precedence
 * there — without affecting any other file, since PHP namespace resolution
 * is per-call-site, not global.
 *
 * Delegates transparently to the real global headers_sent() unless
 * $GLOBALS['mockHeadersSent'] is explicitly set, so this has zero effect on
 * any other test in the suite.
 */
if (!\function_exists(__NAMESPACE__ . '\\headers_sent')) {
    function headers_sent(?string &$file = null, ?int &$line = null): bool
    {
        if ($GLOBALS['mockHeadersSent'] ?? false) {
            $file = $GLOBALS['mockHeadersSentFile'] ?? 'mock-file.php';
            $line = $GLOBALS['mockHeadersSentLine'] ?? 42;
            return true;
        }
        return \headers_sent($file, $line);
    }
}
