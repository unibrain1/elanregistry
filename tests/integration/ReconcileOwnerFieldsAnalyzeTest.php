<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/../../app/admin/includes/fix-script-core.php';

use ElanRegistry\DatabaseInterface;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\FakeDatabase;

/**
 * Integration tests for #1961: 26-Reconcile-Owner-Fields.php's drift-detection
 * query functions.
 *
 * `26-Reconcile-Owner-Fields.php` is a full page script gated by a top-level
 * `securePage($php_self)` check (plus CSRF + `isAdmin()` for its AJAX branch)
 * before any of its functions are reachable — same shape as
 * `21-Fix-Page-Permissions.php`. Following that file's precedent (see
 * FixPagePermissionsAnalyzeRunTest::loadAnalyzePermissions()), this test
 * loads only the testable query functions
 * (findOwnerFieldDriftSummary/findOrphanedOwnerCarCount/findOwnerIdsWithDrift/
 * findDriftedCarDetails, plus their private helpers and the two shared
 * constants they depend on) via
 * {@see IntegrationTestCase::loadOwnerFieldDriftFunctions()}'s require()'d
 * temp-file slice, rather than requiring the whole securePage()-gated script.
 * That helper is shared with ReconcileOwnerFieldsExecuteTest, which needs the
 * same functions.
 *
 * @see usersc/classes/Owner.php Owner::syncOwnerFieldsToCars() — the write-side
 *      counterpart these functions detect drift against, covered by
 *      OwnerSyncOwnerFieldsToCarsTest.php.
 */
#[Group('integration')]
#[Group('database')]
final class ReconcileOwnerFieldsAnalyzeTest extends IntegrationTestCase
{
    private const SCRIPT_PATH = __DIR__ . '/../../app/admin/scripts/maintenance/26-Reconcile-Owner-Fields.php';

    /** @var list<int> Profile IDs created directly by this test, deleted in tearDown(). */
    private array $createdProfileIds = [];

    /** @var list<int> fix_script_runs.id values inserted by this test, deleted in tearDown(). */
    private array $insertedRunIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
        $this->loadOwnerFieldDriftFunctions();
    }

    protected function tearDown(): void
    {
        if ($this->databaseConnected) {
            foreach ($this->createdProfileIds as $profileId) {
                try {
                    $this->db->query('DELETE FROM profiles WHERE id = ?', [$profileId]);
                } catch (\Throwable $e) {
                    fwrite(STDERR, "NOTE: tearDown() cleanup failed for profile id {$profileId}: {$e->getMessage()}\n");
                }
            }
            $this->createdProfileIds = [];

            foreach ($this->insertedRunIds as $runId) {
                try {
                    $this->db->delete('fix_script_runs', ['id', '=', $runId]);
                } catch (\Throwable $e) {
                    fwrite(STDERR, "NOTE: tearDown() cleanup failed for fix_script_runs id {$runId}: {$e->getMessage()}\n");
                }
            }
            $this->insertedRunIds = [];
        }

        parent::tearDown();
    }

    /**
     * Create a profile row for a test user with optional overrides. Tracked
     * for cleanup in tearDown(). Mirrors
     * OwnerSyncOwnerFieldsToCarsTest::createTestProfile() — createTestUser()
     * does not create a profiles row, so any car whose owner fields include
     * city/state/country/lat/lon/website needs one created directly.
     *
     * @param array<string, mixed> $overrides
     */
    private function createTestProfile(int $userId, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $userId,
            'bio'     => '',
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 45.5231,
            'lon'     => -122.6765,
            'website' => 'https://example.com',
        ];

        $this->db->insert('profiles', array_merge($defaults, $overrides));

        $row = $this->db->query('SELECT id FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId])->first();
        if (!$row) {
            throw new \RuntimeException("createTestProfile: insert failed for user_id={$userId}");
        }
        $this->createdProfileIds[] = (int) $row->id;
    }

    /**
     * Baseline: a car whose nine owner-contact fields exactly match its
     * owner's current users/profiles values must be reported as drift-free
     * everywhere.
     */
    public function testCarMatchingOwnerExactlyReportsNoDrift(): void
    {
        $orphanedBefore = findOrphanedOwnerCarCount(dbi());
        // Baselines, not absolutes: these are whole-table aggregates with no
        // scoping to this test's fixtures, and the shared dev database may
        // legitimately already contain drift (the very drift this script was
        // written to repair). A regression such as the collation bug still
        // shows up here — as a +0 delta where +1 is required.
        $before = findOwnerFieldDriftSummary(dbi());

        $userId = $this->createTestUser([
            'fname' => 'Matching',
            'lname' => 'Owner',
            'email' => 'matching-owner@example.com',
        ]);
        $this->createTestProfile($userId, [
            'city'    => 'Eugene',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.0521,
            'lon'     => -123.0868,
            'website' => 'https://matching.example.com',
        ]);
        $this->createTestCar($userId, [
            'fname'   => 'Matching',
            'lname'   => 'Owner',
            'email'   => 'matching-owner@example.com',
            'city'    => 'Eugene',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.0521,
            'lon'     => -123.0868,
            'website' => 'https://matching.example.com',
        ]);

        $after = findOwnerFieldDriftSummary(dbi());

        foreach ($after['fields'] as $field => $count) {
            $this->assertSame(
                $before['fields'][$field],
                $count,
                "Field '{$field}' must not gain drift from an exactly-matching car"
            );
        }
        $this->assertSame($before['carsWithDrift'], $after['carsWithDrift']);
        $this->assertSame($before['ownersWithDrift'], $after['ownersWithDrift']);

        $this->assertNotContains(
            $userId,
            $this->allOwnerIdsWithDrift(),
            'A drift-free owner must not appear in the drift-repair work list'
        );

        $this->assertSame(
            $orphanedBefore,
            findOrphanedOwnerCarCount(dbi()),
            'A drift-free, non-orphaned car must not change the orphaned-car count'
        );
    }

    /**
     * A car with one drifted field (email) and one drifted numeric field
     * (lat) is counted precisely, and the pruning behavior of
     * findDriftedCarDetails() (only the fields that actually differ) is
     * verified.
     */
    public function testSingleFieldDriftIsCountedAndDetailIsPruned(): void
    {
        // Delta baseline — see testCarMatchingOwnerExactlyReportsNoDrift().
        $before = findOwnerFieldDriftSummary(dbi());

        $userId = $this->createTestUser([
            'fname' => 'Drift',
            'lname' => 'Test',
            'email' => 'current-email@example.com',
        ]);
        $this->createTestProfile($userId, [
            'city'    => 'Salem',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.9429,
            'lon'     => -123.0351,
            'website' => 'https://salem.example.com',
        ]);
        $carId = $this->createTestCar($userId, [
            'fname'   => 'Drift',
            'lname'   => 'Test',
            'email'   => 'stale-email@example.com', // drifted
            'city'    => 'Salem',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 40.0000, // drifted
            'lon'     => -123.0351,
            'website' => 'https://salem.example.com',
        ]);

        $after = findOwnerFieldDriftSummary(dbi());

        $expectedUnchanged = ['fname', 'lname', 'city', 'state', 'country', 'lon', 'website'];
        foreach ($expectedUnchanged as $field) {
            $this->assertSame(
                $before['fields'][$field],
                $after['fields'][$field],
                "Field '{$field}' must not be counted as drifted"
            );
        }
        $this->assertSame($before['fields']['email'] + 1, $after['fields']['email'], "Field 'email' must be counted as drifted exactly once");
        $this->assertSame($before['fields']['lat'] + 1, $after['fields']['lat'], "Field 'lat' must be counted as drifted exactly once");
        $this->assertSame($before['carsWithDrift'] + 1, $after['carsWithDrift']);
        $this->assertSame($before['ownersWithDrift'] + 1, $after['ownersWithDrift']);

        $ownerIds = findOwnerIdsWithDrift(dbi());
        $this->assertContains($userId, $ownerIds, 'The drifted owner must appear in the work list');

        $details = $this->findAllDriftedCarDetails();
        $matching = array_values(array_filter($details, static fn ($row) => $row['carId'] === $carId));
        $this->assertCount(1, $matching, 'Exactly one detail row must exist for the drifted car');

        $row = $matching[0];
        $this->assertSame($userId, $row['ownerId']);
        $this->assertSame('Drift Test', $row['ownerName']);

        $this->assertSame(
            ['email', 'lat'],
            array_keys($row['fields']),
            'findDriftedCarDetails() must prune to only the fields that actually differ'
        );
        $this->assertSame('stale-email@example.com', $row['fields']['email']['car']);
        $this->assertSame('current-email@example.com', $row['fields']['email']['owner']);
        $this->assertSame('40', $row['fields']['lat']['car']);
        $this->assertSame('44.9429', $row['fields']['lat']['owner']);
    }

    /**
     * A car whose user_id references a nonexistent user is counted as an
     * orphan, but its (nonexistent) "owner" must never appear in the
     * drift-repair work list — there is nothing to sync it against.
     */
    public function testOrphanedUserIdIsCountedButExcludedFromDriftWorklist(): void
    {
        // A user_id value guaranteed not to correspond to a real row: no
        // fixture in this suite ever creates a user with this ID, and it
        // is far outside the auto-increment range any test run reaches.
        $orphanUserId = 999_999_999;

        $ownerIdsBefore = findOwnerIdsWithDrift(dbi());
        $this->assertNotContains($orphanUserId, $ownerIdsBefore, 'Precondition: the orphan sentinel ID must not already be a real user');

        $orphanedBefore = findOrphanedOwnerCarCount(dbi());

        $carId = $this->createTestCar($this->createTestUser(), ['user_id' => $orphanUserId]);

        $orphanedAfter = findOrphanedOwnerCarCount(dbi());
        $this->assertSame($orphanedBefore + 1, $orphanedAfter, 'findOrphanedOwnerCarCount() must count the orphaned car');

        $ownerIdsAfter = findOwnerIdsWithDrift(dbi());
        $this->assertNotContains(
            $orphanUserId,
            $ownerIdsAfter,
            'An orphaned user_id must never appear in the drift-repair work list — it cannot be compared to a nonexistent owner'
        );

        // Cleanup note: createTestCar()'s user_id override does not match the
        // user actually created via createTestUser() above (that user IS
        // tracked and cleaned up normally); the car itself is tracked via
        // trackCarId() inside createTestCar() and is cleaned up in
        // IntegrationTestCase::tearDown() regardless of its user_id value.
        $this->assertGreaterThan(0, $carId);
    }

    /**
     * An owner with two cars, only one of which is drifted, must be counted
     * (and worklisted) exactly once — not once per drifted car.
     */
    public function testMultiCarOwnerWithOneDriftedCarDedupsToOneOwner(): void
    {
        // Delta baseline — see testCarMatchingOwnerExactlyReportsNoDrift().
        $before = findOwnerFieldDriftSummary(dbi());

        $userId = $this->createTestUser([
            'fname' => 'Multi',
            'lname' => 'Car',
            'email' => 'multi-car@example.com',
        ]);
        $this->createTestProfile($userId, [
            'city'    => 'Bend',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.0582,
            'lon'     => -121.3153,
            'website' => 'https://bend.example.com',
        ]);

        // Matching car: no drift.
        $this->createTestCar($userId, [
            'fname'   => 'Multi',
            'lname'   => 'Car',
            'email'   => 'multi-car@example.com',
            'city'    => 'Bend',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.0582,
            'lon'     => -121.3153,
            'website' => 'https://bend.example.com',
        ]);

        // Drifted car: email differs.
        $driftedCarId = $this->createTestCar($userId, [
            'fname'   => 'Multi',
            'lname'   => 'Car',
            'email'   => 'old-email@example.com',
            'city'    => 'Bend',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.0582,
            'lon'     => -121.3153,
            'website' => 'https://bend.example.com',
        ]);

        $after = findOwnerFieldDriftSummary(dbi());
        $this->assertSame($before['carsWithDrift'] + 1, $after['carsWithDrift'], 'Only the one genuinely drifted car may be counted');
        $this->assertSame($before['ownersWithDrift'] + 1, $after['ownersWithDrift'], 'The owner must be counted exactly once despite owning two cars');

        $ownerIds = findOwnerIdsWithDrift(dbi());
        $matches = array_values(array_filter($ownerIds, static fn ($id) => $id === $userId));
        $this->assertCount(1, $matches, 'findOwnerIdsWithDrift() must return the owner exactly once, not once per drifted car');

        $details = $this->findAllDriftedCarDetails();
        $carIds = array_column($details, 'carId');
        $this->assertContains($driftedCarId, $carIds);
        $this->assertSame(1, count(array_filter($carIds, static fn ($id) => $id === $driftedCarId)), 'The drifted car must appear exactly once in the detail list');
    }

    /**
     * admin_script_record_completion() itself — the zero-drift Analyze
     * branch's completion recording — is directly callable the same way
     * FixPagePermissionsAnalyzeRunTest exercises it for 21's own zero-issues
     * branch: it is a shared helper from fix-script-core.php (already
     * require_once'd above) taking (__FILE__, $userId), entirely independent
     * of the AJAX request/response plumbing around it in
     * 26-Reconcile-Owner-Fields.php's `action === 'analyze'` branch.
     *
     * This proves admin_script_record_completion() inserts a fix_script_runs
     * row keyed to this script's basename when called with a zero-drift
     * result. It does NOT prove that the AJAX handler's own
     * `if ($summary['carsWithDrift'] === 0) { admin_script_record_completion(...) }`
     * guard is actually reached at runtime — exercising that would require
     * driving the full securePage()/CSRF/isAdmin()-gated HTTP request, which
     * this suite (like FixPagePermissionsAnalyzeRunTest before it) has no
     * harness for. Given the guard is a single `if` around an
     * already-covered helper call with an already-covered function's output,
     * this is judged adequate on the same basis as that precedent.
     */
    public function testZeroDriftCompletionIsRecordedViaSharedHelper(): void
    {
        $userId = $this->createTestUser();

        $beforeMax = $this->maxRunId();

        admin_script_record_completion(self::SCRIPT_PATH, $userId);

        $rows = $this->db->query(
            'SELECT id, script_name, completed_at FROM fix_script_runs WHERE id > ? ORDER BY id DESC',
            [$beforeMax]
        )->results();

        $this->assertNotEmpty($rows, 'admin_script_record_completion() must insert a fix_script_runs row');

        $row = $rows[0];
        $this->insertedRunIds[] = (int) $row->id;

        $this->assertSame(
            '26-Reconcile-Owner-Fields.php',
            $row->script_name,
            'fix_script_runs.script_name must be the basename of 26-Reconcile-Owner-Fields.php'
        );

        $completedAt = strtotime((string) $row->completed_at);
        $this->assertNotFalse($completedAt, 'completed_at must be a parseable timestamp');
        $this->assertGreaterThan(
            time() - 60,
            $completedAt,
            'completed_at must be a fresh timestamp recorded by this test run, not a stale row'
        );
    }

    /**
     * A failed drift query must throw, not silently read as "zero drift".
     *
     * This project's DB::query() never throws on a failed statement — it
     * returns a result whose error() is true and whose row set is empty. Both
     * bugs already found in this script (a collation mismatch, a LIMIT binding
     * error) failed exactly that way, reporting no drift over real drift. The
     * error() check is the guard against a third instance of it, so it gets a
     * test of its own rather than resting on the two fixes that prompted it.
     */
    public function testFailedDriftSummaryQueryThrowsRatherThanReportingZeroDrift(): void
    {
        $failingDb = $this->makeFailingDatabase();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/drift summary query failed/');

        findOwnerFieldDriftSummary($failingDb);
    }

    /**
     * A DatabaseInterface double that reports every query as failed, exactly
     * as \DB does: query() returns itself (it never throws), error() is true,
     * and the result set is empty — the precise shape that made the collation
     * and LIMIT bugs read as "zero drift".
     *
     * Extends the shared FakeDatabase (whose defaults are all-succeeding),
     * overriding only the two methods this test needs.
     */
    private function makeFailingDatabase(): DatabaseInterface
    {
        return new class extends FakeDatabase {
            /** SQL of the most recent query(), mirroring \DB's per-call error state. */
            public ?string $lastSql = null;

            /** Reads of the error state, recorded so the accessors are observably impure. */
            public int $errorReads = 0;

            public function query(string $sql, array $params = []): self
            {
                $this->lastSql = $sql;

                return $this;
            }

            public function error(): bool
            {
                $this->errorReads++;

                return $this->lastSql !== null;
            }

            public function errorString(): string
            {
                $this->errorReads++;

                return $this->lastSql === null
                    ? ''
                    : 'ERROR #HY000: simulated statement failure';
            }
        };
    }

    private function maxRunId(): int
    {
        $row = $this->db->query('SELECT COALESCE(MAX(id), 0) AS max_id FROM fix_script_runs')->first();

        return is_object($row) ? (int) $row->max_id : 0;
    }

    /**
     * Convenience: findOwnerIdsWithDrift() results as a plain array, used by
     * the no-drift test's assertNotContains-style checks.
     *
     * @return list<int>
     */
    private function allOwnerIdsWithDrift(): array
    {
        return findOwnerIdsWithDrift(dbi());
    }

    /**
     * Paginate through findDriftedCarDetails() until exhausted, so tests
     * don't depend on the fixed 50-row page size or on there being fewer
     * than 50 drifted cars in a shared test database.
     *
     * @return list<array{carId: int, ownerId: int, ownerName: string, fields: array<string, array{car: string|null, owner: string|null}>}>
     */
    private function findAllDriftedCarDetails(): array
    {
        $all = [];
        $offset = 0;
        $pageSize = 50;

        do {
            $page = findDriftedCarDetails(dbi(), $pageSize, $offset);
            $all = array_merge($all, $page);
            $offset += count($page);
        } while (count($page) === $pageSize);

        return $all;
    }

}
