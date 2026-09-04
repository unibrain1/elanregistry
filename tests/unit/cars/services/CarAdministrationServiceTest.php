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
    /**
     * @param bool $blankLocation When true the target owner carries no location
     *                            or website data at all — the shape of the
     *                            `noowner` system account, and the case where
     *                            CarValidator drops the keys entirely rather
     *                            than passing the blanks through.
     * @param string $username Target owner's username. transfer() treats a
     *                         target with username 'noowner' as the system
     *                         account for `solddate` handling — see
     *                         testTransferToSystemAccountPreservesSoldDate().
     */
    private function createOwnerDb(
        int $userId = 1,
        string $email = 'test@example.com',
        bool $blankLocation = false,
        string $username = 'testuser'
    ): DatabaseInterface {
        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('error')->willReturn(false);
        $db->method('count')->willReturn(1);
        $db->method('first')->willReturn((object) [
            'id'        => $userId,
            'username'  => $username,
            'email'     => $email,
            'fname'     => 'Test',
            'lname'     => 'User',
            'join_date' => '2024-01-01 00:00:00',
            'city'      => $blankLocation ? '' : 'Test City',
            'state'     => $blankLocation ? '' : 'TS',
            'country'   => $blankLocation ? '' : 'US',
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

    /**
     * Issue #1878: transferring a car to a real owner must clear `solddate`
     * on both the live `cars` row and the audit history row it writes —
     * a sale does not survive a change of owner.
     */
    public function testTransferClearsSoldDateOnCarsAndHistoryRow(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999', 'solddate' => '2020-01-01'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);

        $updateFields = null;
        $historyFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturnCallback(
            function (string $table, array $fields = [], bool $update = false) use (&$historyFields): bool {
                $historyFields = $fields;
                return true;
            }
        );
        $repo = new CarRepository($db);

        $this->service->transfer($carData, 1, 'Test transfer reason', 'NEWOWNER', 1, $repo, $this->createOwnerDb());

        $this->assertArrayHasKey('solddate', $updateFields, 'cars.solddate must be written on transfer, not omitted');
        $this->assertNull($updateFields['solddate'], 'cars.solddate must be cleared on an ordinary transfer');
        $this->assertArrayHasKey('solddate', $historyFields, 'history solddate must be written on transfer, not omitted');
        $this->assertNull($historyFields['solddate'], 'history solddate must be cleared on an ordinary transfer');
    }

    /**
     * Issue #1878: the solddate decision is keyed on the target's username,
     * not on the unroutable SYSTEM_ACCOUNT_EMAIL sentinel. A real owner who
     * happens to carry that email (or no username at all in the row) is still
     * a change of owner and must have solddate cleared — otherwise a refactor
     * that "simplifies" the check to the email would silently preserve
     * solddate on any real owner with a malformed address, the same class of
     * bug #1878 fixed.
     */
    public function testTransferClearsSoldDateIsKeyedOnUsernameNotEmail(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999', 'solddate' => '2020-01-01'];

        // Case 1: sentinel email, ordinary username.
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);
        $updateFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturn(true);
        $this->service->transfer(
            $carData,
            1,
            'Test transfer reason',
            'NEWOWNER',
            1,
            new CarRepository($db),
            $this->createOwnerDb(1, 'noowner@invalid')
        );
        $this->assertArrayHasKey('solddate', $updateFields, 'A real owner with the sentinel email is still a change of owner');
        $this->assertNull($updateFields['solddate']);

        // Case 2: target row carries no username key at all — must mean "real owner".
        $ownerDb = $this->createStub(DatabaseInterface::class);
        $ownerDb->method('query')->willReturnSelf();
        $ownerDb->method('error')->willReturn(false);
        $ownerDb->method('count')->willReturn(1);
        $ownerDb->method('first')->willReturn((object) [
            'id'    => 1,
            'email' => 'test@example.com',
            'fname' => 'Test',
            'lname' => 'User',
        ]);
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);
        $updateFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturn(true);
        $this->service->transfer($carData, 1, 'Test transfer reason', 'NEWOWNER', 1, new CarRepository($db), $ownerDb);
        $this->assertArrayHasKey('solddate', $updateFields, 'A target row without a username must be treated as a real owner');
        $this->assertNull($updateFields['solddate']);
    }

    /**
     * Issue #1878: transferring to the `noowner` system account (the GDPR
     * account-deletion and admin "no owner" paths) must NOT clear `solddate`
     * — reassignment to the system account is not a change of owner, so the
     * sold state is preserved on both the live row and the history row.
     */
    public function testTransferToSystemAccountPreservesSoldDate(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999', 'solddate' => '2020-01-01'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);

        $updateFields = null;
        $historyFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturnCallback(
            function (string $table, array $fields = [], bool $update = false) use (&$historyFields): bool {
                $historyFields = $fields;
                return true;
            }
        );
        $repo = new CarRepository($db);

        $this->service->transfer(
            $carData,
            1,
            'Account deleted — reassigned to noowner',
            'NEWOWNER',
            1,
            $repo,
            $this->createOwnerDb(1, 'noowner@invalid', username: 'noowner')
        );

        $this->assertArrayNotHasKey(
            'solddate',
            $updateFields,
            'cars.solddate must be left untouched when transferring to the noowner system account'
        );
        $this->assertSame(
            '2020-01-01',
            $historyFields['solddate'],
            "history solddate must pass through the car's existing value for a system-account transfer"
        );
    }

    /**
     * email_bounced is a property of the previous owner's address, not the car:
     * carrying it forward would permanently exclude the car from
     * CarRepository::findVerificationEligible() (`AND email_bounced = 0`) once
     * the address that caused the bounce is gone. A transfer to a real owner
     * must clear it on both the live `cars` row and the audit history row.
     */
    public function testTransferClearsEmailBouncedOnCarsAndHistoryRow(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999', 'email_bounced' => 1];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);

        $updateFields = null;
        $historyFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturnCallback(
            function (string $table, array $fields = [], bool $update = false) use (&$historyFields): bool {
                $historyFields = $fields;
                return true;
            }
        );
        $repo = new CarRepository($db);

        $this->service->transfer($carData, 1, 'Test transfer reason', 'NEWOWNER', 1, $repo, $this->createOwnerDb());

        $this->assertArrayHasKey('email_bounced', $updateFields, 'cars.email_bounced must be written on transfer, not omitted');
        $this->assertSame(0, $updateFields['email_bounced'], 'cars.email_bounced must be cleared on an ordinary transfer');
        $this->assertArrayHasKey('email_bounced', $historyFields, 'history email_bounced must be written on transfer, not omitted');
        $this->assertSame(0, $historyFields['email_bounced'], 'history email_bounced must be cleared on an ordinary transfer');
    }

    /**
     * Reassignment to the `noowner` system account is not a change of owner
     * (#1878), so email_bounced — like solddate — must be preserved rather than
     * cleared: it still describes the previous (real) owner's address, and the
     * car remains excluded from verification eligibility until a real owner
     * with a working address is assigned again.
     */
    public function testTransferToSystemAccountPreservesEmailBounced(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999', 'email_bounced' => 1];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);

        $updateFields = null;
        $historyFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturnCallback(
            function (string $table, array $fields = [], bool $update = false) use (&$historyFields): bool {
                $historyFields = $fields;
                return true;
            }
        );
        $repo = new CarRepository($db);

        $this->service->transfer(
            $carData,
            1,
            'Account deleted — reassigned to noowner',
            'NEWOWNER',
            1,
            $repo,
            $this->createOwnerDb(1, 'noowner@invalid', username: 'noowner')
        );

        $this->assertArrayNotHasKey(
            'email_bounced',
            $updateFields,
            'cars.email_bounced must be left untouched when transferring to the noowner system account'
        );
        $this->assertSame(
            1,
            $historyFields['email_bounced'],
            "history email_bounced must pass through the car's existing value for a system-account transfer"
        );
    }

    /**
     * Regression (#1679): transferring to a system account whose email is
     * deliberately unroutable must succeed, storing an empty owner email rather
     * than aborting or denormalizing the sentinel address.
     *
     * The `noowner` account created by RegisterNoownerAccount carries
     * `noowner@invalid` precisely so password reset and passwordless login can
     * never reach it. That address fails CarValidator's FILTER_VALIDATE_EMAIL
     * check, so copying it onto the car threw CarValidationException and rolled
     * back the transfer — which silently broke GDPR account deletion, since
     * after_user_deletion.php reassigns every car through this exact path and
     * would leave the deleted owner's PII in place.
     */
    public function testTransferToUnroutableSystemAccountBlanksEmailInsteadOfFailing(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);

        $updateFields = null;
        $historyFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturnCallback(
            function (string $table, array $fields = [], bool $update = false) use (&$historyFields): bool {
                $historyFields = $fields;
                return true;
            }
        );
        $repo = new CarRepository($db);

        $this->service->transfer(
            $carData,
            1,
            'Account deleted — reassigned to noowner',
            'NEWOWNER',
            1,
            $repo,
            $this->createOwnerDb(1, 'noowner@invalid')
        );

        $this->assertSame('', $updateFields['email'] ?? null, 'cars.email must be blanked, not set to the sentinel address');
        $this->assertSame('', $historyFields['email'] ?? null, 'history email must be blanked, not set to the sentinel address');
    }

    /**
     * A *real* account carrying a malformed `users.email` must also blank rather
     * than abort — the transfer still has to complete, since this path runs
     * inside after_user_deletion.php's single reassignment transaction.
     *
     * The distinction from the sentinel case above is visibility, not behavior:
     * blanking `noowner@invalid` is expected and silent, but silently erasing a
     * legitimate owner's contact address would hide a data-quality problem, so
     * contactableEmail() logs that case. Both still write ''.
     */
    public function testTransferBlanksMalformedEmailOnRealAccountWithoutFailing(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);

        $updateFields = null;
        $historyFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturnCallback(
            function (string $table, array $fields = [], bool $update = false) use (&$historyFields): bool {
                $historyFields = $fields;
                return true;
            }
        );
        $repo = new CarRepository($db);

        $this->service->transfer(
            $carData,
            1,
            'Admin-initiated transfer',
            'NEWOWNER',
            1,
            $repo,
            $this->createOwnerDb(1, 'not-an-email-address')
        );

        $this->assertSame('', $updateFields['email'] ?? null, 'a malformed owner email must be blanked, never copied onto the car');
        $this->assertSame('', $historyFields['email'] ?? null, 'a malformed owner email must be blanked in history too');
    }

    /**
     * Guards every field in CarAdministrationService::OWNER_IDENTITY_FIELDS, not
     * just `email`. CarValidator omits any empty-valued key from its result, so
     * without withBlankedFieldsRestored() a transfer to an owner with no
     * location or website would leave the *previous* owner's city/state/country/
     * website sitting on the car — PII the GDPR deletion path must clear.
     * Dropping any field from OWNER_IDENTITY_FIELDS regresses this silently.
     */
    public function testTransferClearsAllOwnerIdentityFieldsWhenTargetHasNone(): void
    {
        $carData = (object) ['id' => 999, 'chassis' => 'TEST99999'];
        $db = $this->createMock(DatabaseInterface::class);
        $this->configureTransaction($db, expectCommit: true);

        $updateFields = null;
        $historyFields = null;
        $db->method('update')->willReturnCallback(
            function (string $table, array|int $id, array $fields) use (&$updateFields): bool {
                $updateFields = $fields;
                return true;
            }
        );
        $db->method('insert')->willReturnCallback(
            function (string $table, array $fields = [], bool $update = false) use (&$historyFields): bool {
                $historyFields = $fields;
                return true;
            }
        );
        $repo = new CarRepository($db);

        $this->service->transfer(
            $carData,
            1,
            'Account deleted — reassigned to noowner',
            'NEWOWNER',
            1,
            $repo,
            $this->createOwnerDb(1, 'noowner@invalid', blankLocation: true)
        );

        foreach (['email', 'city', 'state', 'country'] as $field) {
            $this->assertArrayHasKey(
                $field,
                $updateFields,
                "cars.{$field} must be written on transfer, not dropped by CarValidator"
            );
            $this->assertSame(
                '',
                $updateFields[$field],
                "cars.{$field} must be cleared so the previous owner's value cannot survive the transfer"
            );
        }

        // website is cleared to null, not '', since #1448 made CarValidator's
        // website case null-passthrough (CLEARABLE_FIELDS) rather than
        // dropping the key — see #1448 for why '' and null aren't yet a
        // consistent "cleared" signal across all OWNER_IDENTITY_FIELDS.
        $this->assertArrayHasKey(
            'website',
            $updateFields,
            'cars.website must be written on transfer, not dropped by CarValidator'
        );
        $this->assertNull(
            $updateFields['website'],
            "cars.website must be cleared so the previous owner's value cannot survive the transfer"
        );

        $this->assertSame('', $historyFields['email'] ?? null);
        $this->assertSame('', $historyFields['city'] ?? null);
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
