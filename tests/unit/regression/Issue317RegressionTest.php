<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for Issue #317: Core test infrastructure and organization
 *
 * @issue 317
 * @link https://github.com/unibrain1/elanregistry/issues/317
 * @description Test infrastructure reorganization and fast test execution
 * @category infrastructure
 *
 * This test ensures that the test infrastructure implemented in #317
 * continues to work correctly in future code changes.
 */
#[Group('regression')]
final class Issue317RegressionTest extends TestCase
{
    /**
     * Test that the new test directory structure exists and is organized correctly
     */
    public function testTestDirectoryStructureExists(): void
    {
        $testDir = dirname(__DIR__, 2);

        // Verify main test directories exist
        $this->assertDirectoryExists($testDir . '/unit', 'Unit test directory should exist');
        $this->assertDirectoryExists($testDir . '/integration', 'Integration test directory should exist');
        $this->assertDirectoryExists($testDir . '/playwright', 'Playwright test directory should exist');
    }

    /**
     * Test that regression tests exist as a recognizable group under
     * tests/unit/regression/.
     */
    public function testRegressionTestsDirectoryExists(): void
    {
        $regressionDir = dirname(__DIR__) . '/regression';
        $this->assertDirectoryExists($regressionDir, 'tests/unit/regression/ should exist');

        $regressionTests = glob($regressionDir . '/*.php');
        $this->assertGreaterThanOrEqual(
            2,
            count($regressionTests),
            'tests/unit/regression/ should contain at least 2 regression test files'
        );
    }

    /**
     * Test that test files have been moved to correct directories
     */
    public function testTestFilesAreOrganized(): void
    {
        $testDir = dirname(__DIR__, 2);

        // Check that unit tests directory has test files (recursive — tests live in subdirectories)
        $unitFiles = [];
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($testDir . '/unit', RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $file) {
            if ($file->getExtension() === 'php') {
                $unitFiles[] = $file->getPathname();
            }
        }
        $this->assertGreaterThan(0, count($unitFiles), 'Unit tests directory should contain test files');

        // Check that integration tests directory has test files
        $integrationTests = glob($testDir . '/integration/*.php');
        $this->assertGreaterThan(0, count($integrationTests), 'Integration tests directory should contain test files');

        // Check that the regression tests directory has test files
        $regressionTests = glob($testDir . '/unit/regression/*.php');
        $this->assertGreaterThan(1, count($regressionTests), 'Regression tests directory should contain test files');
    }

    /**
     * Test that PHPUnit configuration includes main test suites
     */
    public function testPhpUnitConfigurationIncludesMainSuites(): void
    {
        $phpunitXml = dirname(__DIR__, 3) . '/phpunit.xml';
        $this->assertFileExists($phpunitXml, 'PHPUnit configuration should exist');

        $content = file_get_contents($phpunitXml);
        $this->assertStringContainsString('testsuite name="Unit"', $content);
        $this->assertStringContainsString('testsuite name="Integration"', $content);
    }

    /**
     * Test that composer.json includes the new test scripts
     */
    public function testComposerScriptsExist(): void
    {
        $composerJson = dirname(__DIR__, 3) . '/composer.json';
        $this->assertFileExists($composerJson, 'Composer configuration should exist');

        $content = file_get_contents($composerJson);
        $composer = json_decode($content, true);

        $this->assertArrayHasKey('scripts', $composer, 'Composer should have scripts section');
        $this->assertArrayHasKey('test:quick', $composer['scripts'], 'Should have test:quick script');
        $this->assertArrayHasKey('test:medium', $composer['scripts'], 'Should have test:medium script');
        $this->assertArrayHasKey('test:full', $composer['scripts'], 'Should have test:full script');
    }

    /**
     * Test that package.json includes the enhanced test scripts
     */
    public function testNpmScriptsExist(): void
    {
        $packageJson = dirname(__DIR__, 3) . '/package.json';
        $this->assertFileExists($packageJson, 'Package.json should exist');

        $content = file_get_contents($packageJson);
        $package = json_decode($content, true);

        $this->assertArrayHasKey('scripts', $package, 'Package.json should have scripts section');
        $this->assertArrayHasKey('test:quick', $package['scripts'], 'Should have npm test:quick script');
        $this->assertArrayHasKey('test:smoke', $package['scripts'], 'Should have npm test:smoke script');
    }
}
