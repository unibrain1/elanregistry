<?php

declare(strict_types=1);

namespace ElanRegistry\Database;

use DB;
use ElanRegistry\DatabaseInterface;

/**
 * DbAdapter - Thin adapter exposing the real UserSpice `\DB` as a DatabaseInterface
 *
 * Wraps the `\DB` singleton so production code can type its collaborator as
 * `DatabaseInterface` while still running against the real database. Every
 * method is a 1:1 delegation with no logic or translation — the wrapped `\DB`
 * already behaves exactly as `DatabaseInterface` documents.
 *
 * Obtain the shared instance via the global `dbi()` helper. The ambient `$db`
 * global is never wrapped, so upstream UserSpice code that type-hints `\DB`
 * directly keeps working.
 *
 * The only deviation from a bare pass-through is that `query()` and `get()`
 * return `$this` (the adapter) rather than the wrapped `\DB`, so fluent chains
 * such as `$adapter->query($sql)->error()` stay on the interface.
 *
 * @package ElanRegistry\Database
 * @since v2.29.1
 * @see https://github.com/elan-registry/registry/issues/1585
 */
class DbAdapter implements DatabaseInterface
{
    public function __construct(private readonly DB $db) {}

    /**
     * @param array<mixed> $params
     */
    public function query(string $sql, array $params = []): self
    {
        $this->db->query($sql, $params);
        return $this;
    }

    /**
     * The wrapped `\DB::get()` delegates to `action()`, which returns the `\DB`
     * instance on success or the literal `false` on failure — so an identity
     * check on the returned value is the only reliable success test.
     *
     * @param array<mixed> $where
     */
    public function get(string $table, array $where): self|false
    {
        if ($this->db->get($table, $where) === false) {
            return false;
        }
        return $this;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function insert(string $table, array $fields = [], bool $update = false): bool
    {
        return $this->db->insert($table, $fields, $update);
    }

    /**
     * @param array<mixed>|int $id
     * @param array<string, mixed> $fields
     */
    public function update(string $table, array|int $id, array $fields): bool
    {
        return $this->db->update($table, $id, $fields);
    }

    /**
     * Mirrors `get()`: the wrapped `\DB::delete()` delegates to `action()`, which
     * returns the `\DB` instance on success or the literal `false` on failure.
     *
     * @param array<mixed>|int $where
     */
    public function delete(string $table, array|int $where): self|false
    {
        if ($this->db->delete($table, $where) === false) {
            return false;
        }
        return $this;
    }

    /**
     * @phpstan-impure
     */
    public function error(): bool
    {
        return $this->db->error();
    }

    /**
     * @phpstan-impure
     */
    public function errorString(): string
    {
        return $this->db->errorString();
    }

    /**
     * @phpstan-impure
     * @return array<int, mixed> PDO errorInfo triple
     */
    public function errorInfo(): array
    {
        return $this->db->errorInfo();
    }

    /**
     * @phpstan-impure
     */
    public function count(): int
    {
        return $this->db->count();
    }

    /**
     * @phpstan-impure
     * @return ($assoc is true ? array<string, mixed>|array{} : \stdClass|array{})
     */
    public function first(bool $assoc = false): array|object
    {
        return $this->db->first($assoc);
    }

    /**
     * @phpstan-impure
     * @return ($assoc is true ? array<int, array<string, mixed>> : array<int, \stdClass>)
     */
    public function results(bool $assoc = false): array
    {
        return $this->db->results($assoc);
    }

    /**
     * @phpstan-impure
     */
    public function lastId(): int
    {
        return $this->db->lastId();
    }

    /**
     * @return bool True on success
     */
    public function beginTransaction(): bool
    {
        return $this->db->beginTransaction();
    }

    /**
     * @return bool True on success
     */
    public function commit(): bool
    {
        return $this->db->commit();
    }

    /**
     * @return bool True on success
     */
    public function rollBack(): bool
    {
        return $this->db->rollBack();
    }

    /**
     * @phpstan-impure
     */
    public function inTransaction(): bool
    {
        return $this->db->inTransaction();
    }
}
