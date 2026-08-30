<?php
declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\LogCategories;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test security headers and behavior of error/500.php — the canonical
 * handler for all 4xx/5xx error codes (400/401/403/404/405/408/500/502/504)
 * since issue #1830 consolidated the former dedicated 403.php/404.php pages
 * into it.
 *
 * Verifies that error pages return proper anti-clickjacking headers even if
 * init.php fails to load, and that the logging guard actually resolves and
 * fires (issue #1830: the pre-consolidation 403/404 handlers guarded
 * logger() with class_exists('LogCategories') — a bare, unqualified class
 * name that silently never resolved under the Composer PSR-4 autoloader,
 * so logging silently no-op'd for 47 days undetected because no test ever
 * executed the guarded code path).
 */
#[Group('integration')]
#[Group('security')]
#[Group('error-pages')]
class ErrorPageHeadersTest extends IntegrationTestCase
{
    /** @var list<string> */
    private const DB_ENV_VARS = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];

    /** @var string[] LIKE patterns for logs rows this test wrote, cleaned up in tearDown() */
    private array $logCleanupPatterns = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
        $this->logCleanupPatterns = [];
    }

    /**
     * The behavioral tests below write real rows to the `logs` table via a
     * subprocess (error/500.php calling logger()) — outside
     * IntegrationTestCase's own car/user tracking, so they need their own
     * cleanup here to avoid polluting the table on every test run.
     */
    protected function tearDown(): void
    {
        foreach (self::DB_ENV_VARS as $var) {
            putenv($var);
        }

        if ($this->databaseConnected) {
            foreach ($this->logCleanupPatterns as $pattern) {
                $this->db->query('DELETE FROM logs WHERE lognote LIKE ?', [$pattern]);
            }
        }

        parent::tearDown();
    }

    /**
     * Propagate this test run's DB credentials (loaded from .env.test.local
     * by tests/bootstrap-integration.php) into the process environment so a
     * subprocess spawned via shell_exec() connects to the same dedicated
     * test schema instead of falling back to the project's real .env.
     *
     * Unlike LogDeploymentScriptTest's subprocess (which only requires
     * vendor/autoload.php and connects to the DB directly), error/500.php
     * requires the full users/init.php bootstrap — which hydrates $settings
     * from a DB-backed row, not just raw DB_* credentials. putenv() alone
     * left that hydration broken in testing (settings loaded as an array
     * instead of the expected object), so this subprocess also explicitly
     * re-loads .env.test.local via Dotenv — init.php's own
     * Dotenv::createImmutable() call reads but does not overwrite
     * already-set $_ENV values, so this doesn't conflict with it.
     */
    private function exposeTestDatabaseToSubprocess(): void
    {
        foreach (self::DB_ENV_VARS as $var) {
            $value = $_ENV[$var] ?? getenv($var);
            if ($value !== false && $value !== '') {
                putenv("$var=$value");
            }
        }
    }

    /**
     * Compile-time safety net for the #1830 bug class: assert the fully
     * qualified class actually resolves under the Composer autoloader. If a
     * future change breaks the autoload mapping, this fails loudly instead
     * of the guarded logger() call silently no-op'ing like the original bug.
     */
    public function testLogCategoriesClassResolves(): void
    {
        $this->assertTrue(
            class_exists(LogCategories::class),
            'ElanRegistry\LogCategories must resolve via the Composer PSR-4 autoloader'
        );
    }

    /**
     * Invoke error/500.php in a subprocess with the given REDIRECT_STATUS,
     * simulating an Apache ErrorDocument dispatch, and return its captured
     * stdout. A subprocess is used (rather than an in-process require)
     * because the file unconditionally calls header()/http_response_code(),
     * which would emit PHP warnings and pollute global state if required
     * directly inside the PHPUnit process.
     */
    private function renderErrorPage(int $statusCode, string $requestUri = '/nonexistent-page'): string
    {
        $errorPageFile = dirname(__DIR__, 2) . '/error/500.php';
        $projectRoot = dirname(__DIR__, 2);

        $this->exposeTestDatabaseToSubprocess();

        $script = sprintf(
            'require %s; \Dotenv\Dotenv::createMutable(%s, ".env.test.local")->load(); ' .
            '$_SERVER["REDIRECT_STATUS"] = %d; $_SERVER["REQUEST_URI"] = %s; ' .
            '$_SERVER["DOCUMENT_ROOT"] = %s; chdir(%s); require %s;',
            var_export($projectRoot . '/vendor/autoload.php', true),
            var_export($projectRoot, true),
            $statusCode,
            var_export($requestUri, true),
            var_export($projectRoot, true),
            var_export(dirname($errorPageFile), true),
            var_export($errorPageFile, true)
        );

        $output = shell_exec('php -r ' . escapeshellarg($script) . ' 2>&1');

        return $output !== null ? $output : '';
    }

    #[DataProvider('nonStaticNotFoundPathProvider')]
    public function test404NonStaticPathWritesOnePageNotFoundRow(string $requestUri): void
    {
        $this->logCleanupPatterns[] = "%{$requestUri}%";
        $before = $this->countMatchingLogs(LogCategories::LOG_CATEGORY_PAGE_NOT_FOUND, "%{$requestUri}%");

        $this->renderErrorPage(404, $requestUri);

        $after = $this->countMatchingLogs(LogCategories::LOG_CATEGORY_PAGE_NOT_FOUND, "%{$requestUri}%");

        $this->assertSame(
            $before + 1,
            $after,
            "404 on non-static path {$requestUri} should write exactly one PageNotFound log row"
        );
    }

    /**
     * @return array<string, array<string>>
     */
    public static function nonStaticNotFoundPathProvider(): array
    {
        $uniqueSuffix = uniqid();

        return [
            'non-static path' => ["/nonexistent-page-{$uniqueSuffix}"],
        ];
    }

    #[DataProvider('staticAssetPathProvider')]
    public function test404StaticAssetPathWritesZeroRows(string $requestUri): void
    {
        $before = $this->countMatchingLogs(LogCategories::LOG_CATEGORY_PAGE_NOT_FOUND, "%{$requestUri}%");

        $this->renderErrorPage(404, $requestUri);

        $after = $this->countMatchingLogs(LogCategories::LOG_CATEGORY_PAGE_NOT_FOUND, "%{$requestUri}%");

        $this->assertSame(
            $before,
            $after,
            "404 on static-asset path {$requestUri} should write zero log rows"
        );
    }

    /**
     * @return array<string, array<string>>
     */
    public static function staticAssetPathProvider(): array
    {
        $uniqueSuffix = uniqid();

        return [
            'jpg' => ["/missing-{$uniqueSuffix}.jpg"],
            'css' => ["/missing-{$uniqueSuffix}.css"],
            'js' => ["/missing-{$uniqueSuffix}.js"],
        ];
    }

    public function test403WritesOneAccessDeniedRow(): void
    {
        $requestUri = '/.git/config-' . uniqid();
        $this->logCleanupPatterns[] = "%{$requestUri}%";

        $before = $this->countMatchingLogs(LogCategories::LOG_CATEGORY_ACCESS_DENIED, "%{$requestUri}%");

        $this->renderErrorPage(403, $requestUri);

        $after = $this->countMatchingLogs(LogCategories::LOG_CATEGORY_ACCESS_DENIED, "%{$requestUri}%");

        $this->assertSame(
            $before + 1,
            $after,
            "403 on {$requestUri} should write exactly one AccessDenied log row"
        );
    }

    /**
     * Guards against a future edit silently dropping a status code from
     * either array — both must cover all 9 codes this handler documents
     * supporting.
     */
    public function testAllNineStatusCodesHaveErrorMessageAndLogCategoryEntries(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/error/500.php');
        $this->assertNotFalse($source, 'error/500.php must be readable');

        $expectedCodes = [400, 401, 403, 404, 405, 408, 500, 502, 504];

        foreach ($expectedCodes as $code) {
            $this->assertMatchesRegularExpression(
                "/{$code}\\s*=>\\s*\\[/",
                $source,
                "\$errorMessages should have an entry for status code {$code}"
            );
            $this->assertMatchesRegularExpression(
                "/{$code}\\s*=>\\s*LogCategories::/",
                $source,
                "\$logCategoryMap should have an entry for status code {$code}"
            );
        }
    }

    /**
     * Test that error page files include security header fallbacks
     */
    #[DataProvider('errorPageProvider')]
    public function testErrorPageIncludesSecurityHeaders(string $pageName): void
    {
        $errorPageFile = dirname(__DIR__, 2) . '/error/' . $pageName;

        if (!is_file($errorPageFile)) {
            $this->markTestSkipped("Error page {$pageName} not found");
        }

        $content = file_get_contents($errorPageFile);
        if ($content === false) {
            $this->markTestSkipped("Unable to read {$pageName}");
        }

        $pageContent = (string) $content;

        // Should set X-Frame-Options header
        $this->assertStringContainsString(
            'X-Frame-Options',
            $pageContent,
            "{$pageName} should set X-Frame-Options header"
        );

        // Should set CSP header with frame-ancestors
        $this->assertStringContainsString(
            "Content-Security-Policy",
            $pageContent,
            "{$pageName} should set Content-Security-Policy header"
        );

        $this->assertStringContainsString(
            "frame-ancestors 'self'",
            $pageContent,
            "{$pageName} should include frame-ancestors in CSP"
        );
    }

    /**
     * Test that error pages set headers before init.php load attempt
     *
     * Headers should be set early in the file, before any includes that
     * might fail (like init.php)
     */
    #[DataProvider('errorPageProvider')]
    public function testErrorPageHeadersBeforeInitPhp(string $pageName): void
    {
        $errorPageFile = dirname(__DIR__, 2) . '/error/' . $pageName;

        if (!is_file($errorPageFile)) {
            $this->markTestSkipped("Error page {$pageName} not found");
        }

        $content = file_get_contents($errorPageFile);
        if ($content === false) {
            $this->markTestSkipped("Unable to read {$pageName}");
        }

        $pageContent = (string) $content;

        // Check that headers are in the early section (before line 30 in most cases)
        $lines = explode("\n", $pageContent);

        $hasHeaderCall = false;
        $hasInitCall = false;
        $headerLineNum = PHP_INT_MAX;
        $initLineNum = PHP_INT_MAX;

        foreach ($lines as $lineNum => $line) {
            if (strpos($line, 'header("X-Frame-Options') !== false) {
                $hasHeaderCall = true;
                $headerLineNum = $lineNum;
            }
            if (strpos($line, 'require_once') !== false && strpos($line, 'init.php') !== false) {
                $hasInitCall = true;
                $initLineNum = $lineNum;
            }
        }

        // Verify headers are set (they should be)
        $this->assertTrue(
            $hasHeaderCall,
            "{$pageName} should set security headers with header() call"
        );

        // If init.php is loaded, headers must precede it
        if ($hasInitCall) {
            $this->assertLessThan(
                $initLineNum,
                $headerLineNum,
                "{$pageName} should set X-Frame-Options before loading init.php"
            );
        }
    }

    /**
     * Test that error pages use SAMEORIGIN policy (not DENY)
     *
     * Error pages allow framing within same origin (more user-friendly)
     * while still protecting against cross-origin clickjacking
     */
    #[DataProvider('errorPageProvider')]
    public function testErrorPageUsesSameoriginPolicy(string $pageName): void
    {
        $errorPageFile = dirname(__DIR__, 2) . '/error/' . $pageName;

        if (!is_file($errorPageFile)) {
            $this->markTestSkipped("Error page {$pageName} not found");
        }

        $content = file_get_contents($errorPageFile);
        if ($content === false) {
            $this->markTestSkipped("Unable to read {$pageName}");
        }

        $pageContent = (string) $content;

        // Should use SAMEORIGIN (allows same-origin framing)
        $this->assertStringContainsString(
            'SAMEORIGIN',
            $pageContent,
            "{$pageName} should use SAMEORIGIN policy"
        );
    }

    /**
     * Data provider: error page filenames to test. Only 500.php remains
     * since #1830 consolidated 403.php/404.php into it.
     *
     * @return array<array<string>>
     */
    public static function errorPageProvider(): array
    {
        return [
            ['500.php'],
        ];
    }
}
