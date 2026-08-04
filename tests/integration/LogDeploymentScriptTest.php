<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test for scripts/log-deployment.php (Issue #1424).
 *
 * The script is a standalone CLI script invoked by the post-receive deploy
 * hook — it has no class/function to unit test directly, so this test
 * invokes it as a real subprocess and asserts on the row it writes to the
 * `logs` table.
 *
 * Environment note: tests/bootstrap-integration.php loads .env.test.local
 * into THIS PHPUnit process's $_ENV, but a subprocess spawned via exec()
 * starts with a fresh environment and would otherwise fall back to the
 * project's real .env — putenv() propagates this run's DB_* values before
 * each invocation so the subprocess's getenv() fallback resolves to the
 * same dedicated test schema this test suite already requires.
 */
#[Group('integration')]
#[Group('deployment')]
final class LogDeploymentScriptTest extends IntegrationTestCase
{
    private const SCRIPT_PATH = __DIR__ . '/../../scripts/log-deployment.php';

    /** @var list<string> */
    private const DB_ENV_VARS = ['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS'];

    private ?int $insertedLogId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    protected function tearDown(): void
    {
        foreach (self::DB_ENV_VARS as $var) {
            putenv($var);
        }

        if ($this->databaseConnected && $this->insertedLogId !== null) {
            try {
                $this->db->query('DELETE FROM logs WHERE id = ?', [$this->insertedLogId]);
            } catch (RuntimeException $e) {
                // Ignore cleanup errors — matches IntegrationTestCase's own convention.
            }
            $this->insertedLogId = null;
        }

        parent::tearDown();
    }

    public function testWritesExpectedDeploymentRow(): void
    {
        $this->exposeTestDatabaseToSubprocess();

        $version = 'v2.29.0-test';
        $environment = 'Test';
        $branch = 'milestone/v2.29.0';
        $gitHash = 'abcdef1234567890';

        [$returnCode, $output] = $this->runScript([$version, $environment, $branch, $gitHash]);

        $this->assertSame(0, $returnCode, 'Script must exit 0 on success. Output: ' . implode("\n", $output));

        $row = $this->db->query(
            "SELECT * FROM logs WHERE logtype = 'Deployment' ORDER BY id DESC LIMIT 1"
        )->first();

        // DB::first() (users/classes/DB.php) returns [] — not null — when no row matches.
        $this->assertIsObject($row, 'Expected a Deployment row to be inserted');
        $this->insertedLogId = (int)$row->id;

        $expectedLognote = "Deployed {$version} (" . substr($gitHash, 0, 8) . ") to {$environment} on branch {$branch}";
        $this->assertSame($expectedLognote, $row->lognote);
        $this->assertSame(0, (int)$row->user_id);
        $this->assertSame('', $row->ip);
    }

    public function testMissingArgumentsFailsNonFatallyWithoutWritingARow(): void
    {
        $this->exposeTestDatabaseToSubprocess();

        $lastIdBefore = $this->latestDeploymentLogId();

        // Only one of the four required arguments is supplied.
        [$returnCode, $output] = $this->runScript(['v2.29.0-test']);

        // Capture any row that may have been inserted BEFORE asserting, so a regression
        // that actually writes a row here — the exact scenario this test exists to catch —
        // doesn't leave it as a permanent orphan if the assertion below fails.
        $lastIdAfter = $this->latestDeploymentLogId();
        if ($lastIdAfter !== $lastIdBefore) {
            $this->insertedLogId = $lastIdAfter;
        }

        $this->assertSame(
            0,
            $returnCode,
            'Script must exit 0 even on internal failure (non-fatal contract). Output: ' . implode("\n", $output)
        );
        $this->assertSame($lastIdBefore, $lastIdAfter, 'No row should be written when required arguments are missing');
    }

    private function latestDeploymentLogId(): ?int
    {
        // DB::first() (users/classes/DB.php) returns [] — not null — when no row matches.
        $row = $this->db->query(
            "SELECT id FROM logs WHERE logtype = 'Deployment' ORDER BY id DESC LIMIT 1"
        )->first();
        return is_object($row) ? (int)$row->id : null;
    }

    /**
     * @param list<string> $args
     * @return array{0: int, 1: list<string>}
     */
    private function runScript(array $args): array
    {
        $command = 'php ' . escapeshellarg(self::SCRIPT_PATH);
        foreach ($args as $arg) {
            $command .= ' ' . escapeshellarg($arg);
        }
        $command .= ' 2>&1';

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);

        return [$returnCode, $output];
    }

    /**
     * Propagate this test run's DB credentials (loaded from .env.test.local
     * by tests/bootstrap-integration.php) into the process environment so a
     * subprocess spawned via exec() connects to the same dedicated test
     * schema instead of falling back to the project's real .env.
     */
    private function exposeTestDatabaseToSubprocess(): void
    {
        foreach (self::DB_ENV_VARS as $var) {
            $value = $_ENV[$var] ?? getenv($var);
            if ($value !== false && $value !== null && $value !== '') {
                putenv("$var=$value");
            }
        }
    }
}
