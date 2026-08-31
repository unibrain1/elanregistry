# Issue #1765: Fix ajax-endpoints.spec.js — remove stale mapmarkers.xml.php test

**Branch:** `bug/1765-ajax-endpoints-test-failures`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Context

Verifying #1623's `baseURL` fix exposed 2 findings in
`tests/playwright/ajax-endpoints.spec.js` that were previously masked (every
test in this file failed at the login step before #1623 landed).

**Finding #2 (CSRF 400-vs-403 discrepancy) is already resolved** — confirmed
independently by Explore and by a direct local run (6/6 passes,
`--repeat-each=5`). Root cause: PR #1790 (merged 2026-08-26, the day after
issue #1765 was filed) rewrote this exact test as an unrelated tech-debt change —
switched the first assertion's payload from a JSON `data:` body with a
hardcoded fake CSRF token (`'test_token'`) to a `form:`-encoded POST with a
**real** CSRF token fetched via `getCsrfFromSettingsPage()`. That side effect
happened to fix the exact discrepancy #1765 flagged: the old fake-token
payload was landing on the missing-`command`-parameter 400 branch (in
`app/api/cars/chassis-availability.php`) for a reason unrelated to CSRF-check
correctness, and the rewritten test no longer exercises that path.
Static trace of `chassis-availability.php` (CSRF check unconditional, always
first, no session-dependent branch in `Token::check()`) supports this
explanation — no live bug found. Per user decision, closing this finding as
already-resolved by #1790 rather than digging further, since evidence
(6/6 passes today, code-level trace agrees, git history shows exactly the
right kind of side-effect fix) is convergent, not circumstantial.

**Finding #1 (dead `mapmarkers.xml.php` endpoint) still reproduces** and is
this issue's actual remaining scope. `app/cars/mapmarkers.xml.php` was
intentionally deleted in commit `ca252732` (PR #837/#724, "replace Google
Maps with self-hosted MapLibre GL JS + VersaTiles") — marker data moved to
inline `window.elanMapMarkers` JSON emitted server-side by
`app/owner/reports/statistics.php`, no longer served via a separate
endpoint. The migration's own commit message confirms this ("Playwright
tests rewritten for MapLibre assertions"), and `tests/playwright/maps-charts.spec.js`
already covers the `window.elanMapMarkers` inline-JSON mechanism correctly
— this one stale test in `ajax-endpoints.spec.js` was simply missed when
the rest of the suite was updated.

**Fix (user-selected):** delete the stale test — no replacement endpoint
exists to point it at, and duplicate coverage in a second file for
already-tested behavior isn't warranted.

## Bug Escape Analysis

- **Root cause:** `ajax-endpoints.spec.js`'s Google Maps XML-endpoint test
  was not updated when `mapmarkers.xml.php` was deleted during the MapLibre
  migration (`ca252732`), while the sibling `maps-charts.spec.js` suite was
  correctly rewritten in the same PR.
- **Testing gap:** this file's tests were masked by an unrelated bug (#1623's
  `baseURL` misconfiguration) for an unknown period, so the stale assertion
  never actually ran and failed loudly — it silently existed as dead test
  code until #1623 was fixed and this file's tests could execute for real.
- **Preventive measures:** none needed beyond the fix itself — this is an
  isolated stale-test cleanup, not a systemic gap. The `baseURL` fix (#1623)
  already restores this file's ability to catch future regressions.

## UserSpice Integration

None — Playwright test-infrastructure only.

## Database & Security Considerations

None — deleting a test for a non-existent endpoint. No production code
changes. Finding #2's investigation touched CSRF-check logic
(`app/api/cars/chassis-availability.php`, `users/classes/Token.php`) but only
via read-only tracing — no code changes needed there, since no bug was found.

## Architecture & Design

**Delete** the test block at `tests/playwright/ajax-endpoints.spec.js:182-196`
(`'map markers XML endpoint returns valid data'`) in its entirety — no
replacement test needed in this file, since `tests/playwright/maps-charts.spec.js`
already asserts on the current `window.elanMapMarkers` mechanism this
functionality was replaced by.

No other files change. No new test is added (removing dead coverage for
dead functionality, not adding new coverage).

## Implementation Checklist

- [x] Delete the `'map markers XML endpoint returns valid data'` test block
      (lines 182-196) — `tests/playwright/ajax-endpoints.spec.js` — ESLint
      clean
- [x] Run `npx playwright test tests/playwright/ajax-endpoints.spec.js`,
      confirm the file now passes cleanly except the one pre-existing,
      unrelated `DataTables AJAX endpoint returns car data` 403 failure —
      confirmed: 18 passed, 1 skipped (intentional), exactly that 1
      pre-existing failure remains, matching prediction exactly
- [x] PHPStan baseline hygiene: N/A — no PHP files touched, confirmed
- [x] Run `senior-architect` review of the diff, address findings — 0
      findings across all severities; independently verified the
      maps-charts.spec.js replacement-coverage claim is true (and provides
      more depth than the deleted test ever did), confirmed no dangling
      references remain repo-wide

## Test Plan

- No new test — this is a dead-test removal. Verification is running the
  file and confirming the deleted test no longer appears/fails, and that no
  other test in the file regresses.
- Existing `tests/playwright/maps-charts.spec.js` coverage of
  `window.elanMapMarkers` is unaffected — not touched by this change,
  already covers the replacement mechanism.

## Documentation Plan

- None — no doc references `mapmarkers.xml.php` (confirmed via repo-wide
  grep during Explore).
