# Issue #1732: datatables-xss.spec.js History section assumes ambient car data

**Branch:** `issue/1732-datatables-xss-history-fixture`
**Milestone:** `milestone/v2.29.4`
**Status:** Implemented — pending commit/PR

## Context

The "car history table" section of `tests/playwright/security/datatables-xss.spec.js`
(lines 290-310) scrapes a `car_id` from the logged-in test account's own car
listing page and throws if none is found. On a fresh dev DB with zero cars
(confirmed on this machine), all 4 tests in that section fail. This is a
fixture-assumption gap: the section needs one real car row to exist so
`app/owner/cars/details.php?car_id=X` loads — the XSS payloads themselves
stay synthetic/in-memory via `table.row.add()`, identical to the passing
car-listing and factory sections, so this is not the "inject a live payload"
concern the file's own header comment (lines 20-26) rules out — that
rationale is about avoiding a *poisoned* DB row, not about creating an
ordinary one.

No Playwright-side fixture-creation helper exists anywhere in the repo today
(confirmed via 2 rounds of Explore across all of `tests/playwright/`). The
closest related work is issue #1789, which hit the same class of gap for
`process-transfer-approve.php` — but #1789 additionally needs a
*non-admin-owned* car (to test transfer approval without violating "you
already own this car"), which requires a second test identity that doesn't
exist. #1732 doesn't have that complication: it only needs a car owned by
the account already logged in via `ensureLoggedIn()`. User confirmed
`TEST_USERNAME` is an admin account, so cleanup is also reachable — the only
delete path in the app is the admin-only HTML form at `app/admin/index.php`.

This PR becomes the first real Playwright-side car-creation-and-cleanup
fixture in the repo. It's scoped narrowly to what #1732 needs (same-identity
ownership); it does not attempt to solve #1789's harder non-admin-ownership
case, which stays open.

## Bug Escape Analysis

- **Root cause:** the History section's `beforeAll` scrapes an ambient
  `car_id` instead of creating its own, so it silently depends on the dev/CI
  DB already containing at least one car for the test account.
- **Testing gap:** no test exercises the zero-cars-for-this-account state;
  the section either passes (car exists) or throws (none exists) with no
  fixture in between.
- **Preventive measure:** this PR's fix *is* the preventive measure — create
  a disposable car in `beforeAll`, clean it up in `afterAll`, so the section
  passes deterministically regardless of ambient DB state.

## Architecture & Design

**Create**, via the same real-CSRF-POST pattern `car-update-ownership.spec.js`
already established for this file's sibling security specs:

1. Fetch a CSRF token from a page rendering `#csrf` (reuse
   `app/owner/cars/edit.php`, same as the existing pattern).
2. `page.request.post('app/api/cars/save.php', { form: {...} })` with
   `action=addCar`. Minimum required fields per `CarValidator`/`Car::create()`:
   `year`, `model`, `chassis`, `csrf`. **Correction during implementation:**
   `model` is not a free-form `series|variant|type` string — it must be an
   exact `model_value` from the `car_models` table (e.g. `S3|FHC|36`, where
   `36` is a numeric type *code*, not a body-style abbreviation like `FHC`).
   The originally-planned `S4|SE|FHC` silently failed `Car::create()`
   (caught and swallowed by `addCar()`'s catch block, producing a `500`
   downstream from `mvTmpImages()` operating on a null id) — confirmed by
   directly querying the dev DB's `cars`/`car_models` tables. Use
   `chassis_override=1` with a randomized chassis value (`TEST-${Date.now()}`)
   to bypass chassis format validation — confirmed no DB-level uniqueness
   constraint exists on `chassis`, so this is safe.
3. **Correction during implementation:** the success response is flat —
   `{success, message, cardetails}` — not nested under `data`. `cardetails.id`
   (a numeric string) is the new car's ID; parse it with `parseInt()`.

**Cleanup**, via the admin-only delete form (the only delete path that
exists):

1. POST to `app/admin/index.php` with `command=delete`, `car_id=<created
   id>`, `confirmation=DELETE` (literal, case-sensitive), a fresh CSRF token,
   in `test.afterAll`.
2. This is an HTML-response endpoint (flash-message reload), not JSON — check
   for a non-error status/absence of a `usError` flash rather than parsing a
   JSON envelope.

**Fallback safety:** if creation fails for any reason, `carId` stays `null`
and the existing `test.skip(true, 'No cars found...')` per-test guards
already in the file continue to work unchanged — no new failure mode
introduced if the fixture-creation step itself breaks.

**File touched:** `tests/playwright/security/datatables-xss.spec.js` only —
replace the ambient-scrape `beforeAll` (lines 293-310) with the
create-fixture version described above, and add a new `afterAll` for
cleanup. No other file changes needed; `ensureLoggedIn` and
`waitForDataTables` continue to be reused from `auth-helper.js`.

**Second pre-existing bug found and fixed in this PR (user-confirmed
in-scope, high severity):** `#historyDetails` (the Bootstrap `collapse`
wrapping `#carHistoryTable`) is collapsed by default (`class="collapse"`,
not `"collapse show"`) on **every** car's details page — confirmed on both
a real ambient car (unrelated owner, pre-existing history) and the new
fixture car. None of the 4 History tests ever clicked `#historyToggleBtn`
to expand it, so `waitForSelector('#carHistoryTable_wrapper')` would time
out (element exists but is hidden) regardless of whether the car is ambient
or freshly created — this predates #1732 and was previously masked by
whatever made the section not get exercised. Fixed by adding
`await page.locator('#historyToggleBtn').click();` before each of the 4
`waitForSelector('#carHistoryTable_wrapper')` calls.

## Test Plan

No new test file — this modifies the existing History section's setup/
teardown so its 4 tests (already present) now run deterministically instead
of throwing on an empty dev DB. Verification: run
`npx playwright test tests/playwright/security/datatables-xss.spec.js`
locally against the current empty dev DB and confirm all tests in the "car
history table" describe block pass (not skip, not throw) — this is the
direct repro of the bug this issue reports. Also re-run the full file to
confirm the car-listing/factory sections are unaffected.

## Implementation Checklist

- [x] Replace History section's `beforeAll` (ambient scrape) with a
      create-fixture version using the `addCar` CSRF-POST pattern —
      `tests/playwright/security/datatables-xss.spec.js` (single file, not
      parallel-safe — one item)
- [x] Add `afterAll` cleanup via the admin delete form —
      `tests/playwright/security/datatables-xss.spec.js` (depends on:
      beforeAll fixture creation)
- [x] Fix pre-existing `#historyDetails` collapse-visibility bug (click
      `#historyToggleBtn` before each `waitForSelector`) —
      `tests/playwright/security/datatables-xss.spec.js` (same file,
      user-confirmed in-scope addition)
- [x] Run `npx playwright test tests/playwright/security/datatables-xss.spec.js`
      locally (MAMP required), confirm all History-section tests pass on
      the current empty dev DB — confirmed: 4/4 History tests pass; only
      the 2 pre-existing unrelated failures remain (car-listing/factory
      sections, confirmed identical on unmodified `main`); DB verified
      clean of orphaned `TEST-*` fixture rows after the run
- [x] Run `composer check:docs` to confirm no doc drift flagged —
      "Documentation checks passed."
