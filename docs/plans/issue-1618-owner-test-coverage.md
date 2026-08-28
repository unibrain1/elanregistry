# Issue #1618: Owner.php test coverage — ownership history, location-sync error paths, quality-badge drift guard

**Branch:** `issue/1618-owner-test-coverage`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

Found during a full test-coverage audit of `usersc/classes/`. `Owner.php` is
well-tested overall but a few real, reachable methods and error paths have
none. Sequenced right after #1505 specifically because #1505 changed the
error-handling shape (`false`/`[]` → throw `OwnerDatabaseException`) that two
of these gaps target — and #1505 already closed two of #1618's four
acceptance criteria as a side effect (confirmed below), so this issue is
narrower than originally scoped.

**Already satisfied by #1505 PR B** (`tests/integration/OwnerReadMethodsDatabaseFailureTest.php`):

- `getCarsOwned()` DB-error branch — `testGetCarsOwnedThrowsOwnerDatabaseExceptionOnQueryError()`
- `getOwnershipHistory()` DB-error branch — `testGetOwnershipHistoryThrowsOwnerDatabaseExceptionOnQueryError()`

**Remaining gaps this issue covers:**

1. `getOwnershipHistory()` happy path — multi-record and empty-result cases (only the DB-error path is covered)
2. `syncLocationToCars()` — `updateCar()`/`insertHistory()` false-return failure branches (only the happy path and the `getCarsOwned()`-throws path are covered)
3. `getQualityBadgeClass()` — untested directly; duplicates `OwnerView::qualityBadgeClass()`'s logic with no drift guard

## UserSpice Integration

N/A — test-only change, no new production code paths.

## Database & Security Considerations

No schema/security impact. New integration tests write to `cars_hist` (via
direct `insert()`, no existing factory helper — confirmed via Explore) and
must clean up inserted rows in `tearDown()`, consistent with
`IntegrationTestCase` conventions.

## Architecture & Design

**1. `getOwnershipHistory()` happy path** — new tests in
`tests/integration/OwnerReadMethodsDatabaseFailureTest.php` (extends the
existing DB-error-focused file rather than creating a new one, since it's
already scoped to this exact method and already has the `IntegrationTestCase`
setup). Two cases: an owner with multiple `cars_hist` rows (assert count and
that returned rows carry the joined `chassis`/`model`/`year` fields from
`Owner::getOwnershipHistory()`'s `LEFT JOIN cars` — Owner.php:395-402), and an
owner with none (assert `[]`). Insert `cars_hist` rows directly via
`$this->db->insert('cars_hist', [...])` — no existing factory (confirmed by
Explore), mirroring the shape `Owner::syncLocationToCars()` itself builds
(Owner.php:625-629) for realism.

**2. `syncLocationToCars()` failure branches** — new file
`tests/integration/OwnerSyncLocationToCarsFailureTest.php`, following
`OwnerCreateUpdateTransactionRollbackTest.php`'s established `DatabaseInterface`
proxy pattern (delegates to a real integration-test DB connection except for
one overridden method). Confirmed via Explore: `CarRepository::updateCar()`/
`insertHistory()` (CarRepository.php:102-105, 331-334) are thin wrappers over
`$this->db->update()`/`$this->db->insert()`, and `Owner`/`CarRepository` share
the same injected `DatabaseInterface` (Owner.php:617), so a proxy overriding
only `update()` (to force `updateCar()` failure) or only `insert()` (to force
`insertHistory()` failure) leaves `Owner`'s own `query()`-based calls
(`getCarsOwned()`) working normally. Two tests:

- `updateCar()` returns false → asserts `$carsUpdated` excludes that car
  (returns 0 for a single owned car) and that the failure is logged under
  `LOG_CATEGORY_OWNER_ACTIONS` (Owner.php:633).
- `insertHistory()` returns false (with `updateCar()` succeeding) → asserts
  `$carsUpdated` still counts the car as updated (Owner.php:620-621, the
  counter increments before the history-insert attempt) and that the
  history-insert failure is logged separately (Owner.php:630).

Build via `new Owner(null, $proxyDb)` + Reflection to set `_data`, mirroring
`OwnerReadMethodsDatabaseFailureTest.php`'s existing pattern for constructing
a loaded `Owner` without a real `find()` call.

**3. `getQualityBadgeClass()` drift guard** — new test method in
`tests/unit/classes/OwnerProfileTest.php` (already tests `Owner`'s scoring
logic, per Explore). A single data-provider-driven test asserting
`Owner::getQualityBadgeClass($score) === OwnerView::qualityBadgeClass($score)`
across boundary values (0, 59.9, 60, 79.9, 80, 100 — matching
`OwnerViewTest.php`'s existing boundary set at lines 74-102) plus a plain
unit test asserting `Owner::getQualityBadgeClass()`'s own return values
directly (not just equality with its twin), so a test failure clearly
indicates *which* side drifted from the intended thresholds. Confirmed with
user: drift-guard test only, not consolidation — consolidating the duplicated
logic is a larger refactor with its own call-site blast radius, out of scope
for a test-coverage issue.

## Implementation Checklist

- [x] Add `testGetOwnershipHistoryReturnsMultipleRecordsOrderedByCtimeDesc()`
      and `testGetOwnershipHistoryReturnsEmptyArrayWhenNoHistoryExists()` —
      new file `tests/integration/OwnerOwnershipHistoryIntegrationTest.php`
      (deviation from plan: `OwnerReadMethodsDatabaseFailureTest.php`
      deliberately extends plain `TestCase`, not `IntegrationTestCase`, per
      its own docblock — happy-path tests need a real DB, so they went in a
      new `IntegrationTestCase`-based file instead of contradicting that
      file's stated design)
- [x] Create `tests/integration/OwnerSyncLocationToCarsFailureTest.php` with
      `testUpdateCarFailureExcludesCarFromCountAndLogs()` and
      `testInsertHistoryFailureStillCountsCarAsUpdatedAndLogsSeparately()` —
      both also assert the log call via `countMatchingLogs()`, added after
      mutation-testing revealed the original DB-state-only assertions didn't
      catch a removed log call
- [x] Add `testGetQualityBadgeClassMatchesOwnerViewAcrossBoundaries()`
      (data-provider) and `testGetQualityBadgeClassReturnsExpectedClassForBoundaryScores()`
      (direct-value) — `tests/unit/classes/OwnerProfileTest.php`
- [x] Mutation-verify each new test (temporarily break the assertion target,
      confirm failure, restore) — all 6 new tests confirmed to fail on a
      targeted mutation and restored clean
- [x] Run `composer test:full`, verify pass (parse summary line per project
      convention, don't trust exit code alone) — OK (1709 tests, 4728
      assertions) unit, OK (494 tests, 1992 assertions) integration
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries — clean, no touched file appears in the
      baseline
- [x] Run `senior-architect` review of the diff, address findings — no
      Critical findings; one Important finding (log-count assertions used an
      absolute count instead of the established before/after delta pattern
      against the shared, never-truncated `logs` table — matches
      `CarCreateRepositoryFailureTest.php`/`CarUpdateRepositoryFailureTest.php`'s
      convention) fixed in both `OwnerSyncLocationToCarsFailureTest.php`
      tests and re-mutation-verified; two Minor/informational notes (no
      `logs` cleanup — inherited convention, not new; proxy duplication
      across 3 similar test-double classes — later consolidated into one
      parameterized proxy in `/simplify`)

(No `/security-review` needed — test-only change, no forms/SQL/auth/user-input handling modified.)

## Test Plan

Covered entirely by the Implementation Checklist above — this issue *is* the
test plan. All new tests are integration (`getOwnershipHistory()` happy path,
`syncLocationToCars()` failure branches — both need a real DB) or unit
(`getQualityBadgeClass()` drift guard — pure function, no DB). No Playwright
needed; nothing user-facing or browser-observable changed.

## Documentation Plan

None expected — test-only change, no public API/behavior/schema change.
