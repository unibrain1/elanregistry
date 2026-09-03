# Issue #1878: CarAdministrationService::transfer() does not clear solddate on transfer

**Branch:** `issue/1878-transfer-clear-solddate`
**Milestone:** `milestone/v2.30.0`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

**Root Cause:** `CarAdministrationService::transfer()` builds an explicit
`$ownerFields` array (`usersc/classes/Car/CarAdministrationService.php:148-161`)
for the `cars` UPDATE. It carries only owner-identity columns; `solddate` is
absent, so a previously-sold car keeps its `solddate` through a change of
ownership. The app-written `cars_hist` audit row (line 195) copies the old
`solddate` too. #1448 (PR #1813) fixed the same falsy-value class of defect in
`Car::update()`'s `CLEARABLE_FIELDS` allowlist, but `transfer()` does not go
through `Car::update()`, so that fix never reached this path.

**Why it reached production:** No test observes `solddate` after a transfer.
The unit test `testTransferClearsAllOwnerIdentityFieldsWhenTargetHasNone()`
captures the `$updateFields` payload but iterates only `OWNER_IDENTITY_FIELDS`;
the integration suite `tests/integration/CarTransferTest.php` reads the row
back but asserts `user_id`, name, email, and location only. Every fixture car
is created with `solddate = NULL`, so a missing clear is indistinguishable from
a correct one.

**Testing Gap:** No test transfers a car whose `solddate` is set. No test
asserts `solddate` on the transfer's `cars_hist` row.

**Preventive Measures:**

- Unit (mocked DB): assert the captured `$updateFields` and `$historyFields`
  both carry `solddate => null` on an ordinary transfer, and that
  `$updateFields` does **not** carry `solddate` when the target is the
  `noowner` system account.
- Integration (real DB): create a car with `solddate = '2020-01-01'`, transfer
  it, read the row back and assert `solddate` is NULL; assert the
  `operation='NEWOWNER'` `cars_hist` row has `solddate` NULL; assert a
  never-sold car still transfers with `solddate` NULL (no regression); and
  extend `AdminCarReassignmentTest` so a sold car reassigned to the seeded
  `noowner` account keeps its `solddate`.

## UserSpice Integration

None. `transfer()` is project code; the DB write goes through
`CarRepository::updateCar()` → UserSpice `DB::update()`, which binds PHP `null`
as SQL NULL via PDO `bindValue()` (precedent:
`CarRepository::reassignCarsByUser()` passes a nullable `user_id`). No `users/**`
file is touched.

## Database & Security Considerations

- **No schema change.** `cars.solddate` is a nullable `DATE`; writing NULL is
  its documented empty state (`CarRepository::findVerificationEligible()`
  comment: comparing it to `''` is a hard error under `STRICT_TRANS_TABLES`,
  so NULL — never `''` — is the only valid clear).
- **Validation:** `CarValidator::validateAndSanitizeFields()` maps a `null`
  `solddate` to `null` (`CarValidator.php:183-184`, post-#1448) rather than
  dropping the key, so the value reaches the UPDATE without any change to
  `withBlankedFieldsRestored()` / `OWNER_IDENTITY_FIELDS`.
- **Audit trail:** the MySQL `cars_update` trigger writes an `OLD.*` row
  automatically (unchanged); the app-written `NEWOWNER` history row is the one
  that must reflect the post-transfer state, so its `solddate` is set to
  `null` alongside the `cars` write.
- **Verification eligibility:** clearing `solddate` makes the transferred car
  eligible again for `findVerificationEligible()` (`WHERE solddate IS NULL`),
  which is the intended outcome.
- **No auth/CSRF/input impact** — no new inputs; all callers already gate on
  admin permission or run inside the GDPR deletion hook.

## Architecture & Design

**Decision (user-confirmed):** clear `solddate` only on a real change of
ownership. Reassignment to the `noowner` system account — the GDPR path in
`usersc/scripts/after_user_deletion.php` and the admin "no_owner" reassign
command in `app/admin/index.php` — is not a sale reversal, so the sold state
is preserved there. Detection lives inside `transfer()`, keyed on
`$targetUser->username === 'noowner'`, because `transfer()` already
special-cases this account (`contactableEmail()` / `SYSTEM_ACCOUNT_EMAIL`);
no signature change, no caller edits. All three live callers pass
`'NEWOWNER'`, so operation type cannot distinguish the paths.

Alternatives rejected: a new `bool $clearSoldDate` parameter (touches
`Car::transfer()` and the GDPR script; every future system-reassignment caller
must remember it); a distinct GDPR operation type (changes a documented audit
value in `SYSTEM_OVERVIEW.md` and existing `cars_hist` semantics).

Changes in `usersc/classes/Car/CarAdministrationService.php`:

1. Add `private const SYSTEM_ACCOUNT_USERNAME = 'noowner';` next to
   `SYSTEM_ACCOUNT_EMAIL`, documented as the same value
   `after_user_deletion.php` and `RegisterNoownerAccount::USERNAME` look up.
   Username, not the email sentinel, is the key: the existing docblock
   promises a stale `SYSTEM_ACCOUNT_EMAIL` "never changes behavior", and
   keying this decision on it would break that promise.
2. Compute `$isSystemAccount = ($targetUser->username ?? '') === self::SYSTEM_ACCOUNT_USERNAME;`
   before the transaction. The `?? ''` matters: existing unit stubs omit
   `username`, and a missing field must mean "real owner", not "system".
3. When `!$isSystemAccount`, add `'solddate' => null` to `$ownerFields`
   (before validation — `CarValidator` passes it through as `null`), and set
   `'solddate' => null` in `$historyFields`; otherwise leave both as today
   (`$carData->solddate ?? null` in history). Add a short comment stating
   the rule: a sale does not survive a change of owner, but reassignment to
   the system account is not a change of owner.

No other files change in production code.

## Implementation Checklist

- [x] Add `SYSTEM_ACCOUNT_USERNAME` const, `$isSystemAccount` check, and the
      conditional `solddate => null` in both `$ownerFields` and
      `$historyFields` — `usersc/classes/Car/CarAdministrationService.php`
      (parallel-safe)
- [x] Extend `createOwnerDb()` with a `string $username = 'testuser'`
      parameter and add unit tests: ordinary transfer writes
      `solddate => null` to both captured `update` and `insert` payloads;
      `noowner` target omits `solddate` from `update` and passes the car's
      existing `solddate` through to `insert` —
      `tests/unit/cars/services/CarAdministrationServiceTest.php`
      (depends on: service change)
- [x] Add integration tests: previously-sold car (`createTestCar(...,
      ['solddate' => '2020-01-01'])`) has `solddate` NULL after transfer and
      NULL on its `NEWOWNER` `cars_hist` row; never-sold car still transfers
      with `solddate` NULL — `tests/integration/CarTransferTest.php`
      (depends on: service change; parallel-safe with the unit-test item)
- [x] Add integration test: sold car reassigned to the seeded `noowner`
      account keeps its `solddate` — `tests/integration/AdminCarReassignmentTest.php`
      (depends on: service change; parallel-safe with the two test items above)
- [x] Verify each new test goes red with the service change reverted
      (`git stash` the service file, run the new tests, `git stash pop`) —
      done: `testTransferClearsSoldDateOnCarsAndHistoryRow` and
      `testTransferClearsSoldDateOnPreviouslySoldCar` fail without the fix;
      the preserve/never-sold guards stay green by design (they pin
      unchanged behaviour). Note: integration files must run via the full
      suite or a suite-wide `--filter` — run alone they error on
      `ELAN_IMAGE_DIR`, a pre-existing gap in `tests/bootstrap-integration.php`
      (out of scope here).
- [x] Run `composer test:full` (integration suite lives outside
      `test:medium`'s `tests/integration/database` scope), verify both `OK`
      summary lines with non-zero counts — `OK (1770 tests, 4870 assertions)`
      / `OK (522 tests, 2111 assertions)`
- [x] PHPStan baseline hygiene: confirm `usersc/classes/Car/CarAdministrationService.php`
      carries no `phpstan-baseline.neon` entries (fix or explicitly defer per
      `/execute-plan` Step 6.5); `vendor/bin/phpstan analyse` on the file
- [x] Run `/security-review` (SQL write path touched), address Critical/High
      — none. One Low (pre-existing): `users.username` has no DB `UNIQUE`
      index; uniqueness is UserSpice `Validate` only. Failure mode is benign
      (a non-seeded `noowner` would fall to the ordinary clear path). Not
      fixed here; candidate for `/found`.
- [x] Run `senior-architect` review of the diff, address findings — no
      Blocking; three clarity nits applied (staleness clause on the new
      const's docblock, shared `$soldDate` local for both writes, comment
      noting the rule is independent of `$operationType`).

## Test Plan

- **Unit** (`CarAdministrationServiceTest.php`, mocked `DatabaseInterface`):
  reuse the existing capture-callback pattern from
  `testTransferToUnroutableSystemAccountBlanksEmailInsteadOfFailing()`.
  - `testTransferClearsSoldDateOnCarsAndHistoryRow`: `$carData` carries
    `solddate => '2020-01-01'`; assert `array_key_exists('solddate',
    $updateFields)` and `null === $updateFields['solddate']`; same for
    `$historyFields`.
  - `testTransferToSystemAccountPreservesSoldDate`: owner stub with
    `username => 'noowner'`, `email => 'noowner@invalid'`; assert
    `solddate` absent from `$updateFields` and `'2020-01-01' ===
    $historyFields['solddate']`.
- **Integration** (`CarTransferTest.php`, real DB):
  - `testTransferClearsSoldDateOnPreviouslySoldCar`: create car with
    `solddate`, transfer, `SELECT solddate FROM cars` → NULL; `SELECT
    solddate FROM cars_hist WHERE car_id = ? AND operation = 'NEWOWNER'` →
    NULL.
  - `testTransferLeavesNeverSoldCarSoldDateNull`: default fixture, transfer,
    assert NULL (guards against the change writing anything but NULL).
- **Integration** (`AdminCarReassignmentTest.php`): create car with
  `solddate`, transfer to seeded `noowner`, assert `solddate` unchanged.
- **Red check:** all four new tests must fail with the service edit
  stashed; recorded in the checklist.
- No Playwright change — no page or UI path is added or moved.

## Documentation Plan

- No developer doc enumerates `transfer()`'s written columns
  (`CLASSES.md`, `SYSTEM_OVERVIEW.md` checked) — no doc update required.
- Release notes entry for #1878 already exists in
  `docs/releases/RELEASE_NOTES_v2.30.0.md`; `/execute-plan` Step 9 should
  amend it to note that reassignment to `noowner` preserves `solddate`.
