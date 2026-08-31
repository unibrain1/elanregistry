# Issue #1852: DataTables AJAX endpoint returns 403 for authenticated session (ajax-endpoints.spec.js:150)

**Branch:** `issue/1852-datatables-ajax-403`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

- **Root cause:** `tests/playwright/ajax-endpoints.spec.js`'s "DataTables
  AJAX endpoint returns car data" test POSTs to `app/api/cars/list.php`
  without a CSRF token and expects `200`, but gets `403`.
  `app/api/cars/list.php:40-42` requires a valid CSRF token
  (`Token::check($token)`) on every POST — confirmed true since the
  endpoint was created in PR #1076 (June 2026), not a recent regression.
  Production's own JS consumer, `app/assets/js/car-list.js`/`car-list.min.js`
  (`data: function(e){e.csrf=window.carListConfig.csrf}`), always sends this
  token. The failing test simply never includes a `csrf` field in its POST
  body — a test bug, not an application regression. Every other test in
  this same file that expects a non-403 response already fetches a real
  token via the file's own `getCsrfFromSettingsPage(page)` helper
  (`tests/playwright/ajax-endpoints.spec.js:14-22`) before posting; this one
  test is the outlier that skips that step.
- **Why it reached this state:** the test was likely written without
  checking the endpoint's actual CSRF requirement, and nothing caught the
  mismatch because Playwright tests aren't part of any CI gate (confirmed
  during #1781 work — no GitHub Actions workflow invokes Playwright at all;
  it's purely a manual/local developer tool). A failing local test doesn't
  block anything.
- **Testing gap:** no check enforces that `getCsrfFromSettingsPage()` is
  used consistently by every test expecting a non-403 response from a
  CSRF-protected endpoint in this file — that's a hand-maintained
  convention, not an enforced one.
- **Preventive measure:** not adding an automated check for this
  (disproportionate for one file); the fix itself demonstrates the correct
  pattern inline, which is the existing convention for this file already.

## Architecture & Design

Apply the exact same fix pattern already used by the file's other
non-hardcoded-CSRF tests (e.g. `chassis_check rejects an invalid command
with a real CSRF token`, lines 130-147): call `getCsrfFromSettingsPage(page)`
before the POST, `test.skip()` if a token couldn't be obtained (consistent
with the rest of the suite's auth-failure handling), and include the token
in the POST body's `form` object.

No new helper needed — `getCsrfFromSettingsPage()` already exists in this
file (lines 14-22) and is reused as-is.

## Implementation Checklist

- [x] Update the "DataTables AJAX endpoint returns car data" test
      (`tests/playwright/ajax-endpoints.spec.js:150-180`) to call
      `getCsrfFromSettingsPage(page)` and include the resulting `csrf` value
      in the POST body's `form` object; add `test.skip(!csrf, ...)` matching
      the file's existing convention — `tests/playwright/ajax-endpoints.spec.js`
- [x] Run the test locally against MAMP
      (`npx playwright test tests/playwright/ajax-endpoints.spec.js -g "DataTables AJAX endpoint"`)
      and confirm it passes (200, correct DataTables JSON shape) — passed
      (1 passed, 6.9s)
- [x] Run the full `ajax-endpoints.spec.js` file to confirm no regression to
      the other tests in it — 19 passed, 1 skipped (pre-existing,
      unrelated fixture-avoidance skip), 0 failed
- [x] PHPStan baseline hygiene: not applicable — no PHP files touched
- [x] Run `/security-review` — not required (test-only change, no
      forms/SQL/auth production code touched)
- [x] Run `senior-architect` review of the diff, address findings — clean,
      no blocking or recommendation items. Confirmed genuine root-cause
      fix (test was wrong, not the endpoint), correct non-duplicative reuse
      of the existing `getCsrfFromSettingsPage()` helper, no masking of a
      real problem.

## Test Plan

- No new test file or PHPUnit involvement — this is a one-test fix within
  an existing Playwright spec file.
- Verification is running the specific test plus the full file locally
  against MAMP (`TEST_USERNAME`/`TEST_PASSWORD` already configured in
  `.env.local` per #1781 work).
