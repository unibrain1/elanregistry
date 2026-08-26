<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test for #1775: script #25
 * (`app/admin/scripts/maintenance/25-Cleanup-Rate-Limits.php`) previously never
 * wrote to `fix_script_runs` at all, so the "Last Run" column on the
 * maintenance dashboard always showed "Never" for it regardless of how many
 * times it had actually run. The fix added a single
 * `admin_script_record_completion(__FILE__, (int) $user->data()->id)` call
 * inside the script's existing try block, immediately after
 * `(new \RateLimit())->cleanup(24)` succeeds and its result is logged, before
 * the success `<div>` is rendered — see the plan at
 * docs/plans/issue-1776-fix-page-permissions-last-run.md.
 *
 * What this test does and does not prove: script #25's cleanup/record logic
 * is plain inline PHP directly inside the page — it is not gated behind its
 * own AJAX action or extracted into a separately-callable function, and it is
 * only reached once `admin_script_exec_requested()` (a POST + CSRF + isAdmin()
 * check in app/admin/includes/fix-script-core.php) returns true. There is no
 * existing precedent anywhere in tests/integration/ for executing a full
 * app/admin/scripts/ page file directly (confirmed via grep for
 * "require.*app/admin" across the suite — no hits), and simulating the
 * page's POST/CSRF/session/template-rendering machinery just to reach three
 * lines of business logic would add a lot of fragile scaffolding for no
 * extra assurance. This test instead reproduces the script's exact sequence
 * of calls — `(new \RateLimit())->cleanup(24)` followed by
 * `admin_script_record_completion(__FILE__, $userId)` — directly, using a
 * real `us_rate_limits` table seeded with rows older than the 24-hour cutoff
 * and a real `fix_script_runs` table. It proves that this call sequence, with
 * a genuine expired-row cleanup in between, results in exactly one
 * `fix_script_runs` row with the correct `script_name` and a fresh
 * `completed_at`. It does NOT prove that the page's own gating condition
 * (`$is_exec`/`admin_script_exec_requested()`) is wired correctly, or that
 * the HTML actually renders — those are template/routing concerns outside
 * this test's scope, not part of the #1775 bug (which was purely "the insert
 * was never written").
 */
#[Group('integration')]
#[Group('database')]
final class CleanupRateLimitsFixScriptRunTest extends IntegrationTestCase
{
    private const SCRIPT_NAME = '25-Cleanup-Rate-Limits.php';

    /** @var list<int> us_rate_limits row IDs seeded by this test, deleted in tearDown if cleanup() didn't already remove them. */
    private array $seededRateLimitIds = [];

    /** @var list<int> fix_script_runs row IDs inserted by this test, deleted in tearDown. */
    private array $insertedFixScriptRunIds = [];

    private ?int $testUserId = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        require_once __DIR__ . '/../../app/admin/includes/fix-script-core.php';

        $this->testUserId = $this->createTestUser();
    }

    protected function tearDown(): void
    {
        if ($this->databaseConnected) {
            foreach ($this->insertedFixScriptRunIds as $id) {
                $this->db->query('DELETE FROM fix_script_runs WHERE id = ?', [$id]);
            }
            foreach ($this->seededRateLimitIds as $id) {
                // Defensive: cleanup() should already have deleted these (they're
                // seeded older than its 24-hour cutoff), but a future change to
                // the cutoff or a failed cleanup call must not leak rows into
                // later tests either way.
                $this->db->query('DELETE FROM us_rate_limits WHERE id = ?', [$id]);
            }
        }

        parent::tearDown();
    }

    /**
     * Reproduces script #25's exact call sequence (cleanup, then record
     * completion) and confirms exactly one fix_script_runs row appears with
     * the correct script_name and a fresh completed_at — matching the
     * issue's own acceptance criteria wording ("exactly one row").
     */
    public function testCompletedCleanupRunInsertsExactlyOneFixScriptRunsRow(): void
    {
        $this->seedExpiredRateLimitRow();
        $this->seedExpiredRateLimitRow();

        $beforeCount = $this->countFixScriptRunsRows();

        // Mirrors 25-Cleanup-Rate-Limits.php's try block exactly:
        // $removed = (new \RateLimit())->cleanup(24);
        // logger(...);
        // admin_script_record_completion(__FILE__, (int) $user->data()->id);
        $removed = (new \RateLimit())->cleanup(24);
        $this->assertGreaterThanOrEqual(
            2,
            $removed,
            'cleanup(24) must have removed at least the two expired rows seeded by this test'
        );

        $beforeInsert = new \DateTimeImmutable('now');
        admin_script_record_completion(
            __DIR__ . '/../../app/admin/scripts/maintenance/' . self::SCRIPT_NAME,
            (int) $this->testUserId
        );
        $afterInsert = new \DateTimeImmutable('now');

        $afterCount = $this->countFixScriptRunsRows();
        $this->assertSame(
            $beforeCount + 1,
            $afterCount,
            'Exactly one new fix_script_runs row must be inserted for a completed cleanup run'
        );

        $rows = $this->fetchAllRows(
            'SELECT * FROM fix_script_runs WHERE script_name = ? ORDER BY id DESC LIMIT 1',
            [self::SCRIPT_NAME]
        );
        $this->assertCount(1, $rows, 'A fix_script_runs row for ' . self::SCRIPT_NAME . ' must exist');

        $row = $rows[0];
        $this->insertedFixScriptRunIds[] = (int) $row['id'];

        $this->assertSame(
            self::SCRIPT_NAME,
            $row['script_name'],
            'script_name must be the bare filename, matching basename(__FILE__) inside the script'
        );

        $completedAt = new \DateTimeImmutable((string) $row['completed_at']);
        $this->assertGreaterThanOrEqual(
            $beforeInsert->modify('-2 seconds'),
            $completedAt,
            'completed_at must be fresh (at or after the moment admin_script_record_completion() was called)'
        );
        $this->assertLessThanOrEqual(
            $afterInsert->modify('+2 seconds'),
            $completedAt,
            'completed_at must be fresh (at or before the moment admin_script_record_completion() returned)'
        );
    }

    /**
     * Confirms a fresh, empty us_rate_limits state — no rows to remove — still
     * counts as a successful cleanup run (RateLimit::cleanup() returns 0, not
     * an error, per users/classes/RateLimit.php:276-286) and still records a
     * completion row, since the script's try block has no branch that skips
     * the record call based on $removed being zero.
     */
    public function testCleanupWithNoExpiredRowsStillRecordsCompletion(): void
    {
        $beforeCount = $this->countFixScriptRunsRows();

        $removed = (new \RateLimit())->cleanup(24);
        $this->assertGreaterThanOrEqual(0, $removed);

        admin_script_record_completion(
            __DIR__ . '/../../app/admin/scripts/maintenance/' . self::SCRIPT_NAME,
            (int) $this->testUserId
        );

        $afterCount = $this->countFixScriptRunsRows();
        $this->assertSame(
            $beforeCount + 1,
            $afterCount,
            'A completion row must be recorded even when there was nothing to clean up'
        );

        $rows = $this->fetchAllRows(
            'SELECT id FROM fix_script_runs WHERE script_name = ? ORDER BY id DESC LIMIT 1',
            [self::SCRIPT_NAME]
        );
        $this->assertCount(1, $rows);
        $this->insertedFixScriptRunIds[] = (int) $rows[0]['id'];
    }

    /**
     * Seeds one us_rate_limits row with attempt_time older than the script's
     * 24-hour cutoff, so RateLimit::cleanup(24) has real expired data to remove.
     */
    private function seedExpiredRateLimitRow(): void
    {
        $identifier = 'test:' . uniqid('fix-script-run-test-', true);
        $expiredAttemptTime = date('Y-m-d H:i:s', time() - (25 * 3600));

        $this->db->insert('us_rate_limits', [
            'identifier_key' => $identifier,
            'action' => 'test_action',
            'success' => 0,
            'attempt_time' => $expiredAttemptTime,
            'metadata' => json_encode([]),
        ]);

        $this->seededRateLimitIds[] = (int) $this->db->lastId();
    }

    private function countFixScriptRunsRows(): int
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS cnt FROM fix_script_runs WHERE script_name = ?',
            [self::SCRIPT_NAME]
        )->first();

        return is_object($row) ? (int) $row->cnt : 0;
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
}
