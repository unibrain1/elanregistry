# Issue #1846: 2 tests in car-edit-text-save.spec.js fail — page.route timeout / target closed

**Branch:** `issue/1846-car-edit-text-save-route-timeout`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

**Root cause, confirmed via instrumented reproduction (not the reported symptom's
surface framing):** the reported error ("page.route: Target page, context or
browser has been closed") is a downstream symptom, not the actual bug.
Traced with timestamped logging and `page.on('close'/'crash')` listeners:

- Both failing tests wait up to 10 seconds
  (`page.waitForFunction(() => instance.getFiles().length > 0, {timeout:
  10000}).catch(() => {})`) for FilePond to hydrate an existing image via a
  mocked `fetchImages` response.
- `fetchImages` is only ever called by `app/assets/js/car-edit.js` when
  `$("#car_id").val()` is truthy, which in turn is only populated when
  `edit.php` renders with `$action === 'updateCar'`
  (`window.editCarConfig.isUpdate`).
- `$action` in `app/owner/cars/edit.php` defaults to `'addCar'` and is
  **only** ever set to `'updateCar'` via a real **POST** request
  (`if (Input::existsPost()) { ... $action = Input::get('action'); ... }`)
  — never from the `car_id` **GET** query parameter these tests navigate
  with (`page.goto('...edit.php?car_id=N')`).
- Confirmed empirically: `window.editCarConfig.isUpdate` is `false` and
  `#car_id`'s value is empty after this GET navigation, regardless of
  whether car `N` exists. `fetchImages` never fires (confirmed via
  request/response listeners — zero requests to `save.php` with
  `action=fetchImages` observed).
- Because the wait is `.catch()`-guarded, it doesn't itself fail the test —
  but by the time its internal 10s timeout resolves the catch, combined
  with earlier step overhead, the test is close enough to Playwright's
  default 30s per-test timeout that the test framework's own teardown
  (closing the page) races with and wins against the test body's next
  `await page.route(...)` call, which then throws "Target page ... has
  been closed" — the error the issue reports, but not where the actual
  defect is.
- The 5 *passing* tests in this file only wait for FilePond to
  **initialize** (`typeof window.FilePond !== 'undefined'`), not for
  hydration — so they never hit this dead-end wait at all. This is why
  only 2 of 7 tests in the file are affected.

**Second, deeper finding for test #737 specifically**
("mixed save: existing image preserved in filenames, only new file in
file[]"): its assertions require a genuinely-hydrated existing image to
verify the "old file survives alongside new upload" behavior. Simply
removing its hydration wait (the fix for test #113) would leave this test
unable to exercise what it's actually named for — the pond would always be
empty. Investigated navigating into real update mode via a POST
(`action=updateCar`, matching the production "Update Car" button's actual
markup in `app/views/cars/_car_hero_actions.php`), but
`app/owner/cars/edit.php`'s `updateCarDetails()` requires the target car to
genuinely exist in the DB (`new Car($id)->exists()`, redirects otherwise) —
and `TEST_USERNAME` currently has zero cars in the local database (same
fixture gap already encountered working #1781/#1852 area of this
milestone). A POST-based fix therefore cannot work without a seeded car
fixture, which is out of scope for this issue.

**Why it reached this state:** both defects are structural, not
timing-flaky — they've likely never passed since being written, or passed
only in an environment where `TEST_USERNAME` happened to own a car (making
the GET navigation's actual mode irrelevant if a hydration race happened to
resolve before the timeout, though this is a coincidence, not a fix — the
`isUpdate: false` / empty `#car_id` behavior would still be present).

**Testing gap:** no test asserts that `editCarConfig.isUpdate` /
`fetchImages`-triggering navigation actually works the way these two
tests assume — the assumption that `?car_id=N` alone puts the page in edit
mode was never verified against the actual PHP logic.

**Preventive measure:** none added beyond fixing the two tests themselves
— this is a narrow, well-understood defect in test setup, not a pattern
likely to recur elsewhere in this file (the other 5 tests correctly don't
depend on hydration).

## Architecture & Design

Two independent fixes:

1. **Test #113 ("text-only save sends sentinel blob and no binary image
   data"):** remove the doomed-to-fail hydration wait
   (`getFiles().length > 0`) entirely, matching the pattern already used by
   the file's 5 passing tests (wait only for FilePond initialization, then
   proceed). The test's actual assertions (sentinel blob present, no binary
   image data in `file[]`) hold regardless of whether the pond starts
   empty or hydrated — a text-only save with zero LOCAL files still must
   produce the sentinel blob and no binary data, so this doesn't weaken
   what the test verifies. Add a comment explaining why the wait was
   removed (GET-only navigation can't reach update mode, so hydration can
   never occur), matching the file's own file-header-comment convention
   for documenting non-obvious test design decisions.

2. **Test #737 ("mixed save: existing image preserved in filenames, only
   new file in file[]"):** add a pre-check for whether a real hydrated
   image is actually possible in this environment, and `test.skip()` with
   a clear message when it isn't — rather than hang or silently degrade
   to testing something other than what the test claims to test. Concretely:
   after the FilePond-initialize wait, check `editCarConfig.isUpdate`
   (already exposed on `window`) — if `false`, skip immediately with a
   message explaining the account has no owned cars locally, this test
   requires a seeded car for `TEST_USERNAME` to exercise mixed-file
   behavior, and pointing to `scripts/provision-schema.sh --full` or
   manual car creation as the way to unblock it locally. This matches the
   file's own "skip rather than assume" convention already used elsewhere
   in this codebase (e.g. `factory-registry-link.spec.js`'s fixture-gap
   skips from #1781).

**Alternative considered and rejected (for #737):** seeding a car as part
of this fix. Rejected per user decision — out of scope; this issue is
about fixing the test's hang/timeout behavior, not building fixture
infrastructure. A seeded-car setup would be a separate, more invasive
change touching test infrastructure broadly, not just this file.

## Implementation Checklist

- [x] Remove the FilePond-hydration `waitForFunction` (lines ~180-193) from
      the "text-only save sends sentinel blob and no binary image data"
      test; add an explanatory comment —
      `tests/playwright/car-edit-text-save.spec.js`
- [x] Add an `editCarConfig.isUpdate` check + `test.skip()` guard after the
      FilePond-initialize wait in the "mixed save: existing image
      preserved..." test, before attempting hydration; the hydration wait
      itself no longer needs a `.catch()` guard once gated behind the
      `isUpdate` check, since a real failure there should now surface as a
      genuine test failure, not be silently swallowed —
      `tests/playwright/car-edit-text-save.spec.js`
- [x] Run the full `car-edit-text-save.spec.js` file locally against MAMP,
      confirm all 7 tests pass or skip cleanly (no hangs, no "target
      closed" errors) — 6 passed, 1 skipped (mixed-save test, correctly,
      since `TEST_USERNAME` owns no cars locally), 0 failed
- [x] Re-run 2-3 times back-to-back to confirm no flakiness — both targeted
      fixes stable across 3 runs. **Found a separate, pre-existing flaky
      test** ("comments with special characters save and reload as plain
      text", line 519/694-709) — unrelated to this issue's two target
      tests, fails 2/5 on a `--repeat-each=5` run even in isolation. Not
      caused by this PR's changes (confirmed via `git stash` against
      unmodified baseline, where it also failed under repeated runs).
      Out of scope for #1846 — filed as
      [#1865](https://github.com/elan-registry/registry/issues/1865).
- [x] PHPStan baseline hygiene: not applicable — no PHP files touched
- [x] Run `/security-review` — not required (test-only change)
- [x] Run `senior-architect` review of the diff, address findings — clean,
      no Blocking. Independently verified the root-cause diagnosis against
      actual source (`edit.php`'s `$action` logic, `car-edit.js`'s
      `fetchImages` guard), confirmed test #113's fix is safe (removed
      wait was never load-bearing for its actual assertions), confirmed
      test #737's skip-guard is correctly placed and will self-heal once a
      car fixture exists. One trivial nit (comment referenced
      `factory-registry-link.spec.js` without its `e2e/` subdirectory) —
      fixed and reverified green.

## Test Plan

- No new test files. Two existing tests in `car-edit-text-save.spec.js`
  fixed in place.
- Verification: run the full file, confirm all 7 tests pass genuinely, no
  hangs, no skips masking real coverage gaps. Re-run multiple times to
  rule out the exact timing-dependent hang this issue reports.

## Post-Push Review Finding (PR #1866) — Corrected

The CI review (`pr-to-milestone-review`) found a **Blocking** issue in the
originally-implemented fix for test #737: the `window.editCarConfig.isUpdate`
skip-guard checked a value from a GET navigation that can **never** set
`isUpdate` to `true`, regardless of whether `TEST_USERNAME` owns a car —
making the skip permanent and unconditional in every environment, not just
locally. The skip message's framing ("seed a car to unblock this") was
actively misleading, since even a seeded car wouldn't have fixed anything —
the test never attempted the POST needed to reach update mode at all. The
review correctly identified a working precedent already in this test suite:
`tests/playwright/car-edit-missing-car.spec.js`'s self-submitting POST-form
pattern (mirroring the real "Update Car" button's actual markup).

**Corrected fix:** rewrote the test to perform that same real POST
(`action=updateCar`) against `CAR_ID_WITH_HISTORY` (a real, existing car,
fixture `1091`) instead of a GET. Verified empirically that `TEST_USERNAME`'s
Administrator permissions (`hasPerm([2,3])` bypass in
`updateCarDetails()`) grant update access regardless of car ownership, so
no car-seeding is needed at all — the original assumption that this test
was blocked on a missing fixture was itself wrong.

Discovered during this fix: mocking `fetchImages` to return a fabricated
image name didn't work once genuinely in update mode — the real DB-backed
image for car 1091 loads before/instead of the mock resolves (a timing
interaction with the POST-triggered page reload, not investigated further
since a better fix was available). **Removed the fetchImages mock
entirely** and instead capture whatever real filename(s) hydrate, then
assert those specific names survive in the outgoing `filenames=` field —
more robust than asserting against an assumed mock value, and exercises
the test's actual intent (mixed old+new file handling) against real data.

Verified: all 7 tests in the file pass genuinely (0 skipped), stable
across repeated runs (4 isolated runs of the corrected test, 2 full-file
runs).
