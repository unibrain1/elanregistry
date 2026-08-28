# Issue #1448: fix: Car::update() array_filter prevents clearing color/comments/website/engine/sold-date

**Branch:** `bug/1448-car-update-clear-fields`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

Owners can't clear an optional car field (color, comments, website, engine,
purchasedate, solddate) through the normal edit flow. `app/api/cars/save.php`
already sends an explicit `null` when a form field is emptied — intending
"clear this" — but the value never reaches the database, and the save
response falsely reports success with the field cleared, so it silently
reappears on reload. This is masked, not absent, failure: assessment finding
**P4**.

Root cause is two independent silent-drop points, both discovered during
research (not just the one named in the issue title):

1. `usersc/classes/Car/CarValidator.php::validateAndSanitizeFields()` — the
   named-field cases for `color`, `engine`, `comments`, `website` (~135-149,
   181-196) and `purchasedate`/`solddate` (~151-170) use
   `if (!empty($value)) { $validatedFields[$key] = ...; }` with **no else
   branch** — so an explicit `''`/`null` never even produces a key in the
   output array. This is the first, earlier drop point; it happens before
   `Car::update()`'s own filter ever runs.
2. `usersc/classes/Car/Car.php::update()` (231-245) — after validation, has a
   local `$validCarFields` allowlist mixing car-owned and denormalized
   owner-cache columns, then does
   `array_filter($filteredFields, fn($v) => $v !== '' && $v !== null)` — a
   debugging workaround added in commit `08f1dcb3b` (Sept 2025, "Remove empty
   fields that might cause UserSpice issues"), never a deliberate design.

Existing precedent for the correct shape: `usersc/classes/Owner.php::update()`
splits validated fields into allowlists via `array_intersect_key()` and does
**not** re-strip empty/null afterward. The `lat`/`lon` cases in both
`CarValidator` and `Owner`'s validator already use the correct
`$value !== null && $value !== ''` guard (needed since `0.0` is valid) —
that's the pattern to extend to the six clearable fields.

## Bug Escape Analysis

- **Root cause:** Two independent `!empty()`/`array_filter` guards, added
  independently over time (validator guards are original; the `array_filter`
  was a 2025 debugging workaround), both interpret "empty" as "absent," with
  no way for a caller to say "this is an intentional clear."
- **Testing gap:** `tests/unit/cars/services/CarValidatorTest.php::testWebsiteEmptyIsAccepted()`
  actively asserts the broken behavior is correct
  (`assertArrayNotHasKey('website', $result)`). No test anywhere — unit or
  integration — clears an existing value on the real `Car` class and asserts
  the DB column became `NULL`/empty. The only "clearing" test that exists,
  `ChassisOverridePersistenceTest::testUpdateCarClearsChassisOverride`, only
  passes because `0` (an int) happens to survive `!== '' && !== null`; its own
  inline comment (lines 116-117) documents that this is a type coincidence,
  not evidence the general path works. Issue #1440 (closed) already retired a
  mock-`Car`-based test that gave false confidence on this exact bug.
- **Preventive measure:** Rewrite `testWebsiteEmptyIsAccepted()` to assert the
  key IS present with value `null` (plus a sibling test confirming a field
  *absent from the request* still doesn't appear in the validated array — so
  "absent" vs "explicitly cleared" stays a tested distinction, not an
  accident). Add a new integration test against the real `Car` class
  (`tests/integration/database/CarDatabaseOperationsTest.php` or a new file)
  that sets each of the six fields to a real value, saves, then clears each
  one via `update()` and asserts the DB column is `NULL` after reload.

## UserSpice Integration

No UserSpice framework functionality is duplicated or affected — this is
entirely within `ElanRegistry\Car\Car` / `CarValidator`, which already sit
outside UserSpice's own persistence layer.

## Database & Security Considerations

- **Schema:** No migration needed. Verified via the `cars_update` trigger
  (`database/migrations/20260710120000_change_cars_year_and_drop_modifiedby.php:121-138`)
  — a plain positional `INSERT ... VALUES (OLD.<col>, ...)` into `cars_hist`
  with no `IFNULL`/`COALESCE`/conditional logic, so `NULL` passes through
  transparently. `cars_hist`'s six relevant columns
  (`database/migrations/20260709000000_add_elanregistry_baseline.php:283-299`)
  already match `cars`' nullable types (`color`/`engine`/`website` varchar
  `DEFAULT NULL`, `purchasedate`/`solddate` `date DEFAULT NULL`, `comments`
  `mediumtext` nullable). No trigger or schema change required.
- **Security:** No new attack surface — this only widens what a validated,
  already-authenticated, CSRF-checked field can be set *to* (null vs. a
  sanitized string), it doesn't change what fields can be targeted. The fix
  introduces an explicit `CLEARABLE_FIELDS` allowlist (see below) specifically
  so null-passthrough is opt-in per field, not blanket — denormalized
  owner-cache columns (`email`, `fname`, `lname`, `city`, `state`, `country`,
  `lat`, `lon`, `join_date`) and required fields (`year`, `model`, `chassis`,
  etc.) are deliberately excluded and keep today's strip-on-empty behavior.
- **GDPR:** Not applicable — clearing car metadata is an owner-initiated edit
  to their own car record, not a data-retention or erasure concern.
- **Cross-field validation:** `CarValidator::validateAndSanitizeFields()`
  ~250-256 checks `solddate >= purchasedate` when both are present. Per user
  confirmation, clearing `solddate` while `purchasedate` remains set is the
  normal case (car still owned) and must be allowed — the cross-field check
  must be skipped whenever either side is `null` (comparing against null is
  meaningless), not treated as a validation failure.

## Architecture & Design

**Scope decision (confirmed with user/PM):** Fix only the six fields named in
the issue's acceptance criteria — `color`, `engine`, `comments`, `website`,
`purchasedate`, `solddate`. Do not touch the `!empty()` guards for `model`,
`year`, `series`, `variant`, `type` (required/non-clearable, already rejected
earlier in `save.php` before reaching this code) — that's issue #1262's
territory for those fields, and #1448 stays intentionally distinct from it.
If the same six-field edit incidentally also fixes #1262's `'0'`-string
problem for those fields, note it as an observed side effect in the PR
description and comment on #1262 — do not close #1262, since its remaining
fields are still open.

**1. `CarValidator::validateAndSanitizeFields()`** — for the six clearable
fields' switch cases, replace:

```php
if (!empty($value)) {
    $validatedFields[$key] = InputSanitizer::normalize($value, ...);
}
```

with the same pattern already used for `lat`/`lon`:

```php
if ($value !== null && $value !== '') {
    $validatedFields[$key] = InputSanitizer::normalize($value, ...);
} else {
    $validatedFields[$key] = null;
}
```

(`website`/`purchasedate`/`solddate` keep their existing format validation in
the non-empty branch; the `else` only applies when the field is genuinely
absent-of-content.) The `purchasedate`/`solddate` cross-field check
(~250-256) gets a guard added so it only runs when both values are non-null.

**2. `Car::update()`** — introduce an explicit clearable-fields allowlist
distinct from the general `$validCarFields` column allowlist, so it's
unambiguous which fields may carry `null` through to the repository write:

```php
private const CLEARABLE_FIELDS = [
    'color', 'engine', 'purchasedate', 'solddate', 'website', 'comments',
];
```

Replace the blanket `array_filter($filteredFields, fn($v) => $v !== '' && $v !== null)`
with a filter that only strips empty/null for fields **not** in
`CLEARABLE_FIELDS`:

```php
$filteredFields = array_filter(
    $filteredFields,
    fn($value, $key) => in_array($key, self::CLEARABLE_FIELDS, true)
        || ($value !== '' && $value !== null),
    ARRAY_FILTER_USE_BOTH
);
```

This keeps every other field (including the denormalized owner-cache columns
and required fields) behaving exactly as today — only the six named fields
can now carry a `null` through to `CarRepository::updateCar()`, which already
passes arrays through to the DB layer verbatim with no filtering of its own
(confirmed — no changes needed there).

**3. `app/api/cars/save.php`** — no changes needed. Its `update*()` helpers
already send explicit `null` on clear; they were correct all along and are
what exposed the bug. Verify (not modify) during implementation that the
null→`""` display-formatting block (~209-215, ~121-126) still works
correctly once real nulls start reaching the DB (it already handles null
input on the *outgoing* response side; this is unaffected because it operates
on the request-derived `$cardetails` array, not a DB re-read).

## Implementation Checklist

- [x] Add `CLEARABLE_FIELDS` constant and update the `array_filter` call in
      `Car::update()` — `usersc/classes/Car/Car.php` (parallel-safe)
- [x] Update `color`/`engine`/`comments` named-field cases in
      `validateAndSanitizeFields()` to the `!== null && !== ''` + else-null
      pattern — `usersc/classes/Car/CarValidator.php` (parallel-safe)
- [x] Update `website` named-field case (keep URL validation for non-empty)
      — `usersc/classes/Car/CarValidator.php` (depends on: prior item, same
      file)
- [x] Update `purchasedate`/`solddate` named-field cases (keep date-format
      validation for non-empty) — `usersc/classes/Car/CarValidator.php`
      (depends on: prior item, same file)
- [x] Guard the `solddate >= purchasedate` cross-field check to skip when
      either value is null — `usersc/classes/Car/CarValidator.php` (depends
      on: prior item, same file) — verified no code change needed: existing
      `isset($validatedFields['purchasedate'], $validatedFields['solddate'])`
      guard already evaluates false when either is null.
- [x] Rewrite `testWebsiteEmptyIsAccepted()` to assert `website` key is
      present with `null`; add sibling test asserting a field *absent from
      the request* still produces no key —
      `tests/unit/cars/services/CarValidatorTest.php` (parallel-safe)
- [x] Add integration regression test(s) against the real `Car` class: set
      each of the six fields, save, clear each one via `update()`, reload,
      assert DB column is `NULL`/empty —
      `tests/integration/database/CarDatabaseOperationsTest.php` (parallel-safe)
- [x] Add integration test: clear `solddate` while `purchasedate` remains
      set — assert save succeeds (no false cross-field validation error) —
      `tests/integration/database/CarDatabaseOperationsTest.php` (depends on:
      prior item, same file)
- [x] Run `composer test:medium`, verify pass — 1688 unit + 90 integration,
      all passing
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries (fix or explicitly defer) — none found
- [x] Run `/security-review` (touches validation/persistence path), address
      Critical/High — 0 findings at any severity
- [x] Run `senior-architect` review of the diff, address findings — **Go**,
      0 Critical/High/Medium; added the one recommended low-severity comment
      (lat/lon-vs-clearable-fields asymmetry) to `CarValidator.php`
- [N/A — deferred to /commit-push-pr] Note the #1262 side-effect overlap in
      the PR description; comment on #1262 pointing at the commit (do not
      close #1262) — this is PR-creation work, not implementation; carry
      forward as a reminder when the PR is opened.

**Additional architect-flagged side effect (documented, no code change):**
`Car::create()` also calls `validateAndSanitizeFields()`; since the six
fields aren't required on creation, submitting `''` for one now produces an
explicit `null` in the insert rather than a dropped key. All six columns are
`DEFAULT NULL`, so this is behaviorally a no-op — same category as the
issue #1262 side effect, worth noting in the PR description.

**Unplanned but required fix found during testing:** `CarAdministrationServiceTest::testTransferClearsAllOwnerIdentityFieldsWhenTargetHasNone`
(`tests/unit/cars/services/CarAdministrationServiceTest.php`) asserted `website`
cleared to `''` on ownership transfer — this depended on the pre-#1448 bug
(validator dropping the key, then `withBlankedFieldsRestored()` falling back
to `''`). Post-fix, the validator now returns `website => null` directly, so
`withBlankedFieldsRestored()` correctly leaves it alone — the previous
owner's value is still cleared, just via `null` instead of `''`, which
satisfies the test's actual intent. Updated the assertion; filed
[#1811](https://github.com/elan-registry/registry/issues/1811) to
standardize the `null`-vs-`''` "cleared" convention across all
`OWNER_IDENTITY_FIELDS`, not just `website`.

**Double-checked (no code change needed):** image clearing via the FilePond
dropzone (deleting all images) goes through `Car::removeImage()` →
`CarImageProcessor::removeImage()` → `CarRepository::updateImage()`'s
dedicated CAS write — entirely separate from `Car::update()`'s
`array_filter`/`CLEARABLE_FIELDS` path this issue fixes. Already writes `''`
correctly when the image list becomes empty; unaffected by and unrelated to
issue #1448.

## Test Plan

- **Unit (`CarValidatorTest.php`):** rewrite the website-empty test to assert
  correct null-passthrough behavior; add an absent-vs-cleared distinction
  test; add equivalent cases for `color`, `engine`, `comments`,
  `purchasedate`, `solddate` if not already covered by existing tests in that
  file (confirm during implementation).
- **Integration (`CarDatabaseOperationsTest.php`):** exercise the real
  `Car::update()` → `CarRepository::updateCar()` → DB path (per #1440, no
  mock) — set-then-clear round trip for each of the six fields individually,
  and the solddate/purchasedate combination case.
- Both tiers must pass under `composer test:medium` before merge.

## Documentation Plan

No public API, schema, or user-facing doc changes — internal bug fix only.
Not consulting `technical-documentation-writer`.
