<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Completeness gate for Issue #1432.
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
 * database and no bootstrapped UserSpice environment. It covers the 11
 * pages added under #1432 plus the original #1372 page and the 3 admin
 * pages fixed in #1430, so a future regression on any of these 15 files
 * is caught immediately.
 */
#[Group('system')]
#[Group('page-metadata')]
class PageMetadataCompletenessTest extends TestCase
{
    private const PAGES_REQUIRING_METADATA = [
        'docs/reference/paint-colors.php',
        'docs/reference/index.php',
        'docs/reference/identification-guide.php',
        'docs/reference/workshop.php',
        'docs/reference/chassis-validation.php',
        'docs/reference/technical-articles.php',
        'docs/index.php',
        'docs/car-stories.php',
        'docs/guides/index.php',
        'app/owner/cars/index.php',
        'app/owner/cars/factory.php',
        'app/owner/reports/statistics.php',
        'app/admin/index.php',
        'app/admin/maintenance.php',
        'app/admin/design-system.php',
    ];

    private string $rootDir = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->rootDir = dirname(__DIR__, 3);
    }

    #[DataProvider('pagesProvider')]
    public function testPageHasPageTitleAssignment(string $relativePath): void
    {
        $filePath = $this->rootDir . '/' . $relativePath;
        // A hard failure here (not markTestSkipped) is deliberate: if one of these
        // pages is ever moved or renamed without updating this list, the
        // completeness gate must go red, not silently green.
        $this->assertFileExists($filePath, "$relativePath must exist (Issue #1432)");

        $content = (string)file_get_contents($filePath);

        $this->assertSame(
            1,
            preg_match('/\$pageTitle\s*=/', $content),
            "$relativePath must set \$pageTitle (Issue #1432)"
        );
    }

    #[DataProvider('pagesProvider')]
    public function testPageHasPageDescriptionAssignment(string $relativePath): void
    {
        $filePath = $this->rootDir . '/' . $relativePath;
        $this->assertFileExists($filePath, "$relativePath must exist (Issue #1432)");

        $content = (string)file_get_contents($filePath);

        $this->assertSame(
            1,
            preg_match('/\$pageDescription\s*=/', $content),
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

    #[DataProvider('pagesProvider')]
    public function testPageDescriptionAssignmentPrecedesInitRequire(string $relativePath): void
    {
        $this->assertVariableAssignmentPrecedesInitRequire($relativePath, 'pageDescription');
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

    public static function pagesProvider(): array
    {
        $data = [];
        foreach (self::PAGES_REQUIRING_METADATA as $file) {
            $data[$file] = [$file];
        }
        return $data;
    }
}
