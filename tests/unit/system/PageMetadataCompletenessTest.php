<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Completeness gate for Issues #1432 and #1484.
 *
 * Per the ElanRegistry page-title/description convention (see
 * usersc/plugins/ai_prompts/custom_prompts/elanregistry_overrides.md.php,
 * section 6), a page must set BOTH `$pageTitle` and `$pageDescription`
 * BEFORE its `require_once '.../init.php'` call for the override to take
 * effect. The loader only checks `isset($pageTitle)` once, at init time —
 * setting the variables afterward is silently too late and the page falls
 * back to site-wide defaults (this is the exact bug class behind #1430,
 * which left three admin pages broken).
 *
 * This test is a pure static-text scan: it reads each file's raw source
 * via file_get_contents() and never requires/executes it, so it needs no
 * database and no bootstrapped UserSpice environment.
 *
 * Dynamic discovery (#1484): rather than a hardcoded page list that has no
 * awareness of pages outside it, the set of pages requiring the convention
 * (`pagesRequiringMetadata()`) is derived by scanning every project-owned
 * location that can plausibly render a page — see PAGE_SCAN_DIRECTORIES and
 * PAGE_SCAN_FILES, which mirror phpstan.neon's project-owned path list — for
 * every file that actually calls `securePage()` (excluding action handlers,
 * fix/maintenance scripts, and API endpoints — see NON_PAGE_DIRECTORIES —
 * none of which render a page for a human), then subtracting the checked-in
 * EXPECTED_INCOMPLETE_PAGES exemption list. This means:
 *
 *  - A brand-new page added WITHOUT the convention is automatically picked
 *    up by testDiscoveredIncompletePagesMatchExpectedSnapshot() as an
 *    unexpected gap — the test fails until the author either adds the
 *    convention or consciously adds the file to the exemption list (a
 *    visible, reviewable decision in the PR diff).
 *  - A brand-new page added WITH the convention needs no exemption-list
 *    change at all — it's automatically picked up by
 *    pagesRequiringMetadata()/pagesProvider() and covered by every test
 *    below, including the before-init.php timing check that catches the
 *    exact #1430 bug class on day one.
 *  - An exemption-list entry that stops being incomplete (someone added
 *    the convention but forgot to remove it from the list) also fails the
 *    snapshot test — the list can't silently drift stale.
 */
#[Group('system')]
#[Group('page-metadata')]
class PageMetadataCompletenessTest extends TestCase
{
    /**
     * Directory prefixes (relative to project root) excluded from page
     * discovery even though files under them may call securePage() —
     * form/action handlers, backend operation scripts, one-time/maintenance
     * fix scripts, and AJAX endpoints, none of which render a page for a
     * human.
     *
     * @var list<string>
     */
    private const NON_PAGE_DIRECTORIES = [
        'app/admin/includes/',
        'app/admin/scripts/',
        'app/api/',
    ];

    /**
     * Directories (relative to project root) recursively scanned for page files.
     * Deliberately narrower than "everything phpstan.neon scans" — usersc/ holds
     * mostly non-page content (classes, includes, plugin internals, templates,
     * views, widgets) that would need its own exclusion logic to scan safely, so
     * individual project-owned page files that live directly in usersc/ or the
     * project root are listed explicitly in PAGE_SCAN_FILES instead.
     *
     * @var list<string>
     */
    private const PAGE_SCAN_DIRECTORIES = ['app', 'docs', 'error'];

    /**
     * Individual project-owned files outside PAGE_SCAN_DIRECTORIES that may
     * render a page — mirrors the page-plausible subset of phpstan.neon's
     * project-owned path list. Found via #1493 PR review: the original #1484
     * scan covered only app/ and docs/, silently missing index.php (site
     * homepage) and two usersc/ pages, undercutting the whole point of this
     * gate (closing exactly this class of blind spot).
     *
     * @var list<string>
     */
    private const PAGE_SCAN_FILES = [
        'index.php',
        'usersc/account.php',
        'usersc/join.php',
        'usersc/login.php',
        'usersc/user_settings.php',
    ];

    /**
     * Checked-in snapshot of pages that call securePage() but do not yet
     * set $pageTitle. Verified against the live tree at the time #1484 was
     * implemented. Migrating one of these to the convention is tracked
     * opportunistically (touch a page, add the convention) — remove its
     * entry here when that happens, or testDiscoveredIncompletePagesMatchExpectedSnapshot()
     * will fail.
     *
     * @var list<string>
     */
    private const EXPECTED_INCOMPLETE_PAGES = [
        'app/admin/verify/index.php',
        'app/admin/verify/send_email.php',
        'app/admin/verify/verify_car.php',
        'app/owner/cars/edit.php',
        'app/owner/contact/index.php',
        'app/owner/contact/owner.php',
        'app/owner/privacy.php',
        'docs/guides/car-transfer-faq.php',
        'docs/pdf-viewer.php',
        'docs/stories/brian_walton/index.php',
        'docs/stories/SGO_2F/index.php',
        'docs/stories/type26register.php',
        'index.php',
        'usersc/account.php',
        'usersc/user_settings.php',
    ];

    private string $rootDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = self::projectRootDir();
    }

    /**
     * Asserts a page requiring the convention (see class docblock) sets $pageTitle somewhere in its source.
     */
    #[DataProvider('pagesProvider')]
    public function testPageHasPageTitleAssignment(string $relativePath): void
    {
        $filePath = $this->rootDir . '/' . $relativePath;
        // A hard failure here (not markTestSkipped) is deliberate: if one of these
        // pages is ever moved or renamed without updating this list, the
        // completeness gate must go red, not silently green.
        $this->assertFileExists($filePath, "$relativePath must exist (Issue #1432)");

        $this->assertTrue(
            self::hasPageTitleAssignment($filePath),
            "$relativePath must set \$pageTitle (Issue #1432)"
        );
    }

    /**
     * Asserts a page requiring the convention (see class docblock) sets $pageDescription somewhere in its source.
     */
    #[DataProvider('pagesProvider')]
    public function testPageHasPageDescriptionAssignment(string $relativePath): void
    {
        $filePath = $this->rootDir . '/' . $relativePath;
        $this->assertFileExists($filePath, "$relativePath must exist (Issue #1432)");

        $this->assertTrue(
            self::hasPageDescriptionAssignment($filePath),
            "$relativePath must set \$pageDescription (Issue #1432)"
        );
    }

    /**
     * Critical timing check — see class docblock for the full rationale.
     * $pageTitle strictly requires this ordering (loader.php's isset() check
     * runs once, at init time). $pageDescription is only read later, when
     * head_tags.php renders — but the convention documented in
     * elanregistry_overrides.md.php section 6 requires both variables to be
     * assigned together, before init.php, for consistency across pages
     * (matching how every page in this convention already writes them
     * adjacently), so this test enforces the same ordering for both.
     */
    #[DataProvider('pagesProvider')]
    public function testPageTitleAssignmentPrecedesInitRequire(string $relativePath): void
    {
        $this->assertVariableAssignmentPrecedesInitRequire($relativePath, 'pageTitle');
    }

    /**
     * Same timing rationale as testPageTitleAssignmentPrecedesInitRequire() above.
     */
    #[DataProvider('pagesProvider')]
    public function testPageDescriptionAssignmentPrecedesInitRequire(string $relativePath): void
    {
        $this->assertVariableAssignmentPrecedesInitRequire($relativePath, 'pageDescription');
    }

    /**
     * The regression gate itself (#1484). Discovers every page file, computes
     * which ones currently lack $pageTitle, and asserts that set exactly
     * matches EXPECTED_INCOMPLETE_PAGES — see class docblock for what each
     * direction of mismatch means and how to fix it.
     */
    public function testDiscoveredIncompletePagesMatchExpectedSnapshot(): void
    {
        $actualIncomplete = [];
        foreach (self::discoverPageFiles($this->rootDir) as $relativePath) {
            if (!self::hasPageTitleAssignment($this->rootDir . '/' . $relativePath)) {
                $actualIncomplete[] = $relativePath;
            }
        }
        sort($actualIncomplete);

        $expectedIncomplete = self::EXPECTED_INCOMPLETE_PAGES;
        sort($expectedIncomplete);

        $this->assertSame(
            $expectedIncomplete,
            $actualIncomplete,
            'Discovered pages lacking $pageTitle no longer match EXPECTED_INCOMPLETE_PAGES (Issue #1484). '
            . 'If this list GREW: a new (or newly-discovered) page is missing the $pageTitle/$pageDescription '
            . 'convention — either add it (see elanregistry_overrides.md.php section 6) or add the file to '
            . 'EXPECTED_INCOMPLETE_PAGES as a conscious decision. '
            . 'If this list SHRANK: a page that used to lack $pageTitle now has it — remove its entry from '
            . 'EXPECTED_INCOMPLETE_PAGES so it stays covered by the other tests in this class.'
        );
    }

    private function assertVariableAssignmentPrecedesInitRequire(string $relativePath, string $variableName): void
    {
        $filePath = $this->rootDir . '/' . $relativePath;
        $this->assertFileExists($filePath, "$relativePath must exist (Issue #1432)");

        $content = (string)file_get_contents($filePath);

        $variableMatches = [];
        preg_match('/\$' . $variableName . '\s*=/', $content, $variableMatches, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty(
            $variableMatches,
            "$relativePath must contain a \$$variableName assignment to check its position (Issue #1432)"
        );

        // Anchor to the require_once statement itself, not a bare 'init.php' substring —
        // a docblock or comment mentioning "init.php" earlier in the file would otherwise
        // give a false-negative position and fail this test on correct code.
        $initMatches = [];
        preg_match('/require_once[^\n]*init\.php/', $content, $initMatches, PREG_OFFSET_CAPTURE);
        $this->assertNotEmpty(
            $initMatches,
            "$relativePath must require init.php so the \$$variableName timing can be verified (Issue #1432)"
        );

        $this->assertLessThan(
            $initMatches[0][1],
            $variableMatches[0][1],
            "$relativePath must assign \$$variableName BEFORE requiring init.php, per the convention in " .
            'elanregistry_overrides.md.php section 6 (Issue #1372/#1432)'
        );
    }

    /**
     * @return array<string, array{0: string}> Pages requiring the convention, keyed by path.
     */
    public static function pagesProvider(): array
    {
        $data = [];
        foreach (self::pagesRequiringMetadata(self::projectRootDir()) as $file) {
            $data[$file] = [$file];
        }
        return $data;
    }

    /**
     * Project root, shared by setUp() (instance context) and pagesProvider()
     * (static context, since PHPUnit invokes data providers before the test
     * instance exists).
     */
    private static function projectRootDir(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return list<string> Discovered page files minus EXPECTED_INCOMPLETE_PAGES —
     *                       i.e. pages that must currently satisfy the convention.
     */
    private static function pagesRequiringMetadata(string $rootDir): array
    {
        return array_values(array_diff(
            self::discoverPageFiles($rootDir),
            self::EXPECTED_INCOMPLETE_PAGES
        ));
    }

    private static function hasPageTitleAssignment(string $filePath): bool
    {
        $content = (string)file_get_contents($filePath);
        return preg_match('/\$pageTitle\s*=/', $content) === 1;
    }

    private static function hasPageDescriptionAssignment(string $filePath): bool
    {
        $content = (string)file_get_contents($filePath);
        return preg_match('/\$pageDescription\s*=/', $content) === 1;
    }

    /**
     * Scans PAGE_SCAN_DIRECTORIES (recursively) and PAGE_SCAN_FILES (individually)
     * for every .php file that actually calls securePage() (the precise
     * `if (!securePage(` call pattern, not a bare substring match — defends
     * against a file that merely *mentions* "securePage()" in a comment or
     * docblock being misclassified as a page; app/admin/includes/fix-script-core.php
     * does exactly this today, though it's also excluded via NON_PAGE_DIRECTORIES
     * regardless, so the precise pattern is a second, independent safeguard
     * rather than the only one), excluding anything under NON_PAGE_DIRECTORIES.
     *
     * @return list<string> Sorted paths relative to $rootDir
     */
    private static function discoverPageFiles(string $rootDir): array
    {
        $files = [];

        foreach (self::PAGE_SCAN_DIRECTORIES as $topLevelDir) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootDir . '/' . $topLevelDir, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $fileInfo) {
                if ($fileInfo->getExtension() !== 'php') {
                    continue;
                }

                $relativePath = substr($fileInfo->getPathname(), strlen($rootDir) + 1);
                self::addIfPage($files, $rootDir, $relativePath);
            }
        }

        foreach (self::PAGE_SCAN_FILES as $relativePath) {
            self::addIfPage($files, $rootDir, $relativePath);
        }

        sort($files);
        return $files;
    }

    /**
     * @param list<string> $files Appended to by reference when $relativePath is a page.
     */
    private static function addIfPage(array &$files, string $rootDir, string $relativePath): void
    {
        foreach (self::NON_PAGE_DIRECTORIES as $excludedPrefix) {
            if (str_starts_with($relativePath, $excludedPrefix)) {
                return;
            }
        }

        $content = (string)file_get_contents($rootDir . '/' . $relativePath);
        if (preg_match('/if\s*\(\s*!\s*securePage\(/', $content) === 1) {
            $files[] = $relativePath;
        }
    }
}
