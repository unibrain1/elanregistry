<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * RateLimiterInterface - Typed contract for rate-limit checking used by application services
 *
 * A deliberately narrow interface exposing exactly the two operations application
 * code needs (check + record), so production classes can type their collaborator
 * as `RateLimiterInterface` rather than depending on the global `checkRateLimit()`
 * / `recordRateLimit()` helper functions directly. This is extracted so unit tests
 * can build small, purpose-built test doubles (e.g. one that always allows, or one
 * that fails after N calls) without needing the real `\RateLimit` class, which
 * opens a live database connection and creates a table in its constructor.
 *
 * @package ElanRegistry
 * @since v2.29.2
 * @see https://github.com/elan-registry/registry/issues/1582
 */
interface RateLimiterInterface
{
    /**
     * Check whether an action is currently allowed for the given user.
     *
     * Does not record the attempt — call `record()` separately once the
     * outcome of the attempt is known.
     *
     * @param string $action Rate-limit action key, as configured in `$rateLimits`
     * @param int|null $userId User ID for per-user limiting, or null for an anonymous caller
     * @return bool True if the action is allowed, false if the caller is currently rate-limited
     */
    public function allow(string $action, ?int $userId): bool;

    /**
     * Record an attempt (successful or failed) for the given action.
     *
     * @param string $action Rate-limit action key, as configured in `$rateLimits`
     * @param bool $success Whether the attempt succeeded
     * @param int|null $userId User ID for per-user limiting, or null for an anonymous caller
     */
    public function record(string $action, bool $success, ?int $userId): void;
}
