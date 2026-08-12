<?php

declare(strict_types=1);

use ElanRegistry\LocationService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for LocationService::getUserAgent()
 *
 * Verifies the VERSION-file-read behaviour added in v2.25.4 (#1070):
 * the method reads the VERSION file on first call, caches the result
 * for the lifetime of the process, and falls back to 'unknown' when
 * the file is absent or empty.
 *
 * getUserAgent() is private + static, so it is exercised via Reflection.
 * The fallback logic itself lives in the path-parameterized
 * resolveVersion() helper (#1602), which is also invoked directly via
 * Reflection so tests can drive the absent/empty-file branches with real
 * temporary files instead of seeding $cachedVersion — mirrors the
 * path-parameterized extraction technique used by
 * AssetVersionResolver::resolve() (#1598); the fallback semantics differ
 * ('unknown' vs. 'dev', no allow-list regex).
 * $cachedVersion is also reset via Reflection between tests to prevent
 * static-state pollution.
 *
 * @issue 1070
 * @issue 1602
 * @link https://github.com/unibrain1/elanregistry/issues/1070
 * @see usersc/classes/LocationService.php
 */
#[Group('fast')]
#[Group('unit')]
#[Group('location-service')]
final class LocationServiceUserAgentTest extends TestCase
{
    private \ReflectionClass $ref;
    private \ReflectionMethod $getUserAgent;
    private \ReflectionMethod $resolveVersion;
    private \ReflectionProperty $cachedVersion;
    private LocationService $service;

    protected function setUp(): void
    {
        $this->service        = new LocationService();
        $this->ref            = new \ReflectionClass(LocationService::class);
        $this->getUserAgent   = $this->ref->getMethod('getUserAgent');
        $this->resolveVersion = $this->ref->getMethod('resolveVersion');
        $this->cachedVersion  = $this->ref->getProperty('cachedVersion');
        // Reset static cache before every test
        $this->cachedVersion->setValue(null, null);
    }

    protected function tearDown(): void
    {
        // Reset static cache after every test to avoid polluting subsequent tests
        $this->cachedVersion->setValue(null, null);
    }

    private function invokeGetUserAgent(): string
    {
        return (string) $this->getUserAgent->invoke($this->service);
    }

    /**
     * Drives resolveVersion() with $path, asserts it falls back to 'unknown',
     * seeds the cache with that result, and asserts the formatted
     * User-Agent string.
     */
    private function assertResolveVersionFallsBackToUnknown(string $path): void
    {
        $resolved = $this->resolveVersion->invoke(null, $path);
        $this->assertSame('unknown', $resolved);

        $this->cachedVersion->setValue(null, $resolved);
        $ua = $this->invokeGetUserAgent();

        $this->assertSame('ElanRegistry/unknown (https://elanregistry.org)', $ua);
    }

    public function testUserAgentContainsVersionFromFile(): void
    {
        $versionFile = dirname(__DIR__, 3) . '/VERSION';
        if (!is_readable($versionFile) || trim((string) file_get_contents($versionFile)) === '') {
            $this->markTestSkipped('VERSION file not present or empty — skipping version-from-file test.');
        }

        $expected = trim((string) file_get_contents($versionFile));
        $ua = $this->invokeGetUserAgent();

        $this->assertStringContainsString($expected, $ua);
        $this->assertStringStartsWith('ElanRegistry/', $ua);
        $this->assertStringContainsString('(https://elanregistry.org)', $ua);
    }

    public function testResolveVersionReturnsTrimmedContentForRealFile(): void
    {
        $path = sys_get_temp_dir() . '/LocationServiceUserAgentTest_valid_' . uniqid() . '_VERSION';
        $written = file_put_contents($path, " v9.9.9 \n");
        $this->assertNotFalse($written, 'Precondition: failed to write temp VERSION file at ' . $path);

        try {
            $resolved = $this->resolveVersion->invoke(null, $path);
        } finally {
            unlink($path);
        }

        $this->assertSame('v9.9.9', $resolved);
    }

    public function testUserAgentFallsBackToUnknownWhenVersionFileIsAbsent(): void
    {
        // Construct a path guaranteed not to exist and drive resolveVersion()
        // with it directly — exercises the real is_readable()/fallback logic.
        $absentPath = sys_get_temp_dir() . '/LocationServiceUserAgentTest_absent_' . uniqid() . '_VERSION';
        $this->assertFileDoesNotExist($absentPath, 'Precondition: temp path must not exist');

        $this->assertResolveVersionFallsBackToUnknown($absentPath);
    }

    public function testStaticCachePreventsDuplicateFileReads(): void
    {
        // Call twice; second call must return the same result (cache is used)
        $first  = $this->invokeGetUserAgent();
        $second = $this->invokeGetUserAgent();

        $this->assertSame($first, $second);
        // After the first call the static property must be a non-null string
        $cached = $this->cachedVersion->getValue(null);
        $this->assertIsString($cached);
    }

    public function testEmptyVersionFallsBackToUnknown(): void
    {
        // Write a real empty VERSION file and drive resolveVersion() with it
        // directly — exercises the real trim()/!== '' fallback logic.
        $path = sys_get_temp_dir() . '/LocationServiceUserAgentTest_empty_' . uniqid() . '_VERSION';
        $written = file_put_contents($path, '');
        $this->assertNotFalse($written, 'Precondition: failed to write empty temp VERSION file at ' . $path);

        try {
            $this->assertResolveVersionFallsBackToUnknown($path);
        } finally {
            unlink($path);
        }
    }

    public function testWhitespaceOnlyVersionFallsBackToUnknown(): void
    {
        // Whitespace-only content is non-empty raw bytes that trim() must
        // reduce to '' before the !== '' fallback check fires — distinct
        // from testEmptyVersionFallsBackToUnknown(), where trim('') is a no-op.
        $path = sys_get_temp_dir() . '/LocationServiceUserAgentTest_whitespace_' . uniqid() . '_VERSION';
        $written = file_put_contents($path, "  \n\t  \n");
        $this->assertNotFalse($written, 'Precondition: failed to write whitespace-only temp VERSION file at ' . $path);

        try {
            $this->assertResolveVersionFallsBackToUnknown($path);
        } finally {
            unlink($path);
        }
    }

    /**
     * Regression guard for #1119: searchPhoton() must pass getUserAgent() to
     * makeHttpRequest(). Because makeHttpRequest() is private the call cannot
     * be intercepted at runtime without modifying source, so this test
     * verifies the call-site argument in the source text — a structural
     * assertion that fails immediately if the argument is removed.
     */
    public function testSearchPhotonPassesUserAgentToMakeHttpRequest(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__, 3) . '/usersc/classes/LocationService.php'
        );

        $this->assertSame(
            3,
            substr_count($source, 'makeHttpRequest($url, self::getUserAgent())'),
            'All three makeHttpRequest() call sites must pass self::getUserAgent() — regression guard for #1119'
        );
    }
}
