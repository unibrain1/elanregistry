<?php

declare(strict_types=1);

namespace ElanRegistry\RateLimit;

use ElanRegistry\RateLimiterInterface;

/**
 * RateLimiterAdapter - Thin adapter exposing the global rate-limit helpers as a RateLimiterInterface
 *
 * Delegates to the global `checkRateLimit()` / `recordRateLimit()` helper functions
 * (`users/helpers/rate_limit_helpers.php`), which in turn wrap the real `\RateLimit`
 * class. Every method is a 1:1 delegation with no logic or translation — the
 * wrapped helpers already accept a nullable `$userId` for anonymous callers.
 *
 * @package ElanRegistry\RateLimit
 * @since v2.29.2
 * @see https://github.com/elan-registry/registry/issues/1582
 */
final class RateLimiterAdapter implements RateLimiterInterface
{
    public function allow(string $action, ?int $userId): bool
    {
        return checkRateLimit($action, $userId);
    }

    public function record(string $action, bool $success, ?int $userId): void
    {
        recordRateLimit($action, $success, $userId);
    }
}
