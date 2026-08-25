<?php
/**
 * Local/dev rate-limit relaxation.
 *
 * Deliberately kept OUT of usersc/includes/rate_limits.php: that file is
 * fully regenerated (overwritten, not merged) by the in-app "Rate Limiting
 * Dashboard" every time it's saved, so any code appended there is silently
 * destroyed on the next save. This file is never touched by that dashboard
 * and is safe to keep permanent local-only logic in.
 *
 * Included unconditionally from usersc/includes/loader.php (every request).
 * Forces $rateLimits to build first (normally lazy, built on first
 * RateLimit:: construction), then multiplies every `_max` threshold 100x
 * when US_ENVIRONMENT=development — set that in .env (git-ignored,
 * local-only; NEVER set it in a deployed .env). Default is 'production'
 * (no-op) when unset, so this is inert everywhere except an opted-in local
 * environment.
 */

if (!defined('US_ENVIRONMENT')) {
    define('US_ENVIRONMENT', $_ENV['US_ENVIRONMENT'] ?? getenv('US_ENVIRONMENT') ?: 'production');
}

if (US_ENVIRONMENT !== 'development') {
    return;
}

if (!isset($rateLimits) || !is_array($rateLimits)) {
    require_once $abs_us_root . $us_url_root . 'users/includes/rate_limits.php';
}

if (!isset($rateLimits) || !is_array($rateLimits)) {
    // Framework config failed to populate $rateLimits — nothing to relax.
    return;
}

foreach ($rateLimits as $action => &$limits) {
    foreach ($limits as $key => &$value) {
        // Skip values already at (or past) PHP_INT_MAX — e.g. admin_ajax_*/
        // location_search's deliberately-unlimited ip_max. Multiplying by
        // 100 would overflow to a float and truncate back to 0 (fully
        // blocking, the opposite of "relax"), not silently stay unlimited.
        if (strpos($key, '_max') !== false && $value < PHP_INT_MAX) {
            $value = (int)min($value * 100, PHP_INT_MAX); // Massively increase limits for development
        }
    }
}
unset($limits, $value); // Clean up references
