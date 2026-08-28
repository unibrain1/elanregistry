# Issue #1313: fix: edit.php force-logs-out a user when the car is missing/merged instead of showing 404

**Branch:** `bug/1313-edit-car-not-found-logout`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

The issue as originally filed described `app/owner/cars/edit.php`'s
`updateCarDetails()` force-logging-out an innocent owner when their car was
deleted/merged, because the ownership comparison ran against a `null`
`$carQ->data()`. **Verified directly against current code: this specific
defect no longer exists.** An `exists()` guard (`edit.php:107-111`, added by
an earlier commit for #1300) already returns early — before the ownership
comparison at line 114 — whenever the car is missing, so the
`$user->logout(); exit();` branch (lines 120-121) can never fire on a
missing/merged car anymore.

Confirmed with the user before planning around this: the residual defect is
narrower than the original title. On a missing car, `updateCarDetails()`
returns silently (line 110) with no user-facing message and no redirect —
the page still renders with `$cardetails`' fields left at their unset
defaults, giving the owner a blank/broken-looking edit form with no
explanation, rather than the clear "not found" message + redirect the
issue's acceptance criteria actually call for. This session's scope is
narrowed to fixing that silent-failure gap, matching the existing pattern
already used by `app/owner/cars/details.php` for the identical scenario.

## Bug Escape Analysis

- **Root cause:** The `exists()` guard was added (#1300) purely to prevent
  the null-dereference/wrong-branch hazard — it stops the *dangerous*
  behavior (force-logout) but never added the *correct* behavior (a visible
  "not found" message + redirect) that a sibling file, `details.php`,
  already has for the same scenario. The fix addressed the security bug but
  left the UX gap #1313 also asked for unaddressed.
- **Testing gap:** No PHPUnit or Playwright test exercises `edit.php`'s
  `updateCarDetails()` at all — confirmed via repo-wide search, no matches
  for the function name outside the file itself. The closest test,
  `tests/playwright/security/car-update-ownership.spec.js`, covers a
  *different* code path (the AJAX `save.php?action=updateCar` endpoint, not
  `edit.php`'s GET-render flow) and would not have caught either the
  original force-logout bug or this narrower silent-failure gap.
- **Preventive measure:** Add a Playwright test loading
  `edit.php?car_id=<nonexistent-id>` as an authenticated owner and asserting
  (a) no forced logout occurs (still logged in afterward — regression guard
  for the original bug, now fixed elsewhere but worth locking in), and (b) a
  visible "not found" message appears with a redirect to `cars/index.php`
  (the actual fix this issue adds).

## Database & Security Considerations

- No schema/migration changes.
- No new attack surface — this only adds a message + redirect on an already-
  guarded early-return path; it does not change what triggers that path or
  who can reach it.
- Confirms (doesn't change) that a genuine ownership violation still logs
  out as before — the `exists()` guard and the ownership check remain two
  independent, sequential gates; only the exists-guard's *consequence*
  changes (redirect+message instead of silent return).

## Architecture & Design

**Chosen approach:** Mirror `details.php`'s existing missing-car pattern
(`details.php:37-42`) inside `edit.php`'s `updateCarDetails()`'s `exists()`
branch (lines 107-111):

```php
function updateCarDetails(array &$car): void
{
    global $user, $us_url_root;
    ...
    if (!$carQ->exists()) {
        logger($user->data()->id, LogCategories::LOG_CATEGORY_CAR_ACTIONS,
            'Car not found for edit: car_id=' . $car['id'] . ' user_id=' . $user->data()->id);
        usError('This car could not be found.');
        Redirect::to($us_url_root . 'app/owner/cars/index.php');
        exit;
    }
```

**Third deviation caught during planning, verified not assumed:** `details.php`'s
`Redirect::to($us_url_root . ...)` calls happen at top-level script scope,
where `$us_url_root` is already a live variable in that file. `edit.php`'s
`updateCarDetails()` is a PHP function, and functions do not inherit
outer-scope variables automatically — the function currently only declares
`global $user;` (line 98), not `$us_url_root`. Copying `details.php`'s
redirect call verbatim into this function would silently produce an empty
`$us_url_root` (undefined-variable warning + a redirect to `'app/owner/cars/index.php'`
relative to whatever the current working path resolves to, not the site
root) — confirmed by grepping the function body, `$us_url_root` is not
referenced anywhere inside `updateCarDetails()` today. The fix adds
`global $us_url_root;` alongside the existing `global $user;` to make the
redirect target correct.

Two deliberate deviations from `details.php`'s exact pattern, both confirmed
via research rather than assumed:

1. **Keep the existing `LOG_CATEGORY_CAR_ACTIONS` category**, not
   `LOG_CATEGORY_CAR_ERRORS` as the issue's "Recommended fix" text
   suggested. `edit.php`'s `updateCarDetails()` already uses
   `LOG_CATEGORY_CAR_ACTIONS` consistently for all four of its `logger()`
   calls (lines 101, 108, 119, 126); switching just this one call to a
   different, currently-unused-anywhere category (`LOG_CATEGORY_CAR_ERRORS`
   has zero call sites repo-wide) would be an inconsistent, unmotivated
   change within the same function. `details.php` itself uses yet a third
   category (`LOG_CATEGORY_VALIDATION_ERROR`) for the analogous case, so
   there's no single established convention to defer to — staying consistent
   with the surrounding function's own established category is the
   least-surprising choice.
2. **Add `usError('This car could not be found.')`** before the redirect —
   `details.php`'s pattern doesn't call `usError()` for this case (it
   redirects silently), but the issue's own acceptance criteria explicitly
   require "shows a 'not found' message," so this fix needs the flash
   message `details.php` happens not to have. `usError()`'s signature and
   behavior confirmed directly (`users/helpers/us_helpers.php:1867-1873` —
   thin wrapper over `sessionValMessages()`, sets a session flash message,
   does not itself redirect or halt), and its call pattern (`usError($msg)`
   with a single string) matches existing usage elsewhere in this same file
   (`edit.php:81-85`).

**The display-time admin-override-warning block** (`edit.php:143-156`,
originally cited in the issue as a "duplicate check at ~147-158") is
confirmed structurally different and NOT in scope for this fix: it already
null-guards inline (`$editCarData !== null && ...`) rather than calling
`exists()`, never calls `logout()`/`exit()`, and its only consequence for a
missing car is silently not rendering an admin-override banner — which is
correct behavior (there's nothing to show an override warning about if the
car doesn't exist), not a defect. No change needed there.

## Implementation Checklist

- [x] Add `usError('This car could not be found.')` and
      `Redirect::to($us_url_root . 'app/owner/cars/index.php'); exit;` to
      `updateCarDetails()`'s `exists()` branch, keeping the existing
      `logger()` call and `LOG_CATEGORY_CAR_ACTIONS` category —
      `app/owner/cars/edit.php` (parallel-safe). Added `global $us_url_root;`
      alongside existing `global $user;`. PHPStan clean, no baseline debt.
- [x] Add a Playwright test — new file
      `tests/playwright/car-edit-missing-car.spec.js` (heavier FilePond/AJAX
      mocking in `car-edit-text-save.spec.js` made this a meaningfully
      different setup). Test reproduces the real POST-triggered code path
      (GET alone never exercises `updateCarDetails()`), waits for
      `Redirect::to()`'s inline-script fallback (headers already sent by
      the time the guard fires), and asserts against the UserSpice toast
      selector (`.us-toast .toast-body`). Passed 2/2 on
      `--repeat-each=2`.
- [x] Run `composer test:medium`, verify pass
- [x] Run `npm run playwright:test` (or the most specific matching script)
      for the new test, verify pass — confirmed via targeted run above
- [x] PHPStan baseline hygiene: confirm `edit.php` carries no pre-existing
      `phpstan-baseline.neon` entries — none found
- [x] Run `senior-architect` review of the diff, address findings — **Go**,
      0 blocking findings. Independently re-verified `Car::exists()`,
      `Redirect::to()`'s inline-script fallback, `$us_url_root`'s
      server-side-only provenance (no open-redirect risk), and the
      display-time block's out-of-scope determination.

(No `/security-review` needed — no forms/SQL/auth logic changed, only a
message + redirect added to an already-existing, unchanged guard condition.)

## Test Plan

- New Playwright test covering the missing-car scenario end-to-end: no
  forced logout (regression guard for the originally-reported bug, already
  fixed but now locked in by a real test for the first time), visible "not
  found" message, redirect to `cars/index.php`.
- No existing tests are expected to break — the `exists()` guard's trigger
  condition is unchanged; only what happens inside that branch changes.
- **Added post-implementation, found by `/review-pr`'s pr-test-analyzer:**
  the plan's own "Confirms (doesn't change) that a genuine ownership
  violation still logs out as before" claim was asserted, not tested.
  `TEST_USERNAME` turned out to be an admin (permission_id=2) and therefore
  structurally unable to reach the logout branch. Initially closed via a
  throwaway non-admin account registered per test run; refactored (at user
  request) to a persistent `TEST_USERNAME2`/`TEST_PASSWORD2` non-admin
  account in `.env.local` (gitignored), reusable by any future test needing
  a plain-owner identity — faster (~16s vs. ~23s for the full file) and
  avoids sharing `account-enumeration.spec.js`'s registration rate-limit
  budget. Verified server-side (session file loses its `user` key, the
  expected `logger()` line fires), not just via a client-side redirect
  assertion.

## Documentation Plan

None — internal bug fix, no public API/schema/user-facing doc change beyond
the behavior itself (which is the fix).
