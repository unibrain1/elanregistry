<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeDatabase;

require_once __DIR__ . '/../../../app/admin/includes/account-cleanup-helpers.php';

/**
 * Unit tests for restoreArchivedAccount() in account-cleanup-helpers.php.
 *
 * Uses RestoreArchivedAccountFakeDatabase (declared at the foot of this file); SQL
 * correctness delegated to integration tests.
 *
 * @see ArchiveAndRestoreIntegrationTest
 */
#[Group('fast')]
#[Group('unit')]
#[Group('admin')]
final class RestoreArchivedAccountTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a DB double for restoreArchivedAccount().
     *
     * @param object|null $archiveRow  Row returned by the initial SELECT, or null to simulate "not found"
     * @param bool        $insertOk   Whether insert() should succeed
     * @param int         $lastId     Value returned by lastId()
     * @param bool        $queryError Whether the first query() should set an error flag
     */
    private function makeDb(
        ?object $archiveRow,
        bool $insertOk = true,
        int $lastId = 42,
        bool $queryError = false
    ): RestoreArchivedAccountFakeDatabase {
        return new RestoreArchivedAccountFakeDatabase($archiveRow, $insertOk, $lastId, $queryError);
    }

    private function archiveRow(): object
    {
        return (object)[
            'id'             => 10,
            'email'          => 'test@example.com',
            'username'       => 'testuser',
            'fname'          => 'Test',
            'lname'          => 'User',
            'join_date'      => '2024-01-01 00:00:00',
            'last_login'     => null,
            'logins'         => 0,
            'email_verified' => 0,
            'city'           => null,
            'state'          => null,
            'country'        => null,
            'bio'            => null,
            'website'        => null,
        ];
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    #[Group('fast')] #[Group('unit')] #[Group('admin')]
    public function testThrowsWhenDbErrorOnLookup(): void
    {
        $db = $this->makeDb(null, true, 42, queryError: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/DB error reading archive row/');
        restoreArchivedAccount($db, 10, 1);
    }

    #[Group('fast')] #[Group('unit')] #[Group('admin')]
    public function testThrowsWhenArchiveRowNotFound(): void
    {
        $db = $this->makeDb(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found or already restored/');
        restoreArchivedAccount($db, 99, 1);
    }

    #[Group('fast')] #[Group('unit')] #[Group('admin')]
    public function testThrowsAndRollsBackOnInsertFailure(): void
    {
        $db = $this->makeDb($this->archiveRow(), insertOk: false);

        try {
            restoreArchivedAccount($db, 10, 1);
            $this->fail('Expected RuntimeException on insert failure');
        } catch (RuntimeException) {
            $this->assertSame(1, $db->rollBackCalls);
            $this->assertSame(0, $db->commitCalls);
        }
    }

    #[Group('fast')] #[Group('unit')] #[Group('admin')]
    public function testThrowsWhenLastIdIsZero(): void
    {
        $db = $this->makeDb($this->archiveRow(), insertOk: true, lastId: 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/returned no ID/');
        restoreArchivedAccount($db, 10, 1);
    }

    #[Group('fast')] #[Group('unit')] #[Group('admin')]
    public function testReturnsNewUserIdOnSuccess(): void
    {
        $db = $this->makeDb($this->archiveRow(), insertOk: true, lastId: 42);

        $result = restoreArchivedAccount($db, 10, 1);

        $this->assertSame(42, $result, 'Must return the new user ID from lastId()');
        $this->assertSame(1, $db->commitCalls);
        $this->assertSame(0, $db->rollBackCalls);
    }
}

/**
 * DB double for restoreArchivedAccount(): the first query() yields the archive row
 * (or an error), later queries succeed silently, and commit()/rollBack() are counted.
 *
 * Deliberately a *named* class rather than the anonymous `new class extends
 * FakeDatabase` it replaces: PHPStan reports `impureMethod.pure` when an anonymous
 * class overrides one of DatabaseInterface's `@phpstan-impure` methods (`first()`,
 * `lastId()`, `error()`, `errorString()` here) with a side-effect-free body, because
 * an anonymous class can never be extended to add the side effect later. A named
 * class is exempt.
 */
class RestoreArchivedAccountFakeDatabase extends FakeDatabase
{
    public int $commitCalls = 0;
    public int $rollBackCalls = 0;

    private bool $errorFlag = false;
    private bool $firstQueryDone = false;
    private ?object $currentRow = null;

    /**
     * @param object|null $archiveRow Row returned by the initial SELECT, or null for "not found"
     * @param bool $insertOk Value returned by insert()
     * @param int $lastIdValue Value returned by lastId()
     * @param bool $queryError Whether the first query() should flag an error
     */
    public function __construct(
        private readonly ?object $archiveRow,
        private readonly bool $insertOk,
        private readonly int $lastIdValue,
        private readonly bool $queryError
    ) {
    }

    /**
     * @param array<mixed> $params
     */
    public function query(string $sql, array $params = []): self
    {
        if (!$this->firstQueryDone) {
            $this->firstQueryDone = true;
            if ($this->queryError) {
                $this->errorFlag = true;
                $this->currentRow = null;
                return $this;
            }
            $this->currentRow = $this->archiveRow;
            return $this;
        }
        // Subsequent queries (UPDATE, etc.) succeed silently
        $this->errorFlag = false;
        $this->currentRow = null;
        return $this;
    }

    /**
     * Mirrors real \DB::first(): an empty result set is `[]`, never null.
     *
     * @return array<string, mixed>|object
     */
    public function first(bool $assoc = false): array|object
    {
        return $this->currentRow ?? [];
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function insert(string $table, array $fields = [], bool $update = false): bool
    {
        return $this->insertOk;
    }

    /**
     * @return int The constructor's $lastIdValue
     */
    public function lastId(): int
    {
        return $this->lastIdValue;
    }

    /**
     * @return bool True only when the first query() was told to fail
     */
    public function error(): bool
    {
        return $this->errorFlag;
    }

    /**
     * @return string Fixed placeholder text for the error path
     */
    public function errorString(): string
    {
        return 'mock error';
    }

    /**
     * @return bool Always true
     */
    public function beginTransaction(): bool
    {
        return true;
    }

    /**
     * @return bool Always true; increments $commitCalls
     */
    public function commit(): bool
    {
        $this->commitCalls++;
        return true;
    }

    /**
     * @return bool Always true; increments $rollBackCalls
     */
    public function rollBack(): bool
    {
        $this->rollBackCalls++;
        return true;
    }
}
