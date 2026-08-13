<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\Database\DbAdapter;
use PHPUnit\Framework\Attributes\Group;

/**
 * Contract test: DbAdapter behaves the way DatabaseInterface documents, against a real \DB.
 *
 * DbAdapter is a 1:1 delegation wrapper with no logic of its own, so nothing here
 * can fail because of a bug in the adapter. What it *can* catch is drift: the
 * adapter's return types (`self` from query(), `self|false` from get(), `bool` from
 * insert()/update()) and DatabaseInterface's documented behaviours (`first()` yields
 * `[]` and never null, `results()` always an array, `\stdClass` vs. plain array for
 * the two fetch modes) are hardcoded assumptions about what the real UserSpice `\DB`
 * does. A UserSpice upgrade that changes `\DB` — throwing where it used to set
 * `error()`, returning null where it used to return `[]` — would silently break every
 * production caller typed against DatabaseInterface while unit tests, which use
 * purpose-built doubles rather than the real class, keep passing. These assertions
 * are the only place the assumptions are checked against the actual framework.
 *
 * Fixtures use IntegrationTestCase's tracked helpers (createTestUser(),
 * createTestCar(), trackCarId()) so every row is removed by the base tearDown().
 *
 * @issue 1585
 */
#[Group('integration')]
final class DbAdapterContractTest extends IntegrationTestCase
{
    /** Table name that is syntactically valid but cannot exist — used to force query failures. */
    private const MISSING_TABLE = 'elan_registry_no_such_table_1585';

    /** Owner for any car fixture this test creates. */
    private int $userId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->userId = $this->createTestUser();
    }

    /**
     * Narrow IntegrationTestCase::$db (declared untyped, so `mixed` to static analysis)
     * to the concrete adapter under test. The base class already wraps the real \DB
     * singleton, so this is the same connection the fixture helpers and tearDown() use —
     * no second adapter, and therefore no second connection, is created.
     */
    private function adapter(): DbAdapter
    {
        $adapter = $this->db;
        self::assertInstanceOf(
            DbAdapter::class,
            $adapter,
            'IntegrationTestCase::$db must be a DbAdapter for this contract test to be meaningful'
        );

        return $adapter;
    }

    // ---------------------------------------------------------------- query()

    public function test_query_returnsSameAdapterInstance_forChaining(): void
    {
        $db = $this->adapter();

        $returned = $db->query('SELECT 1 AS one');

        $this->assertSame($db, $returned, 'query() must return the adapter itself so chains stay on the interface');
        $this->assertFalse($returned->error(), 'A valid statement must not set the error flag');
        $this->assertSame(1, $returned->count());
    }

    public function test_query_reportsFailureViaError_insteadOfThrowing(): void
    {
        $db = $this->adapter();

        $returned = $db->query('SELECT id FROM ' . self::MISSING_TABLE);

        $this->assertSame($db, $returned, 'A failed statement must still return the adapter, not null or false');
        $this->assertTrue($returned->error(), 'A failed statement must be reported through error()');
        $this->assertNotSame('', $returned->errorString());
        $this->assertSame([], $returned->results(), 'A failed statement must leave an empty result set');
    }

    public function test_query_clearsErrorState_onNextSuccessfulStatement(): void
    {
        $db = $this->adapter();

        $db->query('SELECT id FROM ' . self::MISSING_TABLE);
        $this->assertTrue($db->error());

        $db->query('SELECT 1 AS one');
        $this->assertFalse($db->error(), 'Error state must reflect only the most recent statement');
    }

    // ---------------------------------------------------------------- first()

    public function test_first_returnsObjectOrArray_dependingOnAssocFlag(): void
    {
        $db = $this->adapter();
        $carId = $this->createTestCar($this->userId, ['color' => 'Carnival Red']);

        $db->query('SELECT id, color FROM cars WHERE id = ?', [$carId]);
        $asObject = $db->first();

        $db->query('SELECT id, color FROM cars WHERE id = ?', [$carId]);
        $asArray = $db->first(true);

        $this->assertInstanceOf(stdClass::class, $asObject, 'first(false) must yield a \stdClass row');
        $this->assertSame($carId, (int) $asObject->id);
        $this->assertSame('Carnival Red', $asObject->color);

        $this->assertIsArray($asArray, 'first(true) must yield a plain array row');
        $this->assertSame(['id', 'color'], array_keys($asArray));
        $this->assertSame($carId, (int) $asArray['id']);
        $this->assertSame('Carnival Red', $asArray['color']);
    }

    public function test_first_returnsEmptyArray_whenNoRowsMatch(): void
    {
        $db = $this->adapter();

        $db->query('SELECT id FROM cars WHERE id = ?', [0]);
        $this->assertFalse($db->error(), 'The probe query itself must succeed, or the assertions below pass vacuously');

        $this->assertSame([], $db->first(), 'first(false) must return [] — never null — with zero rows');
        $this->assertSame([], $db->first(true), 'first(true) must return [] — never null — with zero rows');
    }

    // -------------------------------------------------------------- results()

    public function test_results_returnsRowsInBothModes_whenRowsMatch(): void
    {
        $db = $this->adapter();
        $firstCarId = $this->createTestCar($this->userId);
        $secondCarId = $this->createTestCar($this->userId);

        $db->query('SELECT id FROM cars WHERE id IN (?, ?) ORDER BY id', [$firstCarId, $secondCarId]);
        $asObjects = $db->results();

        $db->query('SELECT id FROM cars WHERE id IN (?, ?) ORDER BY id', [$firstCarId, $secondCarId]);
        $asArrays = $db->results(true);

        $this->assertCount(2, $asObjects);
        $this->assertContainsOnlyInstancesOf(stdClass::class, $asObjects);
        $this->assertSame([$firstCarId, $secondCarId], array_map(static fn(stdClass $row): int => (int) $row->id, $asObjects));

        $this->assertCount(2, $asArrays);
        $this->assertIsArray($asArrays[0]);
        $this->assertSame([$firstCarId, $secondCarId], array_map(static fn(array $row): int => (int) $row['id'], $asArrays));
    }

    public function test_results_returnsEmptyArray_whenNoRowsMatch(): void
    {
        $db = $this->adapter();

        $db->query('SELECT id FROM cars WHERE id = ?', [0]);
        $this->assertFalse($db->error(), 'The probe query itself must succeed, or the assertions below pass vacuously');

        $this->assertSame([], $db->results(), 'results(false) must return [] — never null — with zero rows');
        $this->assertSame([], $db->results(true), 'results(true) must return [] — never null — with zero rows');
        $this->assertSame(0, $db->count());
    }

    // ------------------------------------------------------------------ get()

    public function test_get_returnsSameAdapterInstance_onSuccessfulLookup(): void
    {
        $db = $this->adapter();
        $carId = $this->createTestCar($this->userId);

        $returned = $db->get('cars', ['id', '=', $carId]);

        $this->assertSame($db, $returned, 'A successful get() must return the adapter, matching \DB::action()');
        $this->assertSame(1, $db->count());
        $this->assertSame($carId, (int) $db->first()->id);
    }

    /**
     * The reachable `false` return. `\DB::action()` produces `false` from two branches:
     * an unusable WHERE clause, or a query that errors. Every unusable-WHERE branch in
     * `\DB::_calcWhere()` throws InvalidArgumentException before `$is_ok = false` can be
     * observed (see the sibling test below), so a failing query is the only path that
     * actually returns `false` today.
     */
    public function test_get_returnsFalse_whenUnderlyingQueryFails(): void
    {
        $db = $this->adapter();

        $returned = $db->get(self::MISSING_TABLE, ['id', '=', 1]);

        $this->assertFalse($returned, 'get() must return false — not throw, not return the adapter — on a failed query');
        $this->assertTrue($db->error());
    }

    /**
     * DatabaseInterface documents an invalid WHERE clause as a `false` return, but the
     * real `\DB::_calcWhere()` throws first. Pinning the actual behaviour here means a
     * future UserSpice change in either direction — throwing where it used to, or
     * falling through to `false` — is caught rather than silently changing how every
     * `get()` caller must handle bad input.
     */
    public function test_get_throwsInvalidArgumentException_onMalformedWhereClause(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->adapter()->get('cars', ['id', 'NOT_AN_OPERATOR', 1]);
    }

    // ----------------------------------------- insert() / update() round trip

    public function test_insertUpdateAndLastId_roundTripAgainstRealRow(): void
    {
        $db = $this->adapter();
        $chassis = 'T' . substr(uniqid(), -10);

        $inserted = $db->insert('cars', [
            'user_id' => $this->userId,
            'year' => 1968,
            'model' => 'Elan S4',
            'series' => 'S4',
            'variant' => 'SE',
            'type' => 'FHC',
            'chassis' => $chassis,
            'color' => 'Lagoon Blue',
            'ctime' => date('Y-m-d H:i:s'),
        ]);

        $this->assertTrue($inserted, 'insert() must return a plain bool, not the database object');

        $carId = $db->lastId();
        $this->assertGreaterThan(0, $carId, 'lastId() must expose the auto-increment id from the insert');
        $this->trackCarId($carId);

        $db->query('SELECT chassis FROM cars WHERE id = ?', [$carId]);
        $this->assertSame(1, $db->count(), 'lastId() must identify the row that was just inserted');
        $this->assertSame($chassis, $db->first()->chassis);

        $updated = $db->update('cars', $carId, ['color' => 'Pistachio Green']);
        $this->assertTrue($updated, 'update() must return a plain bool, not the database object');

        $this->assertSame($db, $db->get('cars', ['id', '=', $carId]));
        $this->assertSame('Pistachio Green', $db->first()->color, 'The update must have persisted');
    }

    // ---------------------------------------------------------- transactions

    public function test_transactionMethods_trackConnectionState(): void
    {
        $db = $this->adapter();

        $this->assertFalse($db->inTransaction(), 'The connection must not already be inside a transaction');

        try {
            $this->assertTrue($db->beginTransaction());
            $this->assertTrue($db->inTransaction(), 'inTransaction() must report an open transaction');
            $this->assertTrue($db->commit());
            $this->assertFalse($db->inTransaction(), 'inTransaction() must clear after commit()');

            $this->assertTrue($db->beginTransaction());
            $this->assertTrue($db->inTransaction());
            $this->assertTrue($db->rollBack());
            $this->assertFalse($db->inTransaction(), 'inTransaction() must clear after rollBack()');
        } finally {
            // Never leak an open transaction into the next test if an assertion above fails.
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }
    }

    public function test_rollBack_discardsWritesMadeInsideTransaction(): void
    {
        $db = $this->adapter();
        $chassis = 'T' . substr(uniqid(), -10);

        $db->beginTransaction();
        try {
            $this->assertTrue($db->insert('cars', [
                'user_id' => $this->userId,
                'year' => 1971,
                'model' => 'Elan S4',
                'series' => 'S4',
                'variant' => 'SE',
                'type' => 'DHC',
                'chassis' => $chassis,
                'color' => 'Yellow',
                'ctime' => date('Y-m-d H:i:s'),
            ]));

            // Tracked before the rollback so the row is still cleaned up if the rollback
            // itself is what has drifted and the insert survives.
            $this->trackCarId($db->lastId());

            $this->assertTrue($db->rollBack());
        } finally {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
        }

        $db->query('SELECT id FROM cars WHERE chassis = ?', [$chassis]);
        $this->assertFalse($db->error(), 'The verification query itself must succeed');
        $this->assertSame(0, $db->count(), 'rollBack() must discard the insert made inside the transaction');
    }
}
