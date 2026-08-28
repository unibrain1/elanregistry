# Issue #1505 (PR B/2): Owner transaction integrity + new OwnerDatabaseException

**Branch:** `issue/1505-owner-transaction-integrity`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented, re-reviewed (Go) — pending commit/PR

## Context

PR A (#1816, merged) fixed `CarRepository`/`CarModel`'s silent DB-error
swallowing. This PR covers the remaining scope of issue #1505: `Owner`'s
transaction integrity and its own silent-failure methods (`find()`,
`getCarsOwned()`, `getOwnershipHistory()`).

Research from earlier this session established:

- `Owner::create()`/`update()` (`usersc/classes/Owner.php:120-269`) wrap
  their DB writes in raw `$this->_db->query("START TRANSACTION")` /
  `"COMMIT"` / `"ROLLBACK"` string calls, whose results are never checked,
  inside `catch (Exception $e)` blocks (not `\Throwable`) — a `\Error`/
  `\TypeError` mid-transaction skips ROLLBACK entirely, and if
  `START TRANSACTION` itself silently failed, the block may already be
  running in autocommit, making any later ROLLBACK a no-op.
- `CarRepository` already has the target pattern: `beginTransaction()`/
  `commit()`/`rollback()` wrapper methods (`CarRepository.php:421-466`)
  using an ownership-flag (`private bool $transactionOwner`) to safely
  nest inside an outer transaction, delegating to native PDO
  `beginTransaction()/commit()/rollBack()/inTransaction()` via the same
  `DatabaseInterface` type `Owner` already has (`Owner::$_db`).
- `Owner::find()`, `getCarsOwned()`, `getOwnershipHistory()` **already**
  check `error()` and log via `LOG_CATEGORY_DATABASE_ERROR` on current
  `main` — the issue's literal "missing check" claim is stale. The real
  remaining gap: all three still collapse "DB failed" and "genuinely
  empty/not-found" into the identical return shape (`false`/`false`,
  `[]`/`[]`, `[]`/`[]`), so callers can't distinguish them without reading
  logs.
- `Owner::searchOwners()` (same file) is the one existing method that
  already throws (`OwnerSearchException`) instead of swallowing — the
  in-file precedent to match.
- No `OwnerDatabaseException` class exists yet. `#1654` (adding an
  `OwnerException` abstract base) is explicitly sequenced *after* this
  issue specifically because it expects these exceptions to already exist.
- Blast radius confirmed small during PR A planning: `find()` has 4
  callers (constructor, 2 post-write reloads inside `Owner` itself, 1
  admin site), `getCarsOwned()` has 2 external callers plus 1 internal
  (`syncLocationToCars()`), `getOwnershipHistory()` has 1 caller.
  `Owner::__construct()` calls `find()` and ignores its return value —
  if `find()` throws, `new Owner($id)` throws, which is strictly better
  than today's silent-null-then-surprising-later-failure behavior.
- `syncLocationToCars()`'s `if (empty($ownedCars)) { return 0; }` line
  (`Owner.php:516-518`) is a direct, immediate consumer of
  `getCarsOwned()`'s contract — once that throws instead of returning `[]`
  on failure, this line needs an explicit decision, not a silent
  behavior change.

## Bug Escape Analysis

- **Root cause:** Two independent gaps, both long-standing: (1) `create()`/
  `update()`'s raw-string transaction calls were never updated to the PDO
  wrapper pattern `CarRepository` introduced later; (2) `find()`/
  `getCarsOwned()`/`getOwnershipHistory()` were given `error()` checks and
  logging at some point but never upgraded to throw, unlike their sibling
  `searchOwners()`.
- **Testing gap:** Zero tests anywhere reference `Owner::create()`/
  `update()`'s transaction/rollback behavior (confirmed via grep across
  `tests/` for `Owner` + `TRANSACTION`/`ROLLBACK`/`inTransaction` keywords
  — no hits). `OwnerProfileTest::testFindReturnsFalseOnDatabaseError()`
  exists but documents the current collapsed-outcome behavior as
  seemingly intentional (its not-found sibling test asserts the identical
  result) — this test needs rewriting, not just extending, once `find()`
  starts throwing.
- **Preventive measure:** New tests for `create()`/`update()` transaction
  rollback under a mid-transaction `\Error`/`\TypeError` (proving the
  `\Throwable` catch actually rolls back, which the current `Exception`
  catch cannot). New/rewritten tests for `find()`/`getCarsOwned()`/
  `getOwnershipHistory()` asserting `OwnerDatabaseException` on DB failure
  instead of the collapsed sentinel return.

## Database & Security Considerations

- No schema/migration changes.
- Transaction correctness is the core of this PR: catching only
  `Exception` today means a PHP-level `\Error` (e.g. `\TypeError` from a
  bad argument somewhere in the write path) skips `ROLLBACK` entirely,
  potentially leaving a half-written user+profile pair uncommitted-but-
  unrolled-back depending on connection state. Switching to
  `\Throwable` + checked `beginTransaction()`/`commit()`/`rollback()`
  (mirroring `CarRepository`) closes this.
- No new attack surface — this changes failure-handling only, not what
  triggers a query or who can reach it.

## Architecture & Design

**1. Transaction pattern** — give `Owner` its own
`beginTransaction()`/`commit()`/`rollback()` wrapper methods, identical
shape to `CarRepository`'s (ownership-flag nesting guard, delegating to
`$this->_db->beginTransaction()/commit()/rollBack()/inTransaction()`).
Replace `create()`/`update()`'s raw `query("START TRANSACTION")` etc.
with calls to these new wrappers, and change `catch (Exception $e)` →
`catch (\Throwable $e)` at both sites (`Owner.php:168`, `:256`).

**2. New `OwnerDatabaseException`** — mirrors `CarDatabaseException`'s
shape exactly (confirmed with user: create now rather than wait for
issue #1654's abstract base, which is sequenced after this issue precisely
because it expects this class to exist). Lives in
`usersc/classes/Exceptions/OwnerDatabaseException.php`, extends
`ElanRegistryException` directly (matching how `OwnerNotFoundException`
etc. currently do — no intermediate base yet).

**3. `find()`/`getCarsOwned()`/`getOwnershipHistory()`** — change the
existing `if ($this->_db->error()) { logger(...); return false/[]; }`
branches to `if ($this->_db->error()) { logger(...); throw new
OwnerDatabaseException(...); }`, matching `searchOwners()`'s existing
in-file precedent and `CarRepository`'s established message format
(`"ClassName::method failed for <context>: " . errorString()`).

**4. `syncLocationToCars()` fallout** — **confirmed with user:** let
`getCarsOwned()`'s new exception propagate uncaught. Consistent with PR
A's design precedent (`CarModel::exists()`'s exception propagates through
`CarValidator` rather than being absorbed) — a DB failure should surface
as a real exception, not silently collapse into "0 cars synced," which
looks identical to a legitimate no-op. No code change needed in
`syncLocationToCars()` itself beyond what naturally happens (the
`if (empty($ownedCars))` line simply stops being reachable on a DB
failure, since `getCarsOwned()` now throws before returning).

**5. Caller updates** — per the confirmed-small blast radius: `Owner`
constructor's `find()` call needs no change (already ignores the return
value; a throw there is a strict improvement, matching `Car`'s
constructor's equivalent behavior).

**Post-write reloads — confirmed with user: wrap defensively.** The 2
`find($userId)` reload calls inside `create()`/`update()` (after a
successful `COMMIT`) must not turn a successful write into an uncaught
exception for the caller if the reload itself hits a DB blip — a
different, lower-severity failure than the write failing. Wrap each in
its own `try/catch (OwnerDatabaseException $e)`, log via
`LOG_CATEGORY_DATABASE_ERROR`, and continue (matching PR A's
`Car::find()` treatment of its own subordinate `getHistory()`/
`getFactoryInfo()` lookups — same rationale, same shape).

The 1 admin caller (`app/admin/index.php:201`) and `getCarsOwned()`'s 2
external callers (`app/admin/includes/load-owner-info.php`,
`usersc/user_settings.php`) need audit at implementation time — same
category of check PR A did for `Car::find()`/`verify_car.php`, expect
similar findings (a caller with no try/catch that would regress from
"empty result" to a 500).

## Implementation Checklist

- [x] Create `OwnerDatabaseException` class, mirroring
      `CarDatabaseException`'s shape — `usersc/classes/Exceptions/OwnerDatabaseException.php`
      (parallel-safe)
- [x] Add `beginTransaction()`/`commit()`/`rollback()` wrapper methods to
      `Owner`, mirroring `CarRepository`'s exact pattern (ownership-flag
      nesting guard) — `usersc/classes/Owner.php`
- [x] Replace raw transaction SQL strings in `create()`/`update()` with
      the new wrapper methods; change `catch (Exception $e)` →
      `catch (\Throwable $e)` at both sites — `usersc/classes/Owner.php`.
      Removed now-unused `use Exception;` import (confirmed no other bare
      `Exception` reference remains in the file).
- [x] Change `find()`'s DB-error branch from `return false` to
      `throw new OwnerDatabaseException(...)` — `usersc/classes/Owner.php`
- [x] Change `getCarsOwned()`'s DB-error branch from `return []` to throw
      — `usersc/classes/Owner.php`
- [x] Change `getOwnershipHistory()`'s DB-error branch from `return []`
      to throw — `usersc/classes/Owner.php`
- [x] Wrap `create()`/`update()`'s post-write `find($userId)` reload calls
      in local `try/catch (OwnerDatabaseException $e)`, log via
      `LOG_CATEGORY_DATABASE_ERROR`, continue — matching PR A's
      `Car::find()` treatment of `getHistory()`/`getFactoryInfo()` —
      `usersc/classes/Owner.php`
- [x] Confirm `syncLocationToCars()` needs no code change (the
      `if (empty($ownedCars))` line simply becomes unreachable on DB
      failure now that `getCarsOwned()` throws first) — confirmed, added
      `@throws` docblock note only, no logic change
- [ ] Audit and fix (if needed) all real callers of the three changed
      read methods: `app/admin/index.php:201`,
      `app/admin/includes/load-owner-info.php`,
      `usersc/user_settings.php` — confirm none regress from "empty
      result" to an uncaught exception/500 without an intentional catch,
      following PR A's `Car::find()`/`verify_car.php` precedent
- [x] Rewrite `OwnerProfileTest::testFindReturnsFalseOnDatabaseError()`
      → `testFindThrowsOwnerDatabaseExceptionOnDatabaseError()` —
      `tests/unit/classes/OwnerProfileTest.php`. Confirmed its not-found
      sibling test (`testFindReturnsFalseWhenUserNotFound`) still
      correctly asserts the different, non-throwing outcome unchanged.
- [x] Add transaction-rollback tests for `create()`/`update()`: a
      mid-transaction `\TypeError`/`\Error` must trigger rollback (proving
      the `\Throwable` catch works where `Exception` couldn't) —
      `tests/integration/OwnerCreateUpdateTransactionRollbackTest.php`
      (new file, 2 tests, using a `DatabaseInterface` proxy delegating to
      the real integration connection except `insert()`/`update()`, which
      throw `\TypeError`)
- [x] Add DB-failure-branch tests for `getCarsOwned()`/`getOwnershipHistory()`,
      mirroring PR A's `CarRepositoryFindByOwnerFailureTest.php` pattern
      — `tests/integration/OwnerReadMethodsDatabaseFailureTest.php` (new
      file, 5 tests total)
- [x] Add test(s) for `syncLocationToCars()`'s new behavior — included in
      the same new file above, confirming the exception propagates rather
      than being swallowed
- [x] **Unplanned, found while auditing callers:** `usersc/user_settings.php`
      called `getCarsOwned()`/`syncLocationToCars()` with zero try/catch
      anywhere in the entire file — a DB blip would crash the whole
      settings-save request even after other fields already saved
      successfully. Fixed following PR A's `verify_car.php` precedent
      (wrap defensively, friendly message, log, continue) — confirmed
      in-scope per the plan's own escalation criteria (same class of
      regression PR A found and fixed).
- [x] Run `composer test:full`, verify pass — 1690 unit + 490 integration,
      all passing
- [x] PHPStan baseline hygiene: found `usersc/user_settings.php` carried 3
      pre-existing baseline entries. Investigated and fixed all 3 (user
      confirmed, not deferred): 2 were false positives from PHPStan not
      tracing `$master_account`/`$currentPage` globals set by
      `users/init.php` across the `require_once` boundary — fixed with an
      explicit `global` declaration. The third (`$profiledetails`) was a
      **genuine bug**: only assigned inside `if ($userQ->count() > 0)` but
      used unconditionally ~12 times later in the file — fixed by making
      the missing-profile branch fail loudly (log + friendly error + exit)
      instead of falling through into undefined-variable territory.
      Regenerated baseline (`composer phpstan:baseline`), confirmed only
      `user_settings.php`'s 3 entries were removed, whole-project PHPStan
      and full test suite still pass.
- [x] Run `senior-architect` review of the diff, address findings —
      **No-go on first pass**, 1 Critical: `usersc/account.php:26` called
      `new Owner($ownerId)` with no try/catch — the caller audit missed
      this fourth site, and every logged-in owner's account page would
      have uncaught-exceptioned on a DB blip. Fixed: wrapped in
      try/catch, degrading `$ownerData` to `null` (already handled by
      the existing `!== null` guard) and falling back `$owner` to an
      unloaded instance (so `getProfileQualityScore()` further down
      doesn't hit an undefined variable). Added
      `tests/unit/admin/AccountPageWiringTest.php`, mirroring PR A's
      `VerifyCarWiringTest.php` source-text pattern, validated
      non-vacuous by temporarily removing the fallback line and
      confirming the test fails. 1 Medium noted, not blocking:
      `create()`/`update()`'s `@throws` docblocks don't mention the
      rethrown `\Throwable` can be broader than the documented
      `OwnerCreationException`/`OwnerUpdateException` — deferred as
      non-functional (PHP doesn't enforce checked exceptions).
      While fixing the Critical finding, `account.php` also had 1
      pre-existing PHPStan baseline entry (unnecessary nullsafe
      operator) — investigating it surfaced a deeper, genuinely
      ambiguous question (whether `$ownerData` can really be null past
      its guard block, given several unguarded uses further down the
      file) that couldn't be safely resolved without more investigation
      than fits this PR; left the baseline entry as-is and filed
      [#1818](https://github.com/elan-registry/registry/issues/1818) to
      track it properly rather than guess.

(No `/security-review` needed — no forms/SQL/auth logic changed, only
failure-handling and transaction-boundary correctness on already-
parameterized queries.)

## Test Plan

- Transaction-rollback tests proving `\Throwable` catch + checked
  `beginTransaction()`/`commit()`/`rollback()` actually protect against a
  mid-transaction PHP error, which the current `Exception`-only catch
  cannot.
- DB-failure-branch tests for the three read methods, asserting
  `OwnerDatabaseException` instead of the collapsed sentinel return.
- Rewritten `OwnerProfileTest` case distinguishing DB-failure (throws)
  from genuine-not-found (still returns `false`, unchanged).
- Caller-regression checks for the 3 audited call sites, matching PR A's
  approach.

## Documentation Plan

None expected — internal error-handling/transaction-integrity fix, no
public API/schema change. Confirm at implementation time that
`CLASSES.md`'s `Owner` section (if it documents these methods' failure
contracts) doesn't need updating, same check as PR A performed.
