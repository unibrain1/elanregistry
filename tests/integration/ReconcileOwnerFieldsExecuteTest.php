<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\Exceptions\CarDatabaseException;
use ElanRegistry\Exceptions\OwnerDatabaseException;
use ElanRegistry\Owner;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for #1961's Execute step in
 * `app/admin/scripts/maintenance/26-Reconcile-Owner-Fields.php`.
 *
 * This suite does not exercise the full securePage()-gated AJAX/HTTP page —
 * there is no precedent anywhere in tests/integration/ for driving a full
 * app/admin/scripts/ page file directly (see
 * CleanupRateLimitsFixScriptRunTest's docblock for the same reasoning applied
 * to script #25). Instead it reproduces the Execute step's exact call
 * sequence directly:
 *
 *   $ownerIds = findOwnerIdsWithDrift(dbi());
 *   foreach ($ownerIds as $ownerId) {
 *       $result = (new Owner($ownerId, dbi()))->syncOwnerFieldsToCars();
 *       // accumulate updatedCount()/skippedCount()/failedCount()
 *   }
 *
 * It proves only that this job's owner-ID-discovery + loop + aggregation
 * wiring is correct. It deliberately does NOT re-prove
 * Owner::syncOwnerFieldsToCars()'s own ownership-changed-mid-sync behavior —
 * that is already covered by OwnerSyncOwnerFieldsToCarsOwnershipScopingTest.
 *
 * The drift-detection functions this suite calls (findOwnerIdsWithDrift() in
 * particular) are loaded via
 * {@see IntegrationTestCase::loadOwnerFieldDriftFunctions()}, shared with
 * ReconcileOwnerFieldsAnalyzeTest, which needs the same functions.
 *
 * `cars_hist` assertions are scoped to `operation = 'OWNER_SYNC'` throughout,
 * reusing the same countOwnerSyncHistoryRows() pattern as
 * OwnerSyncOwnerFieldsToCarsTest — the `cars_update` trigger separately writes
 * its own `operation='UPDATE'` row on every matched UPDATE, including no-ops,
 * so an unfiltered count would be wrong.
 *
 * @see app/admin/scripts/maintenance/26-Reconcile-Owner-Fields.php
 * @see usersc/classes/Owner.php Owner::syncOwnerFieldsToCars()
 */
#[Group('integration')]
#[Group('database')]
final class ReconcileOwnerFieldsExecuteTest extends IntegrationTestCase
{
    /** @var int[] Profile IDs to clean up in tearDown */
    private array $createdProfileIds = [];

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
        }

        parent::tearDown();
    }

    /**
     * Create a profile row for a test user with optional overrides.
     * Tracked for cleanup in tearDown(). Mirrors OwnerSyncOwnerFieldsToCarsTest.
     */
    private function createTestProfile(int $userId, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $userId,
            'bio'     => '',
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => null,
            'lon'     => null,
            'website' => '',
        ];

        $this->db->insert('profiles', array_merge($defaults, $overrides));

        $row = $this->db->query('SELECT id FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId])->first();
        if (!$row) {
            throw new \RuntimeException("createTestProfile: insert failed for user_id={$userId}");
        }
        $this->createdProfileIds[] = (int) $row->id;
    }

    /**
     * Count cars_hist rows for a car scoped to operation='OWNER_SYNC' — never
     * all cars_hist rows, since the cars_update trigger writes its own
     * operation='UPDATE' row for every matched UPDATE, including no-ops.
     * Same pattern as OwnerSyncOwnerFieldsToCarsTest::countOwnerSyncHistoryRows().
     */
    private function countOwnerSyncHistoryRows(int $carId): int
    {
        $result = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM cars_hist WHERE car_id = ? AND operation = 'OWNER_SYNC'",
            [$carId]
        );
        if ($result->error()) {
            throw new \RuntimeException('countOwnerSyncHistoryRows query failed: ' . $result->errorString());
        }
        return (int) $result->first()->cnt;
    }

    private function getOwnerLastUpdated(int $carId): string
    {
        $row = $this->db->query('SELECT owner_last_updated FROM cars WHERE id = ?', [$carId])->first();
        $this->assertNotNull($row, "car {$carId} must exist");
        return (string) $row->owner_last_updated;
    }

    /**
     * Reproduces the Execute step's owner loop and totals accumulation exactly
     * as the script does it.
     *
     * Tracks all five totals the real script tracks — updated, skipped,
     * failed, ownersScanned and ownerErrors — and narrows its catch to the two
     * infrastructure exception types the script absorbs, so anything else
     * (a genuine programming error) fails the test rather than being counted
     * as an owner error.
     *
     * @param list<int> $ownerIds
     * @return array{updated:int, skipped:int, failed:int, ownersScanned:int, ownerErrors:int, perOwner: array<int, \ElanRegistry\OwnerSyncResult|null>}
     */
    private function runExecuteLoop(array $ownerIds): array
    {
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalFailed = 0;
        $ownersScanned = 0;
        $ownerErrors = 0;
        $perOwner = [];

        foreach ($ownerIds as $ownerId) {
            $ownersScanned++;
            try {
                $result = (new Owner($ownerId, $this->db))->syncOwnerFieldsToCars();
                $totalUpdated += $result->updatedCount();
                $totalSkipped += $result->skippedCount();
                $totalFailed += $result->failedCount();
                $perOwner[$ownerId] = $result;
            } catch (OwnerDatabaseException | CarDatabaseException $e) {
                // Per-owner catch, matching the script: a single owner's infra
                // failure must not abort a run covering many owners. Anything
                // outside these two types is a programming error and propagates,
                // exactly as the script lets it propagate.
                $ownerErrors++;
                $perOwner[$ownerId] = null;

                // Same defensive rollback the script performs, so a failure
                // thrown mid-transaction cannot cascade into every later owner.
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
            }
        }

        return [
            'updated'       => $totalUpdated,
            'skipped'       => $totalSkipped,
            'failed'        => $totalFailed,
            'ownersScanned' => $ownersScanned,
            'ownerErrors'   => $ownerErrors,
            'perOwner'      => $perOwner,
        ];
    }

    /**
     * A single owner's infrastructure failure must not abort the run: the
     * owners after it in the work list still get repaired.
     *
     * The failure is forced realistically rather than by mocking — the middle
     * owner's `users` row is deleted between building the work list and running
     * the loop, the same race a real run hits if an account is deleted while
     * the job is in flight. `new Owner($id)` then loads no data and
     * syncOwnerFieldsToCars() throws OwnerDatabaseException for that owner only.
     *
     * The failing owner is deliberately in the MIDDLE of the list: a test with
     * the thrower last would pass even if the loop aborted on failure.
     */
    public function testOneOwnersFailureDoesNotStopLaterOwnersFromBeingRepaired(): void
    {
        $goodCarIds = [];
        $goodOwnerIds = [];

        foreach (['Good', 'Thrower', 'Later'] as $index => $label) {
            $ownerId = $this->createTestUser([
                'fname' => $label,
                'lname' => 'Owner',
                'email' => strtolower($label) . '-isolation@example.com',
            ]);
            $this->createTestProfile($ownerId, [
                'city'    => 'Portland',
                'state'   => 'Oregon',
                'country' => 'United States',
            ]);
            $carId = $this->createTestCar($ownerId, [
                'email' => 'stale-' . strtolower($label) . '@example.com',
                'city'  => 'StaleCity',
            ]);

            $goodOwnerIds[$index] = $ownerId;
            $goodCarIds[$index] = $carId;
        }

        [$firstOwnerId, $throwerOwnerId, $lastOwnerId] = $goodOwnerIds;
        [$firstCarId, $throwerCarId, $lastCarId] = $goodCarIds;

        $ownerIds = findOwnerIdsWithDrift($this->db);
        $this->assertContains($firstOwnerId, $ownerIds);
        $this->assertContains($throwerOwnerId, $ownerIds);
        $this->assertContains($lastOwnerId, $ownerIds);

        // Scope the run to this test's own three owners, in an order that puts
        // the thrower between the two that must both be repaired.
        $ownerIdsToRun = [$firstOwnerId, $throwerOwnerId, $lastOwnerId];

        // Force the middle owner's sync to fail: with the user row gone,
        // Owner::__construct() loads no data and syncOwnerFieldsToCars()
        // throws OwnerDatabaseException ("called on an Owner that failed to
        // load"). The car row is left behind deliberately — that is what the
        // real race leaves behind too.
        $this->db->query('DELETE FROM users WHERE id = ?', [$throwerOwnerId]);

        $totals = $this->runExecuteLoop($ownerIdsToRun);

        $this->assertSame(3, $totals['ownersScanned'], 'All three owners must be attempted');
        $this->assertSame(1, $totals['ownerErrors'], 'Exactly the one broken owner may be counted as an owner-level error');
        $this->assertNull($totals['perOwner'][$throwerOwnerId], 'The broken owner must have produced no sync result');

        $firstCar = $this->db->query('SELECT email, city FROM cars WHERE id = ?', [$firstCarId])->first();
        $this->assertSame('good-isolation@example.com', $firstCar->email, 'The owner before the failure must be repaired');
        $this->assertSame('Portland', $firstCar->city);

        $lastCar = $this->db->query('SELECT email, city FROM cars WHERE id = ?', [$lastCarId])->first();
        $this->assertSame(
            'later-isolation@example.com',
            $lastCar->email,
            'The owner AFTER the failure must still be repaired — this is what proves the loop continued rather than aborting'
        );
        $this->assertSame('Portland', $lastCar->city);

        // The broken owner's car is untouched: there was no owner data to sync.
        $throwerCar = $this->db->query('SELECT email FROM cars WHERE id = ?', [$throwerCarId])->first();
        $this->assertSame('stale-thrower@example.com', $throwerCar->email, 'The broken owner\'s car must be left alone');

        $this->assertSame(2, $totals['updated'], 'Exactly the two healthy owners\' cars may be counted as updated');
        $this->assertSame(0, $totals['failed']);
        $this->assertSame(0, $totals['skipped']);
    }

    /**
     * Two owners with drifted cars: both are discovered by
     * findOwnerIdsWithDrift(), every drifted car is repaired to match its
     * owner's current data, and the aggregated totals equal the sum of each
     * owner's individual OwnerSyncResult counts.
     */
    public function testTwoOwnersAggregatedCorrectly(): void
    {
        $owner1Id = $this->createTestUser([
            'fname' => 'Alice',
            'lname' => 'Anderson',
            'email' => 'alice@example.com',
        ]);
        $this->createTestProfile($owner1Id, [
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
        ]);
        // Drifted: stale email/city on cars table.
        $owner1Car1 = $this->createTestCar($owner1Id, [
            'email' => 'stale-alice@example.com',
            'city'  => 'StaleCity',
        ]);
        // Also drifted (different field) so this owner has 2 drifted cars.
        $owner1Car2 = $this->createTestCar($owner1Id, [
            'fname' => 'StaleFirstName',
        ]);

        $owner2Id = $this->createTestUser([
            'fname' => 'Bob',
            'lname' => 'Baker',
            'email' => 'bob@example.com',
        ]);
        $this->createTestProfile($owner2Id, [
            'city'    => 'Salem',
            'state'   => 'Oregon',
            'country' => 'United States',
        ]);
        $owner2Car1 = $this->createTestCar($owner2Id, [
            'city' => 'OldSalem',
        ]);

        // A third, non-drifted owner/car must not appear in the work list.
        $owner3Id = $this->createTestUser([
            'fname' => 'Carl',
            'lname' => 'Carter',
            'email' => 'carl@example.com',
        ]);
        $this->createTestProfile($owner3Id, [
            'city'    => 'Eugene',
            'state'   => 'Oregon',
            'country' => 'United States',
        ]);
        $owner3Car = $this->createTestCar($owner3Id, [
            'fname'   => 'Carl',
            'lname'   => 'Carter',
            'email'   => 'carl@example.com',
            'city'    => 'Eugene',
            'state'   => 'Oregon',
            'country' => 'United States',
            // createTestCar() leaves an omitted `website` as SQL NULL, while
            // createTestProfile()'s default is '' — set explicitly so this
            // "not drifted" fixture is actually not drifted.
            'website' => '',
        ]);

        $ownerIds = findOwnerIdsWithDrift($this->db);

        $this->assertContains($owner1Id, $ownerIds, 'Owner 1 (drifted) must be in the work list');
        $this->assertContains($owner2Id, $ownerIds, 'Owner 2 (drifted) must be in the work list');
        $this->assertNotContains($owner3Id, $ownerIds, 'Owner 3 (not drifted) must not be in the work list');

        // Only run the loop over the two owners this test seeded, in case the
        // shared test DB has other drifted owners left by unrelated fixtures.
        $ownerIdsToRun = array_values(array_intersect($ownerIds, [$owner1Id, $owner2Id]));
        $totals = $this->runExecuteLoop($ownerIdsToRun);

        // Cars now match owner's current data.
        $car1 = $this->db->query('SELECT email, city FROM cars WHERE id = ?', [$owner1Car1])->first();
        $this->assertSame('alice@example.com', $car1->email);
        $this->assertSame('Portland', $car1->city);

        $car2 = $this->db->query('SELECT fname FROM cars WHERE id = ?', [$owner1Car2])->first();
        $this->assertSame('Alice', $car2->fname);

        $car3 = $this->db->query('SELECT city FROM cars WHERE id = ?', [$owner2Car1])->first();
        $this->assertSame('Salem', $car3->city);

        // Aggregated totals equal the sum of each owner's individual result.
        $sumUpdated = 0;
        $sumSkipped = 0;
        $sumFailed = 0;
        foreach ($totals['perOwner'] as $result) {
            $this->assertNotNull($result, 'No owner-level exception expected in this scenario');
            $sumUpdated += $result->updatedCount();
            $sumSkipped += $result->skippedCount();
            $sumFailed += $result->failedCount();
        }

        $this->assertSame($sumUpdated, $totals['updated']);
        $this->assertSame($sumSkipped, $totals['skipped']);
        $this->assertSame($sumFailed, $totals['failed']);

        // Concretely: 3 drifted cars across the two owners, all updated, none
        // skipped or failed (no mid-sync ownership changes in this scenario).
        $this->assertSame(3, $totals['updated']);
        $this->assertSame(0, $totals['skipped']);
        $this->assertSame(0, $totals['failed']);
    }

    /**
     * Exactly one cars_hist row with operation='OWNER_SYNC' per car that was
     * ACTUALLY changed by the sync. A car that already matched its owner
     * before the run gets zero new OWNER_SYNC rows (syncOwnerFieldsToCars()'s
     * own no-op branch, per Owner.php:730-745 — a matched-but-unchanged UPDATE
     * reports success but writes no OWNER_SYNC row). operation='UPDATE'
     * trigger rows are deliberately not counted here.
     */
    public function testExactlyOneOwnerSyncRowPerActuallyChangedCar(): void
    {
        $ownerId = $this->createTestUser([
            'fname' => 'Drift',
            'lname' => 'Owner',
            'email' => 'drift-owner@example.com',
        ]);
        $this->createTestProfile($ownerId, [
            'city'    => 'Bend',
            'state'   => 'Oregon',
            'country' => 'United States',
        ]);

        // Drifted car: will actually change.
        $driftedCarId = $this->createTestCar($ownerId, [
            'email' => 'stale@example.com',
        ]);

        // Already-matching car: nine synced fields identical to the owner's
        // current data, so the sync's UPDATE is a no-op for it.
        $owner = new Owner($ownerId, $this->db);
        $data = $owner->data();
        $this->assertNotNull($data);
        $matchingCarId = $this->createTestCar($ownerId, [
            'fname'   => $data->fname,
            'lname'   => $data->lname,
            'email'   => $data->email,
            'city'    => $data->city,
            'state'   => $data->state,
            'country' => $data->country,
            'lat'     => $data->lat,
            'lon'     => $data->lon,
            'website' => $data->website,
        ]);

        $this->assertSame(0, $this->countOwnerSyncHistoryRows($driftedCarId), 'Precondition: no OWNER_SYNC rows before the run');
        $this->assertSame(0, $this->countOwnerSyncHistoryRows($matchingCarId), 'Precondition: no OWNER_SYNC rows before the run');

        $ownerIds = findOwnerIdsWithDrift($this->db);
        $this->assertContains($ownerId, $ownerIds);

        $this->runExecuteLoop([$ownerId]);

        $this->assertSame(
            1,
            $this->countOwnerSyncHistoryRows($driftedCarId),
            'The actually-changed car must gain exactly one OWNER_SYNC row'
        );
        $this->assertSame(
            0,
            $this->countOwnerSyncHistoryRows($matchingCarId),
            'A car that already matched the owner must gain zero OWNER_SYNC rows'
        );
    }

    /**
     * owner_last_updated is never touched by the reconciliation run — the
     * single most important invariant per the #1961 plan. Captured
     * byte-for-byte before and after on a drifted car.
     */
    public function testOwnerLastUpdatedNeverTouched(): void
    {
        $ownerId = $this->createTestUser([
            'fname' => 'Invariant',
            'lname' => 'Owner',
            'email' => 'invariant-owner@example.com',
        ]);
        $this->createTestProfile($ownerId, [
            'city'    => 'Ashland',
            'state'   => 'Oregon',
            'country' => 'United States',
        ]);

        $staleOwnerLastUpdated = date('Y-m-d H:i:s', strtotime('-2 years'));
        $carId = $this->createTestCar($ownerId, [
            'email'              => 'stale-invariant@example.com',
            'owner_last_updated' => $staleOwnerLastUpdated,
        ]);

        $beforeValue = $this->getOwnerLastUpdated($carId);
        $this->assertSame($staleOwnerLastUpdated, $beforeValue, 'Precondition: owner_last_updated must start at the seeded stale value');

        $ownerIds = findOwnerIdsWithDrift($this->db);
        $this->assertContains($ownerId, $ownerIds);

        $totals = $this->runExecuteLoop([$ownerId]);
        $this->assertSame(1, $totals['updated'], 'Precondition: the seeded car must actually have been synced');

        $afterValue = $this->getOwnerLastUpdated($carId);
        $this->assertSame(
            $beforeValue,
            $afterValue,
            'owner_last_updated must be byte-for-byte unchanged after reconciliation — this is the invariant that keeps a synced car eligible for verification'
        );
    }

    /**
     * A repeat run is idempotent: after the first run leaves everything
     * synced, findOwnerIdsWithDrift() returns no remaining drift, and
     * defensively re-running the sync loop anyway adds zero new OWNER_SYNC
     * rows and reports every result as a no-new-history-rows outcome.
     */
    public function testRepeatRunIsIdempotent(): void
    {
        $ownerId = $this->createTestUser([
            'fname' => 'Idempotent',
            'lname' => 'Owner',
            'email' => 'idempotent-owner@example.com',
        ]);
        $this->createTestProfile($ownerId, [
            'city'    => 'Corvallis',
            'state'   => 'Oregon',
            'country' => 'United States',
        ]);
        $carId = $this->createTestCar($ownerId, [
            'email' => 'stale-idempotent@example.com',
            'city'  => 'OldCorvallis',
        ]);

        // First run: real drift exists and gets repaired.
        $firstRunOwnerIds = findOwnerIdsWithDrift($this->db);
        $this->assertContains($ownerId, $firstRunOwnerIds);

        $firstTotals = $this->runExecuteLoop([$ownerId]);
        $this->assertSame(1, $firstTotals['updated']);
        $this->assertSame(1, $this->countOwnerSyncHistoryRows($carId), 'First run must write exactly one OWNER_SYNC row for the drifted car');

        $car = $this->db->query('SELECT email, city FROM cars WHERE id = ?', [$carId])->first();
        $this->assertSame('idempotent-owner@example.com', $car->email);
        $this->assertSame('Corvallis', $car->city);

        // Second discovery pass: no remaining drift for this owner.
        $secondRunOwnerIds = findOwnerIdsWithDrift($this->db);
        $this->assertNotContains(
            $ownerId,
            $secondRunOwnerIds,
            'After the first run leaves everything synced, this owner must not reappear in the drift work list'
        );

        // Defensive: re-run the sync loop anyway (as an operator might, or as
        // the script would if invoked twice back-to-back) and confirm it is a
        // true no-op — zero new OWNER_SYNC rows, all-updated-with-zero-new-history
        // outcome.
        $historyCountBeforeSecondRun = $this->countOwnerSyncHistoryRows($carId);

        $secondTotals = $this->runExecuteLoop([$ownerId]);

        $this->assertSame(0, $secondTotals['skipped'], 'A repeat run must not skip any car');
        $this->assertSame(0, $secondTotals['failed'], 'A repeat run must not fail any car');
        $this->assertSame(1, $secondTotals['updated'], 'A repeat run still reports the no-op UPDATE as a successful update (per syncOwnerFieldsToCars()\'s own no-op branch), just with no new history row');

        $result = $secondTotals['perOwner'][$ownerId];
        $this->assertNotNull($result);
        $this->assertTrue($result->isCompleteSuccess(), 'A repeat, no-op run must read as complete success');

        $this->assertSame(
            $historyCountBeforeSecondRun,
            $this->countOwnerSyncHistoryRows($carId),
            'A repeat, no-op run must add zero new OWNER_SYNC rows'
        );
    }
}
