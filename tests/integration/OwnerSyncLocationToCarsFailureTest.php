<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\DatabaseInterface;
use ElanRegistry\Owner;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test for Owner::syncLocationToCars()'s per-car failure
 * branches (#1618).
 *
 * AdminOwnerManagementTest::testSyncLocationToCarsCopiesCoordinatesToOwnedCar()
 * covers the happy path, and OwnerReadMethodsDatabaseFailureTest covers
 * getCarsOwned() throwing before the loop even starts. Neither covers what
 * happens once inside the per-car loop (Owner.php:619-635) when
 * CarRepository::updateCar()/insertHistory() return false rather than throw
 * — both are thin wrappers over DatabaseInterface::update()/insert()
 * (CarRepository.php:102-105, 331-334), which return bool, not exceptions.
 *
 * Owner constructs its own internal CarRepository(Owner.php:617) with no
 * injection point, but Owner and CarRepository share the same
 * DatabaseInterface instance — so a single proxy, parameterized by which
 * write method to fail, can force one specific failure while
 * getCarsOwned()'s own query()-based read keeps working against the real
 * connection. Mirrors OwnerCreateUpdateTransactionRollbackTest's proxy
 * pattern.
 *
 * @see usersc/classes/Owner.php Owner::syncLocationToCars()
 */
#[Group('integration')]
#[Group('owner')]
final class OwnerSyncLocationToCarsFailureTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    /**
     * A DatabaseInterface proxy backed by the real connection for every
     * method except one write method, which is forced to always return
     * false — simulating that single CarRepository write failing while
     * everything else (including reads via getCarsOwned()) keeps working
     * against the real connection.
     *
     * @param 'update'|'insert' $failingMethod Which write method always returns false
     */
    private function dbFailingOn(string $failingMethod): DatabaseInterface
    {
        $real = $this->db;
        return new class ($real, $failingMethod) implements DatabaseInterface {
            public function __construct(private DatabaseInterface $real, private string $failingMethod)
            {
            }
            public function query(string $sql, array $params = []): self
            {
                $this->real->query($sql, $params);
                return $this;
            }
            public function get(string $table, array $where): self|false
            {
                $result = $this->real->get($table, $where);
                return $result === false ? false : $this;
            }
            public function insert(string $table, array $fields = [], bool $update = false): bool
            {
                return $this->failingMethod === 'insert' ? false : $this->real->insert($table, $fields, $update);
            }
            public function update(string $table, array|int $id, array $fields): bool
            {
                return $this->failingMethod === 'update' ? false : $this->real->update($table, $id, $fields);
            }
            public function delete(string $table, array|int $where): self|false
            {
                $result = $this->real->delete($table, $where);
                return $result === false ? false : $this;
            }
            public function error(): bool
            {
                return $this->real->error();
            }
            public function errorString(): string
            {
                return $this->real->errorString();
            }
            public function errorInfo(): array
            {
                return $this->real->errorInfo();
            }
            public function count(): int
            {
                return $this->real->count();
            }
            public function first(bool $assoc = false): array|object
            {
                return $this->real->first($assoc);
            }
            public function results(bool $assoc = false): array
            {
                return $this->real->results($assoc);
            }
            public function lastId(): int
            {
                return $this->real->lastId();
            }
            public function beginTransaction(): bool
            {
                return $this->real->beginTransaction();
            }
            public function commit(): bool
            {
                return $this->real->commit();
            }
            public function rollBack(): bool
            {
                return $this->real->rollBack();
            }
            public function inTransaction(): bool
            {
                return $this->real->inTransaction();
            }
        };
    }

    /**
     * Build an Owner backed by the given proxy DB, with $_data populated via
     * Reflection so no real find() query is needed against the proxy.
     */
    private function ownerWithLoadedData(DatabaseInterface $db, array $data): Owner
    {
        $owner = new Owner(null, $db);

        $ref = new \ReflectionClass(Owner::class);
        $dataProp = $ref->getProperty('_data');
        $dataProp->setValue($owner, (object) $data);

        return $owner;
    }

    /**
     * updateCar() returning false must exclude that car from the returned
     * count (Owner.php:620-621 only increments $carsUpdated inside the
     * if-branch) and log the failure under LOG_CATEGORY_OWNER_ACTIONS
     * (Owner.php:633) rather than throw or silently succeed.
     */
    public function testUpdateCarFailureExcludesCarFromCountAndLogs(): void
    {
        $userId = $this->createTestUser();
        $carId = $this->createTestCar($userId, ['chassis' => 'SYNCFAIL1']);

        $logPattern = "syncLocationToCars: DB update returned false for car ID {$carId}%";
        $before = $this->countMatchingLogs('OwnerActions', $logPattern);

        $db = $this->dbFailingOn('update');
        $owner = $this->ownerWithLoadedData($db, [
            'id'      => $userId,
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => '45.5231',
            'lon'     => '-122.6765',
        ]);

        $carsUpdated = $owner->syncLocationToCars();

        $this->assertSame(0, $carsUpdated, 'A car whose updateCar() call fails must not be counted as updated');

        // Confirm the car's location was NOT actually changed in the real DB.
        $car = $this->db->query("SELECT lat FROM cars WHERE id = ?", [$carId])->first();
        $this->assertNotNull($car);
        $this->assertNull($car->lat, 'Car lat must remain unchanged when updateCar() fails');

        // Owner.php:633 — the update failure itself must be logged. Before/after
        // delta (not an absolute count) since the shared `logs` table is never
        // truncated across the suite run — matches CarCreateRepositoryFailureTest's
        // established convention for this exact scenario.
        $after = $this->countMatchingLogs('OwnerActions', $logPattern);
        $this->assertSame($before + 1, $after, 'The updateCar() failure must be logged under LOG_CATEGORY_OWNER_ACTIONS');
    }

    /**
     * insertHistory() returning false (with updateCar() succeeding) must
     * still count the car as updated (Owner.php:620-621 increments before
     * attempting the history insert) and log the history-insert failure
     * separately (Owner.php:630) without affecting the returned count.
     */
    public function testInsertHistoryFailureStillCountsCarAsUpdatedAndLogsSeparately(): void
    {
        $userId = $this->createTestUser();
        $carId = $this->createTestCar($userId, ['chassis' => 'SYNCFAIL2']);

        $logPattern = "syncLocationToCars: failed to insert history record for car ID {$carId}%";
        $before = $this->countMatchingLogs('OwnerActions', $logPattern);

        $db = $this->dbFailingOn('insert');
        $owner = $this->ownerWithLoadedData($db, [
            'id'      => $userId,
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => '45.5231',
            'lon'     => '-122.6765',
        ]);

        $carsUpdated = $owner->syncLocationToCars();

        $this->assertSame(1, $carsUpdated, 'A car whose updateCar() succeeds must count as updated even if insertHistory() fails');

        // Confirm the car's location WAS actually changed despite the history-insert failure.
        $car = $this->db->query("SELECT lat FROM cars WHERE id = ?", [$carId])->first();
        $this->assertNotNull($car);
        $this->assertEqualsWithDelta(45.5231, (float) $car->lat, 0.001, 'Car lat must be updated even though insertHistory() failed');

        // Owner.php:630 — the history-insert failure must be logged separately from
        // the update itself. Before/after delta, matching CarUpdateRepositoryFailureTest's
        // established convention (the shared `logs` table is never truncated).
        $after = $this->countMatchingLogs('OwnerActions', $logPattern);
        $this->assertSame($before + 1, $after, 'The insertHistory() failure must be logged under LOG_CATEGORY_OWNER_ACTIONS');
    }
}
