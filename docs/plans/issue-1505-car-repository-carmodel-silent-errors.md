# Issue #1505 (PR A/2): CarRepository + CarModel silent DB-error swallowing

**Branch:** `issue/1505-car-repository-carmodel-silent-errors` (renamed from
`issue/1505-owner-silent-db-errors`, which was created before the two-PR
split was decided — see Context)
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

Issue #1505 consolidates 7+ prior issues into three failure-shape categories
(silent false/null/[] returns, transaction integrity, CarModel reference-data
lookups). The issue's own text says "landing as one PR," but research +
PM review found the Owner-side portion is materially larger and riskier
than the issue assumed (new exception class, transaction-pattern rewrite,
`syncLocationToCars()` fallout) — **confirmed with user: splitting into two
PRs.** This plan covers PR A only: `CarRepository` and `CarModel`, both
genuinely silent today (no `error()` check at all, not just a same-shape
collapse like the Owner methods turned out to have). PR B (Owner transaction
integrity + new `OwnerDatabaseException`) will be planned separately once
this lands, since the two have no code dependency on each other.

**Branch rename confirmed with user:** `issue/1505-owner-silent-db-errors`
(created before the split decision, no code written yet) will be renamed
to `issue/1505-car-repository-carmodel-silent-errors` at implementation
time to match this PR's actual scope.

## Bug Escape Analysis

- **Root cause:** `CarRepository::findByVerificationCode()`, `getHistory()`,
  and `getFactoryInfo()` were written without the `error()` check that other
  `CarRepository` methods consistently use (`findByOwner()`,
  `findByChassisKey()`, `getAllForSitemap()`, `updateImage()`,
  `reassignCarsByUser()` all check-and-throw). `CarModel::exists()`/
  `byValue()` have the same omission — no `error()` check anywhere in that
  class.
- **Testing gap:** No test in the codebase exercises the DB-failure branch
  for any of these five methods — confirmed via targeted grep for
  `error.*true`/`willReturn(true)` paired with each method name across
  `tests/`. `CarModelTest.php` and `CarValidatorTest.php` cover happy-path
  and genuine-not-found only.
- **Preventive measure:** Add one DB-failure-branch test per method,
  matching the existing pattern already used for sibling methods
  (`CarRepositoryFindByOwnerFailureTest.php`'s `testFindByOwnerThrowsCarDatabaseExceptionOnQueryError()`
  — stub `error()` → `true`, assert the typed exception with the expected
  message). Mutation-style: reverting the fix should fail the new test
  (explicitly required by #1505's CarModel acceptance criteria).

## Database & Security Considerations

- No schema/migration changes.
- No new attack surface — this only changes what happens on a DB-level
  failure (throw a typed exception + log, instead of silently returning
  `null`/`[]`), not what triggers a query or who can reach it.
- `CarModel::exists()`'s current failure mode has a mild security-adjacent
  UX defect worth noting explicitly: on DB failure, the user sees "not a
  valid Lotus Elan model" — a plausible-looking validation error that masks
  a real infrastructure problem, with zero server-side log entry today.
  Fixing this improves operational visibility (a real DB blip becomes
  loggable/alertable) without changing authorization or input validation.

## Architecture & Design

Apply the codebase's own established pattern — query → check `$this->db->error()`
→ log via `LogCategories::LOG_CATEGORY_DATABASE_ERROR` → throw
`CarDatabaseException` — to the two genuinely-silent `CarRepository` methods
and to `CarModel`'s two methods (new exception type needed there, see below).

**`CarRepository::findByVerificationCode()`** (`CarRepository.php:252-259`):
add the `error()` check, throw `CarDatabaseException` on failure, matching
`findByChassisKey()`'s exact shape (same file, same return-type contract:
`?object`).

**`CarRepository::getHistory()`** (`CarRepository.php:300-310`): add the
`error()` check, throw `CarDatabaseException` on failure, matching
`findByOwner()`'s shape (same file, same return-type contract: `array`).

**`CarRepository::getFactoryInfo()`** (`CarRepository.php:343-355`): add the
`error()` check, throw `CarDatabaseException` on failure. Note this one
currently returns `null` via a loop + `count()` check rather than a direct
query-result check — verify the exact control flow at implementation time
before inserting the guard, since the loop structure may need the check
placed differently than the other two.

**`CarModel::exists()`** (`CarModel.php:250-259`) and **`CarModel::byValue()`**
(`CarModel.php:117-130`): add `error()` checks and throw `CarDatabaseException`
on failure — **confirmed with user**: reuse the existing generic car-domain
DB-failure exception rather than introducing a one-off
`CarModelDatabaseException` for a class with only 2 fallible methods.

**`CarValidator::validateAndSanitizeFields()`'s `'model'` case**
(`CarValidator.php:104-122`) currently calls `$carModelRef->exists(...)`
and negates the result directly into a `CarValidationException` ("not a
valid Lotus Elan model"). Once `exists()` can throw, this call site needs
no change to its own logic — the new `CarDatabaseException` (or
`CarModelDatabaseException`) will propagate up past `CarValidator` naturally,
distinct from the existing `CarValidationException` path, which is exactly
the fix: a DB failure now surfaces as a different, more accurate exception
type instead of masquerading as "invalid model."

## Implementation Checklist

- [x] Add `error()` check + throw `CarDatabaseException` to
      `CarRepository::findByVerificationCode()` —
      `usersc/classes/Car/CarRepository.php` (parallel-safe)
- [x] Add `error()` check + throw `CarDatabaseException` to
      `CarRepository::getHistory()` — `usersc/classes/Car/CarRepository.php`
      (same file as prior item — sequential in practice)
- [x] Add `error()` check + throw `CarDatabaseException` to
      `CarRepository::getFactoryInfo()` — `usersc/classes/Car/CarRepository.php`
      (same file as prior items; loop-structure check confirmed the guard
      goes inside the `foreach`, one query per iteration)
- [x] Add `error()` check + throw `CarDatabaseException` to
      `CarModel::exists()` — `usersc/classes/Reference/CarModel.php`
      (parallel-safe, different file from CarRepository items)
- [x] Add `error()` check + throw `CarDatabaseException` to
      `CarModel::byValue()` — `usersc/classes/Reference/CarModel.php`
      (same file as prior item)
- [x] **Unplanned, found during blast-radius audit:** `Car::find()`
      (`usersc/classes/Car/Car.php:281`) calls `getHistory()`/
      `getFactoryInfo()` with no try/catch — since `find()` is called
      site-wide with no consistent exception handling for this path, and
      its own contract is "never throws for these two subordinate lookups"
      (only the primary `findById()` lookup legitimately throws per its
      existing docblock), wrapped both calls in try/catch, logging via
      `LOG_CATEGORY_DATABASE_ERROR` and degrading to empty
      history/null factory info on failure — confirmed with user before
      implementing. `usersc/classes/Car/Car.php` (depends on: the
      CarRepository items above)
- [x] Add DB-failure-branch tests for all 5 methods, mirroring
      `CarRepositoryFindByOwnerFailureTest.php`'s pattern — 5 new test
      files across `tests/integration/cars/services/` and
      `tests/integration/reference/CarModelFailureTest.php`, plus 2 tests
      confirming `Car::find()`'s catch-and-degrade behavior in
      `tests/integration/cars/CarFindSubordinateLookupFailureTest.php`.
      7 new tests, all passing.
- [x] Audit all call sites of the 3 `CarRepository` methods + 2 `CarModel`
      methods — confirmed via agent: `CarModel::byValue()` has zero
      production callers; `CarModel::exists()`'s one caller
      (`CarValidator`) is a deliberate propagate-as-distinct-exception-type
      design, not a regression; `getHistory()`/`getFactoryInfo()`'s one
      caller (`Car::find()`) already fixed above. **Found one real
      regression:** `app/admin/verify/verify_car.php:50` called
      `Car::findByVerificationCode()` with no try/catch — a DB failure
      would surface as an uncaught `CarDatabaseException` (broken partial
      page) instead of the old silent "not found." Fixed: wrapped in
      try/catch matching this file's own existing pattern (the
      `$applyCarStateChange` closure already does the same thing for the
      same "this page renders output directly" reason) — confirmed with
      user before implementing.
- [x] Run `composer test:medium`/`test:full`, verify pass — 1688 unit +
      483 integration (up from 476), all passing
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries — none found across all 4 touched
      production files
- [x] Run `senior-architect` review of the diff, address findings — **Go**,
      1 Medium (pre-existing, not introduced by this diff, doesn't block
      merge): `Car::findByVerificationCode()`'s broad `catch(\Throwable)`
      discards the new `CarDatabaseException`'s real error message and
      double-logs. Filed
      [#1815](https://github.com/elan-registry/registry/issues/1815) as a
      fast-follow. Architect also live-verified mutation-proof test
      coverage by reverting one fix and confirming its test fails.

(No `/security-review` needed — no forms/SQL/auth logic changed, only
failure-handling on already-parameterized queries.)

## Test Plan

Five new DB-failure-branch tests (one per fixed method), each stubbing
`DatabaseInterface::error()` → `true` and asserting the correct typed
exception + message, mirroring the existing
`CarRepositoryFindByOwnerFailureTest.php` pattern exactly. No existing
happy-path tests are expected to change.

## Documentation Plan

None — internal error-handling consistency fix, no public API/schema/
user-facing change. `CLASSES.md`'s `CarModel`/`CarRepository` docs (if any
document these methods' return contracts on failure) should be checked at
implementation time, but this is expected to be a no-op since none document
"returns null/[] on DB failure" as a deliberate contract today.
