<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * DatabaseInterface - Typed contract for the UserSpice `\DB` methods this project uses
 *
 * A deliberately narrow interface covering exactly the `\DB` methods production
 * code calls — nothing more. It is extracted so unit tests can build small,
 * purpose-built test doubles without needing the real `\DB` class (which opens a
 * live PDO connection in its constructor and `die()`s if it cannot) and without a
 * shared global mock shell that every test must agree on.
 *
 * The signatures below describe the *real* runtime behaviour of `\DB`, which is
 * untyped at the PHP level but well defined in practice. Notable behaviours that
 * are not obvious from the method names alone:
 *
 * - `query()` never throws on a failed statement and always returns the database
 *   object for chaining — a failure is reported by `error()` returning true, so
 *   callers must check `error()` rather than relying on exceptions or a falsy return.
 * - `get()` returns `false` when the underlying query fails; on success it
 *   returns the database object for chaining. A malformed WHERE array throws
 *   `InvalidArgumentException` rather than returning `false`.
 * - `delete()` follows the same pattern as `get()`: the database object on
 *   success, the literal `false` on failure.
 * - `first()` returns an empty array when there are no rows — never `null`.
 * - `results()` returns an empty array when there are no rows — never `null`.
 * - `insert()` and `update()` return a plain bool, not the database object.
 * - Result state (`count()`, `first()`, `results()`, `lastId()`, `error()`) always
 *   reflects the most recent `query()`/`get()`/`insert()`/`update()` call on that
 *   same instance.
 *
 * @package ElanRegistry
 * @since v2.29.1
 * @see https://github.com/elan-registry/registry/issues/1585
 */
interface DatabaseInterface
{
    /**
     * Run a prepared statement, binding parameters positionally.
     *
     * Never throws on statement failure and always returns the instance for
     * chaining — check `error()` afterwards to detect failure.
     *
     * @param string $sql SQL with `?` placeholders
     * @param array<mixed> $params Values bound to the placeholders, in order
     * @return self This instance, for chaining
     */
    public function query(string $sql, array $params = []): self;

    /**
     * SELECT * from a table using a UserSpice WHERE-clause array.
     *
     * Returns `false` when the underlying query fails. A malformed WHERE array
     * throws `InvalidArgumentException` rather than returning `false`.
     *
     * @param string $table Table name
     * @param array<mixed> $where UserSpice WHERE array, e.g. `['id', '=', 5]`
     * @return self|false This instance on success, false on a failed query
     * @throws \InvalidArgumentException If the WHERE array is malformed
     */
    public function get(string $table, array $where): self|false;

    /**
     * Insert one or more rows.
     *
     * @param string $table Table name
     * @param array<string, mixed> $fields Column => value pairs (a value may be an
     *                                     array to insert multiple rows at once)
     * @param bool $update Append `ON DUPLICATE KEY UPDATE` for an upsert
     * @return bool True on success, false on failure
     */
    public function insert(string $table, array $fields = [], bool $update = false): bool;

    /**
     * Update rows by primary key or by a UserSpice WHERE-clause array.
     *
     * @param string $table Table name
     * @param array<mixed>|int $id Primary key value, or a UserSpice WHERE array
     * @param array<string, mixed> $fields Column => value pairs to set
     * @return bool True on success, false on failure (including empty $fields)
     */
    public function update(string $table, array|int $id, array $fields): bool;

    /**
     * DELETE rows matching a UserSpice WHERE-clause array, or by integer primary key.
     *
     * Mirrors `get()`: `\DB::delete()` delegates to `action()`, which returns the
     * database object on success or the literal `false` on failure — so an identity
     * check on the returned value is the only reliable success test.
     *
     * @param string $table Table name
     * @param array<mixed>|int $where UserSpice WHERE array, e.g. `['id', '=', 5]`, or an id
     * @return self|false This instance on success, false on failure
     */
    public function delete(string $table, array|int $where): self|false;

    /**
     * Whether the most recent query failed.
     *
     * @phpstan-impure
     * @return bool True if the last query errored
     */
    public function error(): bool;

    /**
     * Human-readable description of the most recent error.
     *
     * Always returns a string, even when the last query succeeded.
     *
     * @phpstan-impure
     * @return string Formatted as `ERROR #<sqlstate>: <driver message>`
     */
    public function errorString(): string;

    /**
     * Raw PDO error info for the most recent query.
     *
     * Callers that need to distinguish specific database errors (e.g. MySQL
     * 1146 / SQLSTATE 42S02 "table not found") read this rather than parsing
     * `errorString()`.
     *
     * @phpstan-impure
     * @return array<int, mixed> PDO errorInfo triple: `[SQLSTATE, driver code, message]`
     */
    public function errorInfo(): array;

    /**
     * Row count from the most recent query.
     *
     * @phpstan-impure
     * @return int Rows returned by a SELECT, or rows affected by a write
     */
    public function count(): int;

    /**
     * First row of the most recent result set.
     *
     * Returns an empty array when there are no rows — never null. Rows are
     * fetched with `PDO::FETCH_OBJ`, so a non-assoc row is always a `\stdClass`.
     *
     * @phpstan-impure
     * @param bool $assoc True for an associative array, false for an object
     * @return ($assoc is true ? array<string, mixed>|array{} : \stdClass|array{}) The first row, or `[]` if there are none
     */
    public function first(bool $assoc = false): array|object;

    /**
     * All rows from the most recent result set.
     *
     * Returns an empty array when there are no rows — never null. Rows are
     * fetched with `PDO::FETCH_OBJ`, so non-assoc rows are always `\stdClass`.
     *
     * @phpstan-impure
     * @param bool $assoc True for associative arrays, false for objects
     * @return ($assoc is true ? array<int, array<string, mixed>> : array<int, \stdClass>) Rows, or `[]` if there are none
     */
    public function results(bool $assoc = false): array;

    /**
     * Auto-increment id generated by the most recent insert.
     *
     * @phpstan-impure
     * @return int Last insert id, or 0 if the last query generated none
     */
    public function lastId(): int;

    /**
     * Begin a transaction on the underlying connection.
     *
     * @return bool True on success
     */
    public function beginTransaction(): bool;

    /**
     * Commit the active transaction.
     *
     * @return bool True on success
     */
    public function commit(): bool;

    /**
     * Roll back the active transaction.
     *
     * @return bool True on success
     */
    public function rollBack(): bool;

    /**
     * Whether a transaction is currently active.
     *
     * @phpstan-impure
     * @return bool True if inside a transaction
     */
    public function inTransaction(): bool;
}
