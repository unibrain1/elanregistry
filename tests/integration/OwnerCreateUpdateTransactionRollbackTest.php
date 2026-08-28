<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\DatabaseInterface;
use ElanRegistry\Owner;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration regression test for Owner::create()/update()'s transaction
 * integrity (#1505 PR B).
 *
 * Before this fix, create()/update() wrapped their writes in raw
 * `$this->_db->query("START TRANSACTION")` / "COMMIT" / "ROLLBACK" string
 * calls inside `catch (Exception $e)` blocks. A PHP-level \Error or
 * \TypeError thrown mid-transaction — not a plain \Exception — would skip
 * the catch entirely, leaving any partial writes uncommitted-but-not-rolled-
 * back. These tests inject a DatabaseInterface stub whose insert()/update()
 * throws \TypeError instead of returning false, and assert the real PDO
 * transaction (via Owner's beginTransaction()/commit()/rollback() wrappers,
 * mirroring CarRepository's pattern) is rolled back — something the old
 * `catch (Exception $e)` could not guarantee.
 *
 * Mirrors CarCreateRepositoryFailureTest / CarUpdateRepositoryFailureTest's
 * pattern: real class, stubbed collaborator, DB-backed assertions. Unlike
 * those tests (which stub Car's CarRepository), Owner has no swappable
 * repository — DatabaseInterface is injected directly via the constructor
 * — so this test stubs DatabaseInterface itself rather than using Reflection.
 *
 * @see usersc/classes/Owner.php Owner::create(), Owner::update()
 */
#[Group('integration')]
#[Group('owner')]
final class OwnerCreateUpdateTransactionRollbackTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    /**
     * A DatabaseInterface proxy backed by the real connection for
     * beginTransaction()/commit()/rollBack()/inTransaction() (and all other
     * methods), but with insert()/update() overridden to throw \TypeError —
     * simulating a PHP-level error (not a plain \Exception) mid-transaction.
     *
     * DatabaseInterface has no concrete base class to inherit real behavior
     * from, so every method is proxied to the real connection explicitly.
     */
    private function dbThrowingTypeErrorOnInsert(): DatabaseInterface
    {
        return new class ($this->db) implements DatabaseInterface {
            public function __construct(private DatabaseInterface $real)
            {
            }
            public function query(string $sql, array $params = []): DatabaseInterface
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
                throw new \TypeError('Simulated mid-transaction PHP error during insert()');
            }
            public function update(string $table, array|int $id, array $fields): bool
            {
                throw new \TypeError('Simulated mid-transaction PHP error during update()');
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
     * Core assertion: a \TypeError thrown mid-transaction during create()'s
     * user insert triggers a real rollback (inTransaction() is false
     * afterward), proving the \Throwable catch + checked
     * beginTransaction()/commit()/rollback() wrappers work where the old
     * `catch (Exception $e)` around raw SQL strings could not.
     */
    public function testCreateRollsBackOnMidTransactionTypeError(): void
    {
        $db = $this->dbThrowingTypeErrorOnInsert();
        $owner = new Owner(null, $db);

        $this->expectException(\TypeError::class);

        try {
            $owner->create([
                'fname' => 'Rollback',
                'lname' => 'Test',
                'email' => 'rollback-create-' . uniqid() . '@example.com',
            ]);
        } finally {
            $this->assertFalse(
                $db->inTransaction(),
                'A mid-transaction \TypeError during create() must trigger rollback, leaving no open transaction'
            );
        }
    }

    /**
     * Core assertion: a \TypeError thrown mid-transaction during update()'s
     * user update triggers a real rollback.
     */
    public function testUpdateRollsBackOnMidTransactionTypeError(): void
    {
        $userId = $this->createTestUser();

        $db = $this->dbThrowingTypeErrorOnInsert();
        $owner = new Owner(null, $db);

        $this->expectException(\TypeError::class);

        try {
            $owner->update([
                'id'    => $userId,
                'fname' => 'RollbackUpdate',
            ]);
        } finally {
            $this->assertFalse(
                $db->inTransaction(),
                'A mid-transaction \TypeError during update() must trigger rollback, leaving no open transaction'
            );
        }
    }
}
