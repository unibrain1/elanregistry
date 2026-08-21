<?php

declare(strict_types=1);

require_once __DIR__ . '/_curl_namespace_overrides.php';

use ElanRegistry\Exceptions\LocationServiceException;
use ElanRegistry\LocationService;
use ElanRegistry\RateLimiterInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for LocationService's rate-limiter integration (issue #1582).
 *
 * LocationService::searchLocation() and ::reverseGeocode() now:
 *   1. Check the cache FIRST — a cache hit returns immediately, never
 *      touching the rate limiter at all.
 *   2. On a cache miss, check the rate limiter (shared 'location_search'
 *      action key for both methods) via the private rateLimiterAllows()/
 *      recordRateLimiterAttempt() wrapper methods.
 *   3. A blocked check throws LocationServiceException and records a failed
 *      attempt; a genuine successful upstream call caches the result and
 *      records a successful attempt.
 *
 * FakeRateLimiter (below) is a small in-memory RateLimiterInterface double
 * that records every allow()/record() call, following the same
 * dependency-injection + fake idiom already established in this codebase for
 * DatabaseInterface (see usersc/classes/Database/DbAdapter.php and its test
 * fakes) — LocationService itself is never mocked, only its rate-limiter
 * collaborator.
 *
 * The upstream HTTP call inside the private makeHttpRequest() method is
 * stubbed via _curl_namespace_overrides.php (a namespace-scoped override of
 * the curl_*() functions LocationService calls unqualified), following the
 * same technique _apcu_namespace_overrides.php already established in this
 * directory for APCu. This lets Test 3 exercise the real success path
 * (cache write + record(success: true)) without a live network call.
 *
 * searchLocation() gets the primary blocked/cache-hit/success coverage
 * (Tests 1-3). reverseGeocode() shares the exact same rate-limiter wrapper
 * methods and the same 'location_search' action key, so it does not repeat
 * all three — instead Test 5 targets reverseGeocode()'s one structurally
 * distinct path (its single Nominatim call, vs. searchLocation()'s
 * Photon-then-Nominatim fallback) to confirm a failed upstream call is
 * recorded as success=false rather than miscounted as success=true.
 *
 * Test 4 covers the fail-open contract itself: rateLimiterAllows()/
 * recordRateLimiterAttempt() must swallow a throwing RateLimiterInterface
 * and keep the request working, matching the documented fail-open behavior
 * on LocationService's constructor/method docblocks.
 *
 * @issue 1582
 * @see usersc/classes/LocationService.php
 * @see usersc/classes/RateLimiterInterface.php
 */
#[Group('fast')]
#[Group('unit')]
#[Group('location-service')]
final class LocationServiceRateLimitTest extends TestCase
{
    private string $tempRoot = '';
    private string $cacheDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Isolated cache directory, mirroring LocationServiceCacheTest's setup,
        // so this suite never touches (or is polluted by) the real cache dir.
        $this->tempRoot = sys_get_temp_dir() . '/location_service_ratelimit_test_' . uniqid('', true);
        $this->cacheDir = $this->tempRoot . '/usersc/cache/';
        mkdir($this->cacheDir, 0755, true);

        $GLOBALS['abs_us_root'] = $this->tempRoot . '/';
        $GLOBALS['us_url_root'] = '';
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['abs_us_root'],
            $GLOBALS['us_url_root'],
            $GLOBALS['mockCurlResponse'],
            $GLOBALS['mockCurlFailure'],
            $GLOBALS['mockCurlHttpCode']
        );

        $this->removeDirectory($this->tempRoot);

        parent::tearDown();
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (glob($path . '/*') ?: [] as $entry) {
            is_dir($entry) && !is_link($entry)
                ? $this->removeDirectory($entry)
                : unlink($entry);
        }
        rmdir($path);
    }

    /**
     * A valid, minimal Photon API JSON response for a single result, used to
     * drive the mocked cURL success path in Test 3.
     */
    private function photonSuccessBody(): string
    {
        return json_encode([
            'features' => [
                [
                    'properties' => [
                        'city' => 'Portland',
                        'state' => 'Oregon',
                        'country' => 'United States',
                        'name' => 'Portland',
                    ],
                    'geometry' => [
                        'coordinates' => [-122.6765, 45.5231],
                    ],
                ],
            ],
        ]) ?: '{}';
    }

    // =========================================================================
    // Test 1: blocked — rate limiter denies, no upstream call, exception thrown
    // =========================================================================

    #[Group('fast')]
    public function test_searchLocation_throwsAndRecordsFailure_whenRateLimiterBlocks(): void
    {
        $fake = new FakeRateLimiter(allow: false);
        $service = new LocationService($fake);

        // Guaranteed-unique query so this is always a cache miss.
        $query = 'ratelimit-block-test-' . uniqid('', true);

        // If the blocked path fell through to the upstream call, a real
        // network request would be attempted (and fail in a sandboxed test
        // run) or the curl mock would need to be armed; leaving both curl
        // mock globals unset means any accidental upstream call is very
        // unlikely to silently "succeed" its way to a passing test.
        $this->expectException(LocationServiceException::class);

        try {
            $service->searchLocation($query, 42);
        } finally {
            $this->assertSame(
                [['action' => 'location_search', 'success' => false, 'userId' => 42]],
                $fake->recordCalls,
                'record() must be called exactly once, with success=false, when the rate limiter blocks.'
            );
            $this->assertSame(
                [['action' => 'location_search', 'userId' => 42]],
                $fake->allowCalls,
                'allow() must be called exactly once for a cache-miss query.'
            );
            // No cache entry should exist for a successful result — proves the
            // blocked path never reached the upstream call / setCache().
            $cacheFile = $this->cacheDir . md5('location_search_' . md5($query . '_8')) . '.cache';
            $this->assertFileDoesNotExist(
                $cacheFile,
                'No cache entry should be written when the rate limiter blocks the request.'
            );
        }
    }

    // =========================================================================
    // Test 2: cache hit — rate limiter never consulted, even if it would block
    // =========================================================================

    #[Group('fast')]
    public function test_searchLocation_returnsCachedResult_andNeverCallsRateLimiter_onCacheHit(): void
    {
        // Seed the cache via a real successful call with allow() = true and a
        // mocked successful upstream response.
        $query = 'ratelimit-cachehit-test-' . uniqid('', true);
        $GLOBALS['mockCurlResponse'] = $this->photonSuccessBody();
        $GLOBALS['mockCurlHttpCode'] = 200;

        $seedFake = new FakeRateLimiter(allow: true);
        $seedService = new LocationService($seedFake);
        $seeded = $seedService->searchLocation($query, 7);

        $this->assertNotEmpty($seeded, 'Precondition: seeding call must return a non-empty result to populate the cache.');
        $this->assertSame(
            [['action' => 'location_search', 'success' => true, 'userId' => 7]],
            $seedFake->recordCalls,
            'Precondition: the seeding call must have recorded a successful attempt.'
        );

        // Second call: fresh fake whose allow() always returns false. If the
        // cache hit path incorrectly consulted the rate limiter, this call
        // would throw LocationServiceException instead of returning the
        // cached result.
        $blockingFake = new FakeRateLimiter(allow: false);
        $service = new LocationService($blockingFake);
        $result = $service->searchLocation($query, 7);

        $this->assertSame($seeded, $result, 'A cache hit must return the exact cached result.');
        $this->assertSame(
            [],
            $blockingFake->allowCalls,
            'allow() must never be called on a cache hit — the cache check happens before rate limiting.'
        );
        $this->assertSame(
            [],
            $blockingFake->recordCalls,
            'record() must never be called on a cache hit.'
        );
    }

    // =========================================================================
    // Test 3: success — rate limiter allows, upstream succeeds, records once
    // =========================================================================

    #[Group('fast')]
    public function test_searchLocation_recordsSuccessOnce_whenAllowedAndUpstreamSucceeds(): void
    {
        $query = 'ratelimit-success-test-' . uniqid('', true);
        $GLOBALS['mockCurlResponse'] = $this->photonSuccessBody();
        $GLOBALS['mockCurlHttpCode'] = 200;

        $fake = new FakeRateLimiter(allow: true);
        $service = new LocationService($fake);

        $result = $service->searchLocation($query, 99);

        $this->assertNotEmpty($result, 'A successful upstream call must return non-empty results.');
        $this->assertSame(
            [['action' => 'location_search', 'userId' => 99]],
            $fake->allowCalls,
            'allow() must be called exactly once.'
        );
        $this->assertSame(
            [['action' => 'location_search', 'success' => true, 'userId' => 99]],
            $fake->recordCalls,
            'record() must be called exactly once, with success=true and the correct userId, on a genuine successful upstream call.'
        );
    }

    // =========================================================================
    // Test 4: fail-open — a throwing rate limiter must not break the request
    // =========================================================================

    #[Group('fast')]
    public function test_searchLocation_failsOpen_whenRateLimiterAllowThrows(): void
    {
        $query = 'ratelimit-failopen-allow-test-' . uniqid('', true);
        $GLOBALS['mockCurlResponse'] = $this->photonSuccessBody();
        $GLOBALS['mockCurlHttpCode'] = 200;

        $fake = new FakeRateLimiter(allow: true, throwOnAllow: true);
        $service = new LocationService($fake);

        // Must not throw, and must not propagate the rate limiter's failure —
        // rateLimiterAllows() is documented to catch \Throwable and return
        // true, matching this class's existing fail-open behavior on cache
        // errors.
        $result = $service->searchLocation($query, 5);

        $this->assertNotEmpty($result, 'A throwing allow() must fail open — the request should still succeed.');
    }

    #[Group('fast')]
    public function test_searchLocation_failsOpen_whenRateLimiterRecordThrows(): void
    {
        $query = 'ratelimit-failopen-record-test-' . uniqid('', true);
        $GLOBALS['mockCurlResponse'] = $this->photonSuccessBody();
        $GLOBALS['mockCurlHttpCode'] = 200;

        $fake = new FakeRateLimiter(allow: true, throwOnRecord: true);
        $service = new LocationService($fake);

        // record() throwing must not prevent the already-successful result
        // from being returned to the caller — recordRateLimiterAttempt()
        // swallows the failure after logging it.
        $result = $service->searchLocation($query, 5);

        $this->assertNotEmpty($result, 'A throwing record() must not break an otherwise-successful request.');
    }

    // =========================================================================
    // Test 5: reverseGeocode() — a failed upstream call is not miscounted as
    // a success (the one path structurally different from searchLocation())
    // =========================================================================

    #[Group('fast')]
    public function test_reverseGeocode_recordsFailure_whenNominatimCallFails(): void
    {
        // Distinct coordinates per test run so this is always a cache miss.
        $lat = 45.0 + (random_int(1, 1000) / 100000);
        $lon = -122.0 + (random_int(1, 1000) / 100000);
        $GLOBALS['mockCurlFailure'] = true;

        $fake = new FakeRateLimiter(allow: true);
        $service = new LocationService($fake);

        $this->expectException(LocationServiceException::class);

        try {
            $service->reverseGeocode($lat, $lon, 11);
        } finally {
            $this->assertSame(
                [['action' => 'location_search', 'userId' => 11]],
                $fake->allowCalls,
                'allow() must be called exactly once.'
            );
            $this->assertSame(
                [['action' => 'location_search', 'success' => false, 'userId' => 11]],
                $fake->recordCalls,
                'A failed Nominatim call must record success=false, never success=true — '
                    . 'confirms reverseGeocode()\'s single-provider failure path does not '
                    . 'share searchLocation()\'s Photon-then-Nominatim fallback shape in a '
                    . 'way that could miscount a failure as a success.'
            );
        }
    }
}

/**
 * In-memory fake RateLimiterInterface for tests. Records every allow()/
 * record() call (action, userId — and success, for record()) and lets the
 * test control what allow() returns via the constructor.
 *
 * Kept in the same file as its only consumer, mirroring how small,
 * single-purpose test doubles are typically colocated with the test that
 * uses them elsewhere in this suite (e.g. LocationServiceCacheTest's private
 * helper methods).
 */
final class FakeRateLimiter implements RateLimiterInterface
{
    /** @var list<array{action: string, userId: int|null}> */
    public array $allowCalls = [];

    /** @var list<array{action: string, success: bool, userId: int|null}> */
    public array $recordCalls = [];

    public function __construct(
        private readonly bool $allow,
        private readonly bool $throwOnAllow = false,
        private readonly bool $throwOnRecord = false,
    ) {
    }

    public function allow(string $action, ?int $userId): bool
    {
        if ($this->throwOnAllow) {
            throw new \RuntimeException('Simulated rate limiter failure (allow)');
        }
        $this->allowCalls[] = ['action' => $action, 'userId' => $userId];
        return $this->allow;
    }

    public function record(string $action, bool $success, ?int $userId): void
    {
        if ($this->throwOnRecord) {
            throw new \RuntimeException('Simulated rate limiter failure (record)');
        }
        $this->recordCalls[] = ['action' => $action, 'success' => $success, 'userId' => $userId];
    }
}
