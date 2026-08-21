<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test proving the 'location_search' rate limit (#1582) buckets
 * anonymous callers by IP, not globally — i.e. two distinct anonymous
 * visitors do not share a single 'location_search' rate-limit bucket, and one
 * visitor exhausting their budget does not affect another (AC #1/#2 of the
 * issue).
 *
 * This exercises the shared RateLimit engine directly via checkRateLimit()/
 * recordRateLimit() (the same global helpers RateLimiterAdapter delegates
 * to) rather than going through LocationService — the property under test
 * here is the engine's per-IP bucket isolation, not LocationService's own
 * cache-then-rate-limit logic (see tests/unit/location/LocationServiceRateLimitTest.php
 * for that).
 *
 * There is no existing IP-faking helper on IntegrationTestCase (compare
 * loginAsTestUser()/restoreGlobalUser(), which snapshot/restore
 * $GLOBALS['user']), so each test method here manually saves and restores
 * $_SERVER['REMOTE_ADDR'] in a try/finally block, mirroring that same
 * save-once/restore-in-tearDown shape at the single-test-method scope.
 *
 * Fake IPs are drawn from TEST-NET-3 (203.0.113.0/24, RFC 5737) — reserved
 * for documentation/testing and guaranteed never to appear in real traffic —
 * with a random last octet per call so concurrent test runs don't collide.
 *
 * Caching note (mirrors tests/integration/GetBaseUrlTest.php): RateLimit's
 * getRealIP() reads REMOTE_ADDR through Server::get(), which memoizes every
 * key it resolves in a private static $cache for the lifetime of the PHP
 * process. Without clearing that cache, a later change to
 * $_SERVER['REMOTE_ADDR'] in the same process is invisible to Server::get()
 * — the rate limiter would keep using whichever IP was first resolved
 * (typically during bootstrap), silently bucketing every "distinct" IP in
 * this test under one real identifier. resetServerCache() clears it via
 * reflection before REMOTE_ADDR is changed, matching GetBaseUrlTest's setUp()
 * pattern.
 */
#[Group('database')]
final class LocationRateLimitIsolationTest extends IntegrationTestCase
{
    private const ACTION = 'location_search';

    /** Matches usersc/includes/rate_limits.php's location_search total_max. */
    private const TOTAL_MAX = 10;

    private function fakeTestNet3Ip(): string
    {
        return '203.0.113.' . random_int(1, 254);
    }

    /**
     * Clear Server::$cache so a subsequent Server::get('REMOTE_ADDR', ...)
     * call (made indirectly via RateLimit::getRealIP()) observes whatever
     * $_SERVER['REMOTE_ADDR'] is set to at call time, rather than a value
     * memoized earlier in this process. See the class docblock's Caching note.
     */
    private function resetServerCache(): void
    {
        if (!class_exists(\Server::class)) {
            return;
        }
        $reflection = new \ReflectionClass(\Server::class);
        if ($reflection->hasProperty('cache')) {
            $reflection->getProperty('cache')->setValue(null, []);
        }
    }

    public function testSecondIpIsUnaffectedByFirstIpExhaustingItsBudget(): void
    {
        $this->requireDatabase();

        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;

        try {
            $ipOne = $this->fakeTestNet3Ip();
            $this->resetServerCache();
            $_SERVER['REMOTE_ADDR'] = $ipOne;

            for ($i = 0; $i < self::TOTAL_MAX; $i++) {
                $this->assertTrue(
                    checkRateLimit(self::ACTION, null),
                    'Attempt ' . ($i + 1) . ' of ' . self::TOTAL_MAX . ' for IP #1 should be allowed (within total_max).'
                );
                recordRateLimit(self::ACTION, true, null);
            }

            $this->assertFalse(
                checkRateLimit(self::ACTION, null),
                'Attempt ' . (self::TOTAL_MAX + 1) . ' for IP #1 must be blocked — total_max exhausted (AC: budget enforcement).'
            );

            // Switch to a second, distinct fake IP.
            $ipTwo = $this->fakeTestNet3Ip();
            // Guard against the astronomically unlikely random collision,
            // which would silently turn this into a same-IP (non-)test.
            while ($ipTwo === $ipOne) {
                $ipTwo = $this->fakeTestNet3Ip();
            }
            $this->resetServerCache();
            $_SERVER['REMOTE_ADDR'] = $ipTwo;

            $this->assertTrue(
                checkRateLimit(self::ACTION, null),
                'A distinct anonymous IP must have its own independent location_search bucket — '
                    . "it must not be blocked by IP #1 ({$ipOne}) exhausting its own budget (AC #1/#2, #1582)."
            );
        } finally {
            if ($originalRemoteAddr === null) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
            }
            // Clear the memoized value so later tests don't observe this
            // test's fake IP through Server::get('REMOTE_ADDR', ...).
            $this->resetServerCache();
        }
    }
}
