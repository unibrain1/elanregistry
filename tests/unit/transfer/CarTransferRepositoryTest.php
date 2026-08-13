<?php

declare(strict_types=1);

use ElanRegistry\DatabaseInterface;
use ElanRegistry\Exceptions\CarDatabaseException;
use ElanRegistry\Transfer\CarTransferRepository;
use ElanRegistry\Transfer\TransferStatus;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for CarTransferRepository.
 *
 * Mixes two DatabaseInterface doubles: the happy-path/not-found tests run
 * against the healthy-but-empty stub from makeEmptyResultDb() (query() reports
 * no rows, insert() returns true, lastId() returns 1) — SQL correctness
 * (column names, WHERE clauses, JOINs) is NOT verified there. The "Database
 * error paths" tests below inject a per-test double whose error() returns
 * true, proving each method fails closed with CarDatabaseException. Real-DB
 * behavioral coverage lives in
 * tests/integration/transfer/CarTransferRepositoryIntegrationTest.php.
 */
#[Group('fast')]
#[Group('transfer')]
final class CarTransferRepositoryTest extends TestCase
{
    private CarTransferRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new CarTransferRepository($this->makeEmptyResultDb());
    }

    /**
     * A database double standing in for a healthy connection with no matching
     * rows: query() returns the double itself (the real \DB contract — query()
     * always returns $this for chaining), error() is false, and the result
     * accessors report an empty result set. first() returns [] rather than
     * null, matching the real \DB::first() contract.
     *
     * @return \PHPUnit\Framework\MockObject\Stub&DatabaseInterface
     */
    private function makeEmptyResultDb(): object
    {
        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('error')->willReturn(false);
        $db->method('count')->willReturn(0);
        $db->method('first')->willReturn([]);
        $db->method('results')->willReturn([]);
        $db->method('insert')->willReturn(true);
        $db->method('lastId')->willReturn(1);

        return $db;
    }

    public function testFindPendingByIdReturnsNullForMissingId(): void
    {
        $result = $this->repo->findPendingById(PHP_INT_MAX);
        $this->assertNull($result);
    }

    public function testFindPendingWithCarByIdReturnsNullForMissingId(): void
    {
        $result = $this->repo->findPendingWithCarById(PHP_INT_MAX);
        $this->assertNull($result);
    }

    public function testHasPendingForCarReturnsFalseForMissingCar(): void
    {
        $result = $this->repo->hasPendingForCar(PHP_INT_MAX, PHP_INT_MAX);
        $this->assertFalse($result);
    }

    public function testFindByIdReturnsNullForMissingId(): void
    {
        $result = $this->repo->findById(PHP_INT_MAX);
        $this->assertNull($result);
    }

    public function testCreateReturnsPositiveInt(): void
    {
        $result = $this->repo->create([
            'existing_car_id'     => 1,
            'requested_by_user_id' => 2,
            'security_token'      => 'TESTTOKEN12345678901234567890123456789012345678',
            'expires_at'          => '2026-08-01 00:00:00',
            'submitted_model'     => 'Elan',
            'submitted_series'    => 'S4',
            'submitted_variant'   => 'SE',
            'submitted_year'      => '1973',
            'submitted_type'      => '26R',
            'submitted_chassis'   => 'TEST_CTR_001',
            'created_by'          => 2,
        ]);
        $this->assertIsInt($result);
        $this->assertGreaterThan(0, $result);
    }

    public function testUpdateStatusReturnsBool(): void
    {
        $id = $this->repo->create([
            'existing_car_id'     => 1,
            'requested_by_user_id' => 2,
            'security_token'      => 'TESTTOKEN_UPDATE_STATUS_1234567890123456789012',
            'expires_at'          => '2026-08-01 00:00:00',
            'submitted_model'     => 'Elan',
            'submitted_series'    => 'S4',
            'submitted_variant'   => 'SE',
            'submitted_year'      => '1973',
            'submitted_type'      => '26R',
            'submitted_chassis'   => 'TEST_CTR_002',
            'created_by'          => 2,
        ]);
        $this->assertGreaterThan(0, $id, 'Precondition: create must succeed');

        // Mock query() always returns empty results, so rows-affected = 0.
        // That rows-affected=0 → false path is the correct behavior (no row matched).
        // True/False with real rows is verified by the integration tests.
        $result = $this->repo->updateStatus($id, TransferStatus::Denied, 'Test denial');
        $this->assertIsBool($result);
    }

    public function testCountPendingReturnsInt(): void
    {
        $result = $this->repo->countPending();
        $this->assertIsInt($result);
    }

    public function testGetPendingWithCarAndUsersReturnsArray(): void
    {
        $result = $this->repo->getPendingWithCarAndUsers();
        $this->assertIsArray($result);
    }

    // =========================================================================
    // Database error paths (issue #1441)
    //
    // The healthy stub above always reports error() = false, so these guards were
    // unreachable from it. Each test injects a per-test DB double whose error()
    // returns true, proving the repository fails closed with a
    // CarDatabaseException rather than returning a healthy-looking empty result.
    // =========================================================================

    /** @return \PHPUnit\Framework\MockObject\MockObject&DatabaseInterface */
    private function makeDbMock(): object
    {
        return $this->createMock(DatabaseInterface::class);
    }

    /**
     * Assert that calling $action on a repository wired to an erroring DB throws
     * CarDatabaseException. Shared by every "*ThrowsOnDatabaseError" test below —
     * they differ only in which repository method $action invokes.
     */
    private function assertThrowsOnDatabaseError(callable $action): void
    {
        $db = $this->makeDbMock();
        $db->expects($this->once())->method('query')->willReturnSelf();
        $db->method('error')->willReturn(true);
        $db->method('errorString')->willReturn('Connection lost');

        $repo = new CarTransferRepository($db);

        $this->expectException(CarDatabaseException::class);
        $action($repo);
    }

    public function testFindByIdThrowsOnDatabaseError(): void
    {
        $this->assertThrowsOnDatabaseError(fn (CarTransferRepository $repo) => $repo->findById(1));
    }

    public function testFindPendingByIdThrowsOnDatabaseError(): void
    {
        $this->assertThrowsOnDatabaseError(fn (CarTransferRepository $repo) => $repo->findPendingById(1));
    }

    public function testFindPendingWithCarByIdThrowsOnDatabaseError(): void
    {
        $this->assertThrowsOnDatabaseError(fn (CarTransferRepository $repo) => $repo->findPendingWithCarById(1));
    }

    public function testHasPendingForCarThrowsOnDatabaseError(): void
    {
        $this->assertThrowsOnDatabaseError(fn (CarTransferRepository $repo) => $repo->hasPendingForCar(1, 2));
    }

    public function testGetPendingWithCarAndUsersThrowsOnDatabaseError(): void
    {
        $this->assertThrowsOnDatabaseError(fn (CarTransferRepository $repo) => $repo->getPendingWithCarAndUsers());
    }

    public function testGetTodayStatusCountsThrowsOnDatabaseError(): void
    {
        $this->assertThrowsOnDatabaseError(fn (CarTransferRepository $repo) => $repo->getTodayStatusCounts());
    }

    public function testCountPendingThrowsOnDatabaseError(): void
    {
        $this->assertThrowsOnDatabaseError(fn (CarTransferRepository $repo) => $repo->countPending());
    }

    public function testUpdateStatusThrowsOnDatabaseError(): void
    {
        $this->assertThrowsOnDatabaseError(
            fn (CarTransferRepository $repo) => $repo->updateStatus(1, TransferStatus::Denied, 'Test denial')
        );
    }

    // =========================================================================
    // create() failure paths (issue #1441)
    //
    // create() doesn't fit assertThrowsOnDatabaseError() above — it calls
    // insert()/lastId() directly, not query()/error(). Two distinct failure
    // branches: the insert itself failing, and the insert "succeeding" but
    // returning no usable ID.
    // =========================================================================

    public function testCreateThrowsOnInsertFailure(): void
    {
        $db = $this->makeDbMock();
        $db->expects($this->once())->method('insert')->willReturn(false);
        $db->method('errorString')->willReturn('Duplicate entry');

        $repo = new CarTransferRepository($db);

        $this->expectException(CarDatabaseException::class);
        $repo->create(['existing_car_id' => 1]);
    }

    public function testCreateThrowsWhenNoIdReturned(): void
    {
        $db = $this->makeDbMock();
        $db->expects($this->once())->method('insert')->willReturn(true);
        $db->expects($this->once())->method('lastId')->willReturn(0);

        $repo = new CarTransferRepository($db);

        $this->expectException(CarDatabaseException::class);
        $repo->create(['existing_car_id' => 1]);
    }
}
