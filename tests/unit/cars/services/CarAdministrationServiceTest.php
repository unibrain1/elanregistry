<?php

declare(strict_types=1);

use ElanRegistry\Car\CarAdministrationService;
use ElanRegistry\Car\CarRepository;
use ElanRegistry\DatabaseInterface;
use ElanRegistry\Exceptions\CarDatabaseException;
use ElanRegistry\Exceptions\CarNotFoundException;
use ElanRegistry\Exceptions\CarValidationException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for CarAdministrationService service class
 *
 * These tests exercise the REAL CarRepository against a DatabaseInterface double.
 * Mocking the framework boundary (the database) is the project convention; mocking our own
 * CarRepository would hide real repository behaviour such as the
 * CarNotFoundException thrown on 0-row deletes.
 *
 * @see docs/development/TESTING_STRATEGY.md
 */
#[Group('fast')]
final class CarAdministrationServiceTest extends TestCase
{
    private CarAdministrationService $service;
    private CarRepository $repo;

    protected function setUp(): void
    {
        $this->service = new CarAdministrationService();
        $this->repo = new CarRepository($this->createStub(DatabaseInterface::class));
    }

    /**
     * Configure $db's transaction-lifecycle expectations for one begin/end cycle.
     *
     * Models actual inTransaction() state via a closure-captured flag, rather than
     * assuming a fixed call count/order (a prior willReturnOnConsecutiveCalls(false, true)
     * version was one extra inTransaction() call away from a confusing null-return
     * TypeError instead of a clear assertion failure). beginTransaction() flips the
     * flag true; whichever of commit()/rollBack() actually runs flips it back false —
     * matching CarRepository's real transactionOwner bookkeeping exactly, regardless
     * of how many times inTransaction() happens to be called.
     *
     * The callbacks return true because DatabaseInterface declares beginTransaction(),
     * commit() and rollBack() as `bool` (the real PDO-backed methods return true on
     * success); a void callback would make the double return null and fail its own
     * return type.
     */
    private function configureTransaction(MockObject $db, bool $expectCommit): void
    {
        $inTransaction = false;
        $db->method('inTransaction')->willReturnCallback(function () use (&$inTransaction): bool {
            return $inTransaction;
        });
        $db->expects($this->once())->method('beginTransaction')
            ->willReturnCallback(function () use (&$inTransaction): bool {
                $inTransaction = true;
                return true;
            });
        $db->expects($expectCommit ? $this->once() : $this->never())->method('commit')
            ->willReturnCallback(function () use (&$inTransaction): bool {
                $inTransaction = false;
                return true;
            });
        $db->expects($expectCommit ? $this->never() : $this->once())->method('rollBack')
            ->willReturnCallback(function () use (&$inTransaction): bool {
                $inTransaction = false;
                return true;
            });
    }

    /**
     * Build the DatabaseInterface double that transfer() hands to Owner.
     *
     * Owner::find() runs a single `users LEFT JOIN profiles WHERE u.id = ?`
     * query and reads count()/first() back off the same instance, so the stub
     * mirrors that: query() returns itself, one row is found, and first()
     * returns a complete user row.
     */
    private function createOwnerDb(int $userId = 1): DatabaseInterface
    {
        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('error')->willReturn(false);
        $db->method('count')->willReturn(1);
        $db->method('first')->willReturn((object) [
            'id'        => $userId,
            'email'     => 'test@example.com',
            'fname'     => 'Test',
            'lname'     => 'User',
            'join_date' => '2024-01-01 00:00:00',
            'city'      => 'Test City',
            'state'     => 'TS',
            'country'   => 'US',
            'lat'       => null,
            'lon'       => null,
            'website'   => '',
        ]);
        return $db;
    }

    public function testDeleteSucceedsWithValidData(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);
        // query() returns the database object itself for chaining; the result of the
        // DELETE is read back off the same double via error()/count().
        $db->method('query')->willReturn($db);
        $db->method('error')->willReturn(false);
        $db->method('count')->willReturn(1); // deleteCar(): count()>0 -> true, no CarNotFoundException
        $repo = new CarRepository($db);

        $result = $this->service->delete($carData, 'Test deletion', 1, $repo);
        $this->assertTrue($result);
    }

    public function testMergeRejectsSelfMerge(): void
    {
        $this->expectException(CarValidationException::class);

        $carData = (object) [
            'id' => 1,
            'chassis' => 'TEST00001'
        ];

        $this->service->merge($carData, 1, 'Test merge', 1, $this->repo);
    }

    public function testTransferThrowsCarValidationExceptionWhenUserNotFound(): void
    {
        $this->expectException(CarValidationException::class);

        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999'];

        $this->service->transfer($carData, 0, 'Test transfer reason', 'NEWOWNER', 1, $this->repo, $this->createOwnerDb());
    }

    public function testTransferSucceeds(): void
    {
        // transfer() looks the target owner up via (new Owner($newUserId, $db))->data(),
        // using the DatabaseInterface passed as its last argument. A dedicated owner
        // double keeps that lookup separate from $db's repository expectations below.
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);
        $db->method('update')->willReturn(true);  // CarRepository::updateCar() -> $this->db->update(...)
        $db->method('insert')->willReturn(true);  // CarRepository::insertHistory() -> $this->db->insert(...)
        $repo = new CarRepository($db);

        // transfer()'s return type is literal `true` (throws on any failure), so no
        // assertion is needed on the return value itself — the mock's beginTransaction/
        // commit expectations above (verified in tearDown) are what this test proves.
        $this->service->transfer($carData, 1, 'Test transfer reason', 'NEWOWNER', 1, $repo, $this->createOwnerDb());
    }

    public function testTransferThrowsCarDatabaseExceptionWhenUpdateFails(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: false);
        $db->method('update')->willReturn(false); // updateCar() fails
        $repo = new CarRepository($db);

        $this->expectException(CarDatabaseException::class);
        $this->service->transfer($carData, 1, 'Test transfer reason', 'NEWOWNER', 1, $repo, $this->createOwnerDb());
    }

    public function testTransferThrowsCarDatabaseExceptionWhenInsertHistoryFails(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: false);
        $db->method('update')->willReturn(true);  // updateCar() succeeds
        $db->method('insert')->willReturn(false); // insertHistory() fails
        $repo = new CarRepository($db);

        $this->expectException(CarDatabaseException::class);
        $this->service->transfer($carData, 1, 'Test transfer reason', 'NEWOWNER', 1, $repo, $this->createOwnerDb());
    }

    // =========================================================================
    // delete() + merge() propagation tests (issue #1311)
    // =========================================================================

    /**
     * delete() must re-throw CarNotFoundException when deleteCar() discovers the
     * car was already deleted (0 rows affected).  The service catch block must
     * not swallow CarException subclasses.
     */
    public function testDeletePropagatesCarNotFoundExceptionFromDeleteCar(): void
    {
        // deleteCar(): error()=false, count()=0 -> throws CarNotFoundException (real CarRepository behavior)
        $carData = (object) ['id' => 999, 'chassis' => 'GHOST01'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: false);
        $db->method('query')->willReturn($db); // query() returns the database object itself
        $db->method('error')->willReturn(false);
        $db->method('count')->willReturn(0);
        $repo = new CarRepository($db);

        $this->expectException(CarNotFoundException::class);
        $this->service->delete($carData, 'Test deletion', 1, $repo);
    }

    /**
     * delete() must wrap a false return from deleteCar() in CarDatabaseException.
     * This is the DB-level error path, distinct from the CarNotFoundException
     * thrown when 0 rows are affected.
     */
    public function testDeleteThrowsCarDatabaseExceptionWhenDeleteCarReturnsFalse(): void
    {
        // deleteCar(): error()=true -> returns false BEFORE checking count (real CarRepository behavior)
        $carData = (object) ['id' => 999, 'chassis' => 'GHOST02'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: false);
        $db->method('query')->willReturn($db); // query() returns the database object itself
        $db->method('error')->willReturn(true);
        $repo = new CarRepository($db);

        $this->expectException(CarDatabaseException::class);
        $this->service->delete($carData, 'Test deletion', 1, $repo);
    }

    /**
     * merge() must throw CarNotFoundException when findByIdForUpdate() returns
     * null, indicating the source car was deleted between the caller's initial
     * check and the locked re-read inside the transaction.
     */
    public function testMergePropagatesCarNotFoundExceptionWhenSourceCarGone(): void
    {
        // findByIdForUpdate(999): error()=false, count()=0 -> returns null -> merge() throws CarNotFoundException itself
        $targetCarData = (object) ['id' => 1, 'chassis' => 'TARGET01'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: false);
        $db->method('query')->willReturn($db); // query() returns the database object itself
        $db->method('error')->willReturn(false);
        $db->method('count')->willReturn(0);
        $repo = new CarRepository($db);

        $this->expectException(CarNotFoundException::class);
        $this->service->merge($targetCarData, 999, 'Test merge', 1, $repo);
    }

    /**
     * merge() must wrap a false return from transferHistory() in CarDatabaseException.
     * This covers the DB-level failure path during the history-transfer step.
     */
    public function testMergeThrowsCarDatabaseExceptionWhenTransferHistoryFails(): void
    {
        // error() called twice in sequence: 1st by findByIdForUpdate (must be false = success),
        // 2nd by transferHistory (must be true = failure, since transferHistory returns !error()).
        $targetCarData = (object) ['id' => 1, 'chassis' => 'TARGET01'];
        $sourceData = (object) ['id' => 999, 'chassis' => 'SOURCE01'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: false);
        $db->method('query')->willReturn($db); // query() returns the database object itself
        $db->method('error')->willReturnOnConsecutiveCalls(false, true);
        $db->method('count')->willReturn(1);
        $db->method('first')->willReturn($sourceData);
        $repo = new CarRepository($db);

        $this->expectException(CarDatabaseException::class);
        $this->service->merge($targetCarData, 999, 'Test merge', 1, $repo);
    }

    public function testMergeThrowsCarDatabaseExceptionWhenDeleteCarFails(): void
    {
        // error() called 3x in sequence: findByIdForUpdate(false=ok), transferHistory(false=ok via !error()),
        // deleteCar(true=fails, returns false before checking count). count() only used by findByIdForUpdate.
        $targetCarData = (object) ['id' => 1, 'chassis' => 'TARGET01'];
        $sourceData = (object) ['id' => 999, 'chassis' => 'SOURCE01'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: false);
        $db->method('query')->willReturn($db); // query() returns the database object itself
        $db->method('error')->willReturnOnConsecutiveCalls(false, false, true);
        $db->method('count')->willReturn(1);
        $db->method('first')->willReturn($sourceData);
        $repo = new CarRepository($db);

        $this->expectException(CarDatabaseException::class);
        $this->service->merge($targetCarData, 999, 'Test merge', 1, $repo);
    }

    public function testMergeThrowsCarDatabaseExceptionWhenInsertHistoryFails(): void
    {
        // error() called 3x, all false (findByIdForUpdate ok, transferHistory ok, deleteCar ok).
        // count() called 2x, both >0 (findByIdForUpdate finds the row, deleteCar affects a row).
        // insert() (insertHistory) fails.
        $targetCarData = (object) ['id' => 1, 'chassis' => 'TARGET01'];
        $sourceData = (object) ['id' => 999, 'chassis' => 'SOURCE01'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: false);
        $db->method('query')->willReturn($db); // query() returns the database object itself
        $db->method('error')->willReturn(false);
        $db->method('count')->willReturn(1);
        $db->method('first')->willReturn($sourceData);
        $db->method('insert')->willReturn(false);
        $repo = new CarRepository($db);

        $this->expectException(CarDatabaseException::class);
        $this->service->merge($targetCarData, 999, 'Test merge', 1, $repo);
    }

    /**
     * merge() succeeds end-to-end: source car found, history transferred, source
     * car deleted, audit trail inserted, transaction commits. Success-path
     * counterpart to the four failure-path tests above.
     */
    public function testMergeSucceeds(): void
    {
        $targetCarData = (object) ['id' => 1, 'chassis' => 'TARGET01'];
        $sourceData = (object) ['id' => 999, 'chassis' => 'SOURCE01'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);
        $db->method('query')->willReturn($db); // query() returns the database object itself
        $db->method('error')->willReturn(false);
        $db->method('count')->willReturn(1);
        $db->method('first')->willReturn($sourceData);
        // Must actually assert the audit-trail insert happens — a loose ->method('insert')
        // stub would leave this test green even if merge() stopped calling insertHistory().
        $db->expects($this->once())->method('insert')->with('cars_hist', $this->anything())->willReturn(true);
        $repo = new CarRepository($db);

        // merge()'s return type is literal `true` (throws on any failure), so no
        // assertion is needed on the return value itself — the mock's beginTransaction/
        // commit expectations above (verified via PHPUnit's mock-expectation checks after
        // the test method completes) are what this test proves.
        $this->service->merge($targetCarData, 999, 'Test merge', 1, $repo);
    }
}
