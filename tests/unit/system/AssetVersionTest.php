<?php

declare(strict_types=1);

use ElanRegistry\AssetVersionResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the ASSET_VERSION constant resolution logic (GitHub issue #1126).
 *
 * ASSET_VERSION is a PHP constant defined once at runtime via define() in
 * usersc/includes/config.php. PHP constants cannot be redefined between tests
 * in the same process, and config.php is not loaded by bootstrap-unit.php
 * (it requires $abs_us_root and redefines backup constants already set by
 * the bootstrap). To keep the resolution logic itself unit-testable despite
 * that constraint, it lives in ElanRegistry\AssetVersionResolver::resolve()
 * (#1598) — a pure, PSR-4-autoloaded function that takes no dependency on
 * $abs_us_root/$us_url_root or any BACKUP_* constant, so it loads standalone
 * in the unit tier. config.php calls it and wraps the result in define().
 * The behavioral tests below call AssetVersionResolver::resolve() directly,
 * so they exercise the exact same code path production runs — no drift risk.
 *
 * Four required scenarios are covered:
 *   - VERSION file present with a clean version string
 *   - VERSION file absent (fallback to 'dev')
 *   - VERSION file with invalid content — allow-list rejects it (fallback to 'dev')
 *   - VERSION file with surrounding whitespace (trim applied)
 *
 * Source code inspection tests verify that config.php wires up the resolver
 * correctly (the one thing that can't be verified behaviorally, since
 * config.php itself still can't be loaded standalone in this tier), plus one
 * AssetVersionResolver branch that's hard to trigger portably in PHPUnit.
 * These inspection tests guard only the exact substrings asserted — a
 * maintainer must keep the assertions tied to the literal statement being
 * verified (e.g. the full `define(...)` call, not just its parts appearing
 * independently anywhere in the file), or the guard becomes hollow.
 *
 * @issue 1126
 * @issue 1598
 */
#[Group('system')]
#[Group('asset-version')]
class AssetVersionTest extends TestCase
{
    /**
     * Writes content to a unique temporary file and returns the path.
     * Callers must unlink the file when done (use try/finally).
     */
    private function writeTempVersionFile(string $content): string
    {
        $path = sys_get_temp_dir() . '/AssetVersionTest_' . uniqid() . '_VERSION';
        file_put_contents($path, $content);
        return $path;
    }

    // -------------------------------------------------------------------
    // Scenario 1: VERSION file present — version string returned as-is
    // -------------------------------------------------------------------

    public function test_assetVersionResolver_versionFilePresent_returnsVersionString(): void
    {
        $path = $this->writeTempVersionFile('v2.25.7');

        try {
            $this->assertSame('v2.25.7', AssetVersionResolver::resolve($path));
        } finally {
            unlink($path);
        }
    }

    // -------------------------------------------------------------------
    // Scenario 2: VERSION file absent — fallback to 'dev'
    // -------------------------------------------------------------------

    public function test_assetVersionResolver_versionFileAbsent_returnsDev(): void
    {
        // Construct a path guaranteed not to exist.
        $path = sys_get_temp_dir() . '/AssetVersionTest_absent_' . uniqid() . '_VERSION';
        $this->assertFileDoesNotExist($path, 'Precondition: temp path must not exist');

        $this->assertSame('dev', AssetVersionResolver::resolve($path));
    }

    // -------------------------------------------------------------------
    // Scenario 3: VERSION file with invalid content — allow-list rejects it
    // -------------------------------------------------------------------

    public function test_assetVersionResolver_versionFileWithInvalidContent_returnsDev(): void
    {
        $path = $this->writeTempVersionFile('<script>alert(1)</script>');

        try {
            $this->assertSame('dev', AssetVersionResolver::resolve($path));
        } finally {
            unlink($path);
        }
    }

    /**
     * Internal whitespace (e.g. a second line appended by a botched deploy
     * script) survives trim() — since trim() only strips the ends — and must
     * still be rejected by the allow-list regex. Distinct from Scenario 4
     * below, which covers whitespace trim() *does* fully remove.
     */
    public function test_assetVersionResolver_versionFileWithInternalWhitespace_returnsDev(): void
    {
        $path = $this->writeTempVersionFile("v2.25.7\nleftover-line");

        try {
            $this->assertSame('dev', AssetVersionResolver::resolve($path));
        } finally {
            unlink($path);
        }
    }

    // -------------------------------------------------------------------
    // Scenario 4: VERSION file with surrounding whitespace — trim applied
    // -------------------------------------------------------------------

    public function test_assetVersionResolver_versionFileWithLeadingAndTrailingWhitespace_returnsTrimmedString(): void
    {
        $path = $this->writeTempVersionFile("  v2.25.7\n");

        try {
            $this->assertSame('v2.25.7', AssetVersionResolver::resolve($path));
        } finally {
            unlink($path);
        }
    }

    // -------------------------------------------------------------------
    // Data provider: all common whitespace / line-ending patterns
    // -------------------------------------------------------------------

    /**
     * Shell scripts and editors can produce various whitespace patterns in
     * a VERSION file. trim() must handle all of them.
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function whitespaceVariants(): array
    {
        return [
            'trailing newline only'           => ["v2.25.7\n",      'v2.25.7'],
            'leading spaces and trailing LF'  => ["  v2.25.7\n",   'v2.25.7'],
            'tab-padded on both sides'        => ["\tv2.25.7\t",   'v2.25.7'],
            'Windows CRLF line ending'        => ["v2.25.7\r\n",   'v2.25.7'],
            'leading and trailing spaces'     => ["  v2.25.7  ",   'v2.25.7'],
            'git-describe with build suffix'  => ["v2.25.6-13-g16246bf2\n", 'v2.25.6-13-g16246bf2'],
        ];
    }

    /**
     * trim() must strip all common whitespace and line-ending patterns,
     * including the git-describe output format actually written by the
     * deploy hook on this project.
     */
    #[DataProvider('whitespaceVariants')]
    public function test_assetVersionResolver_stripsVariousWhitespacePatterns(
        string $fileContent,
        string $expected
    ): void {
        $path = $this->writeTempVersionFile($fileContent);

        try {
            $this->assertSame($expected, AssetVersionResolver::resolve($path));
        } finally {
            unlink($path);
        }
    }

    // -------------------------------------------------------------------
    // Source code inspection: verify config.php implements the contract
    // -------------------------------------------------------------------

    /**
     * config.php must define ASSET_VERSION. Removing or renaming the constant
     * would silently break all asset URL cache-busting.
     */
    public function test_configPhp_definesAssetVersionConstant(): void
    {
        $configFile = dirname(__DIR__, 3) . '/usersc/includes/config.php';

        $this->assertFileExists($configFile, 'config.php must exist at usersc/includes/config.php');

        $content = (string) file_get_contents($configFile);

        $this->assertStringContainsString(
            "define('ASSET_VERSION'",
            $content,
            "config.php must define the ASSET_VERSION constant"
        );
    }

    /**
     * config.php must define ASSET_VERSION directly from
     * AssetVersionResolver::resolve() rather than reimplementing the
     * resolution logic inline. This is the one part of the contract that
     * can't be verified behaviorally, since config.php itself still can't be
     * loaded standalone in the unit tier (see class docblock). The resolution
     * logic itself (file_exists/trim/allow-list/'dev' fallback) is covered
     * directly by the behavioral tests above, which call
     * AssetVersionResolver::resolve() for real.
     *
     * Asserts the combined `define('ASSET_VERSION', AssetVersionResolver::resolve(`
     * statement rather than checking `define('ASSET_VERSION'` and
     * `AssetVersionResolver::resolve(` as two independent substrings —
     * independent checks would both still pass against a config.php that
     * regressed to `define('ASSET_VERSION', 'dev');` with the resolver call
     * left dangling elsewhere in the file.
     */
    public function test_configPhp_callsAssetVersionResolver(): void
    {
        $configFile = dirname(__DIR__, 3) . '/usersc/includes/config.php';
        $content = (string) file_get_contents($configFile);

        $this->assertStringContainsString(
            "define('ASSET_VERSION', AssetVersionResolver::resolve(",
            $content,
            "config.php must define ASSET_VERSION directly from AssetVersionResolver::resolve()"
        );
    }

    /**
     * The VERSION file path must be built from $abs_us_root . $us_url_root so
     * it resolves to the project root on every environment. $abs_us_root alone
     * has no trailing slash, so omitting $us_url_root produces a broken path
     * (e.g. /var/www/htmlVERSION) and the constant always falls back to 'dev'.
     */
    public function test_configPhp_buildsVersionFilePathFromAbsUsRootAndUsUrlRoot(): void
    {
        $configFile = dirname(__DIR__, 3) . '/usersc/includes/config.php';
        $content = (string) file_get_contents($configFile);

        $this->assertStringContainsString(
            '$abs_us_root',
            $content,
            "ASSET_VERSION path must use \$abs_us_root"
        );
        $this->assertStringContainsString(
            '$us_url_root',
            $content,
            "ASSET_VERSION path must include \$us_url_root as the directory separator between document root and 'VERSION'"
        );
        $this->assertStringContainsString(
            "'VERSION'",
            $content,
            "config.php must reference the 'VERSION' filename"
        );
    }

    /**
     * The $_versionFile helper variable must be unset after use to avoid
     * leaking it into the global scope. Asserts the exact `unset($_versionFile)`
     * statement rather than checking '$_versionFile' and 'unset(' as two
     * independent substrings — independent checks would both still pass if
     * the unset() call were deleted entirely, since '$_versionFile' also
     * appears in the variable's own assignment earlier in the file.
     */
    public function test_configPhp_unsetsHelperVariablesAfterUse(): void
    {
        $configFile = dirname(__DIR__, 3) . '/usersc/includes/config.php';
        $content = (string) file_get_contents($configFile);

        $this->assertStringContainsString(
            'unset($_versionFile)',
            $content,
            "config.php must unset \$_versionFile to prevent global-scope leakage"
        );
    }

    /**
     * An empty VERSION file (created but not written by a failed deploy hook)
     * must fall back to 'dev'. The '+' quantifier in the allow-list regex
     * requires at least one character, so an empty trimmed string is rejected.
     */
    public function test_assetVersionResolver_emptyVersionFile_returnsDev(): void
    {
        $path = $this->writeTempVersionFile('');

        try {
            $this->assertSame('dev', AssetVersionResolver::resolve($path));
        } finally {
            unlink($path);
        }
    }

    // -------------------------------------------------------------------
    // Template regression: every call site must include ?v=ASSET_VERSION
    // -------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function templateFilesWithAssetVersionTag(): array
    {
        $root = dirname(__DIR__, 3);
        return [
            'index.php (root)'         => [$root . '/index.php'],
            'footer.php'               => [$root . '/usersc/includes/footer.php'],
            'join.php'                 => [$root . '/usersc/join.php'],
            'user_settings.php'        => [$root . '/usersc/user_settings.php'],
            'cars/index.php'           => [$root . '/app/owner/cars/index.php'],
            'cars/details.php'         => [$root . '/app/owner/cars/details.php'],
            'cars/edit.php'            => [$root . '/app/owner/cars/edit.php'],
            'reports/statistics.php'   => [$root . '/app/owner/reports/statistics.php'],
            'admin/index.php'          => [$root . '/app/admin/index.php'],
            'admin/maintenance.php'    => [$root . '/app/admin/maintenance.php'],
        ];
    }

    /**
     * Every template that loads a first-party minified asset must append
     * ?v=<?= ASSET_VERSION ?>. A merge conflict or refactor that strips the
     * suffix from any file would restore stale-asset delivery for all users.
     */
    #[DataProvider('templateFilesWithAssetVersionTag')]
    public function test_templateFile_containsAssetVersionQueryParameter(string $path): void
    {
        $content = (string) file_get_contents($path);
        $this->assertStringContainsString(
            '?v=<?= ASSET_VERSION ?>',
            $content,
            basename($path) . ' must append ?v=<?= ASSET_VERSION ?> to its first-party asset tags'
        );
    }

    /**
     * AssetVersionResolver must log a warning via error_log() when
     * file_get_contents() fails for an existing VERSION file. Silently
     * falling back to 'dev' in that case would make deploy-hook failures
     * invisible in server logs. This branch can't be triggered behaviorally
     * in a portable unit test without risking a PHPUnit-converted-warning
     * failure, so it's covered via source inspection instead.
     */
    public function test_assetVersionResolver_logsErrorWhenFileGetContentsFails(): void
    {
        $resolverFile = dirname(__DIR__, 3) . '/usersc/classes/AssetVersionResolver.php';
        $content = (string) file_get_contents($resolverFile);

        $this->assertStringContainsString(
            'error_log(',
            $content,
            "AssetVersionResolver must call error_log() to make VERSION read failures visible in server logs"
        );
        $this->assertStringContainsString(
            'ASSET_VERSION: file_get_contents',
            $content,
            "AssetVersionResolver error_log() message must identify the ASSET_VERSION context for diagnosability"
        );
    }

    /**
     * config.php must be syntactically valid PHP so that it can be included
     * by the application without parse errors.
     */
    public function test_configPhp_isValidPhp(): void
    {
        $configFile = dirname(__DIR__, 3) . '/usersc/includes/config.php';
        $output = [];
        $returnCode = 0;
        exec("php -l " . escapeshellarg($configFile), $output, $returnCode);

        $this->assertSame(0, $returnCode, 'config.php must pass PHP syntax check (php -l)');
    }
}
