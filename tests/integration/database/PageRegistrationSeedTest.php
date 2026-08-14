<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for database/seeds/PageRegistrationSeed.php (#1671).
 *
 * The seed is a Phinx `AbstractSeed` subclass, discovered by filename glob
 * and executed under Phinx's own CLI runtime (adapter, input/output) — it is
 * not autoloaded by Composer and cannot be instantiated directly from
 * PHPUnit. This test therefore runs it the same way
 * `scripts/provision-schema.sh` does: as a real `vendor/bin/phinx seed:run`
 * subprocess against the dedicated integration test schema, then asserts on
 * the `pages`/`permission_page_matches` rows it leaves behind. This mirrors
 * `LogDeploymentScriptTest`'s subprocess pattern, including propagating this
 * run's DB_* env vars via putenv() so the subprocess connects to the same
 * test schema instead of falling back to the project's real .env.
 *
 * `pages` and `permission_page_matches` are truncated in setUp() so every
 * test starts from the empty-table state a fresh install actually has, and
 * restored from a snapshot in tearDown() so this suite never leaves the
 * shared integration schema missing its real page inventory for other tests.
 */
#[Group('integration')]
#[Group('migration')]
final class PageRegistrationSeedTest extends IntegrationTestCase
{
    /** @var list<string> */
    private const DB_ENV_VARS = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];

    /** @var list<array<string, mixed>> Snapshot of `pages` rows before this suite truncates the table. */
    private array $pagesSnapshot = [];

    /** @var list<array<string, mixed>> Snapshot of `permission_page_matches` rows before truncation. */
    private array $permissionMatchesSnapshot = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->pagesSnapshot = $this->fetchAllRows('SELECT * FROM `pages`');
        $this->permissionMatchesSnapshot = $this->fetchAllRows('SELECT * FROM `permission_page_matches`');

        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->db->query('TRUNCATE TABLE `permission_page_matches`');
        $this->db->query('TRUNCATE TABLE `pages`');
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
    }

    protected function tearDown(): void
    {
        if ($this->databaseConnected) {
            $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
            $this->db->query('TRUNCATE TABLE `permission_page_matches`');
            $this->db->query('TRUNCATE TABLE `pages`');

            foreach ($this->pagesSnapshot as $row) {
                $this->db->insert('pages', $row);
            }
            foreach ($this->permissionMatchesSnapshot as $row) {
                $this->db->insert('permission_page_matches', $row);
            }
            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        }

        parent::tearDown();
    }

    public function testSeedRegistersKnownPagesAcrossAllBranchesAndIsIdempotent(): void
    {
        [$returnCode, $output] = $this->runSeed();
        $this->assertSame(0, $returnCode, 'Seed must exit 0. Output: ' . implode("\n", $output));

        // Admin-only page: private=1, exactly one permission_page_matches row (Administrator = 2).
        $adminPage = $this->fetchAllRows(
            "SELECT * FROM `pages` WHERE `page` = 'app/admin/scripts/maintenance/21-Fix-Page-Permissions.php'"
        );
        $this->assertCount(1, $adminPage, 'Admin-only page must be registered exactly once');
        $this->assertSame(1, (int) $adminPage[0]['private'], 'Admin-only page must be private');

        $adminPageId = (int) $adminPage[0]['id'];
        $adminPermissions = $this->fetchAllRows(
            'SELECT * FROM `permission_page_matches` WHERE `page_id` = ?',
            [$adminPageId]
        );
        $this->assertCount(1, $adminPermissions, 'Admin-only page must have exactly one permission row');
        $this->assertSame(2, (int) $adminPermissions[0]['permission_id'], 'Admin-only page must grant Administrator (2)');

        // Public page: private=0, zero permission rows. docs/pdf-viewer.php calls
        // securePage() only for permission-table registration consistency, not to
        // require login (see that file's own header comment).
        $publicPage = $this->fetchAllRows("SELECT * FROM `pages` WHERE `page` = 'docs/pdf-viewer.php'");
        $this->assertCount(1, $publicPage, 'Public page must be registered exactly once');
        $this->assertSame(0, (int) $publicPage[0]['private'], 'Public page must not be private');

        $publicPageId = (int) $publicPage[0]['id'];
        $publicPermissions = $this->fetchAllRows(
            'SELECT * FROM `permission_page_matches` WHERE `page_id` = ?',
            [$publicPageId]
        );
        $this->assertCount(0, $publicPermissions, 'Public page must have no permission rows');

        // users/* page: a distinct code path (getUserSpiceInstallerSpec()) from the
        // app/usersc/docs branch exercised above. users/account.php is a known
        // UserSpice installer default: private=1, User permission only.
        $usersPage = $this->fetchAllRows("SELECT * FROM `pages` WHERE `page` = 'users/account.php'");
        $this->assertCount(1, $usersPage, 'users/account.php must be registered exactly once');
        $this->assertSame(1, (int) $usersPage[0]['private'], 'users/account.php must be private');

        $usersPageId = (int) $usersPage[0]['id'];
        $usersPagePermissions = $this->fetchAllRows(
            'SELECT * FROM `permission_page_matches` WHERE `page_id` = ?',
            [$usersPageId]
        );
        $this->assertCount(1, $usersPagePermissions, 'users/account.php must have exactly one permission row');
        $this->assertSame(1, (int) $usersPagePermissions[0]['permission_id'], 'users/account.php must grant User (1)');

        $pagesCountAfterFirstRun = $this->countRows('pages');
        $matchesCountAfterFirstRun = $this->countRows('permission_page_matches');
        $this->assertGreaterThan(0, $pagesCountAfterFirstRun, 'Seed must have registered at least one page');

        // Re-running must be a no-op.
        [$secondReturnCode, $secondOutput] = $this->runSeed();
        $this->assertSame(
            0,
            $secondReturnCode,
            'Re-running the seed must exit 0. Output: ' . implode("\n", $secondOutput)
        );

        $this->assertSame(
            $pagesCountAfterFirstRun,
            $this->countRows('pages'),
            'Re-running the seed must not change the pages row count'
        );
        $this->assertSame(
            $matchesCountAfterFirstRun,
            $this->countRows('permission_page_matches'),
            'Re-running the seed must not change the permission_page_matches row count'
        );
    }

    /**
     * @return array{0: int, 1: list<string>}
     */
    private function runSeed(): array
    {
        $this->exposeTestDatabaseToSubprocess();

        $phinxBinary = __DIR__ . '/../../../vendor/bin/phinx';
        $phinxConfig = __DIR__ . '/../../../phinx.php';

        $command = 'php ' . escapeshellarg($phinxBinary)
            . ' seed:run'
            . ' -c ' . escapeshellarg($phinxConfig)
            . ' -s ' . escapeshellarg('PageRegistrationSeed')
            . ' 2>&1';

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        return [$returnCode, $output];
    }

    /**
     * Propagate this test run's DB credentials into the process environment so the
     * `phinx` subprocess connects to the same dedicated test schema instead of
     * falling back to the project's real .env. Mirrors LogDeploymentScriptTest.
     */
    private function exposeTestDatabaseToSubprocess(): void
    {
        foreach (self::DB_ENV_VARS as $var) {
            $value = $_ENV[$var] ?? getenv($var);
            if (is_string($value) && $value !== '') {
                putenv("$var=$value");
            }
        }
    }

    /**
     * @param list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    private function fetchAllRows(string $sql, array $bindings = []): array
    {
        $rows = $this->db->query($sql, $bindings)->results();

        return array_map(static fn(object $row): array => (array) $row, $rows);
    }

    private function countRows(string $table): int
    {
        $quotedTable = '`' . str_replace('`', '``', $table) . '`';
        $row = $this->db->query("SELECT COUNT(*) AS cnt FROM {$quotedTable}")->first();

        return is_object($row) ? (int) $row->cnt : 0;
    }
}
