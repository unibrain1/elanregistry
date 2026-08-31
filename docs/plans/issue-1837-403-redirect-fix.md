# Issue #1837: privacy.php and car-transfer-faq.php redirect to nonexistent root-level /403.php

**Branch:** `bug/1837-403-redirect-fix`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

**Root Cause:** Both `app/owner/privacy.php:23` and `docs/guides/car-transfer-faq.php:12`
call `Redirect::to($us_url_root . '403.php')` when `securePage($php_self)` returns
`false`, but neither calls `die()`/`exit` afterward. `Redirect::to()` (see
`users/classes/Redirect.php`) only sends an HTTP `Location` header — it does not
stop script execution. As a result, when `securePage()` denies access, the page
sends a redirect header and then **keeps executing and rendering the protected
content anyway**. The nonexistent `403.php` target reported in the issue (now a
plain 404, previously would have been a legacy root-level file) is a real but
secondary defect — even pointing the redirect at a real page would not fix the
underlying problem, since the content renders regardless of where the header
points.

Every other `securePage()` caller in the codebase (`app/owner/contact/owner.php`,
`app/owner/contact/index.php`, `app/owner/cars/details.php`,
`app/owner/cars/factory.php`, `app/owner/cars/edit.php`,
`app/owner/cars/index.php`, `app/owner/reports/statistics.php`,
`app/admin/maintenance.php`, `app/admin/index.php`,
`app/admin/design-system.php`, `app/admin/includes/process-admin-contact.php`,
admin fix-scripts) uses the plain `die();` idiom with no redirect at all —
these two files are the only outliers.

**Why this reached production:** No Playwright coverage exercises the
`securePage()`-denied path for either page — only the authenticated-owner
success path is tested (`tests/playwright/privacy-page.spec.js`, added via
issue #1253). A test asserting that denied access does not render the
protected heading/content would have caught the missing `die()`.

**Preventive Measures:** This plan fixes the guard to match the established
`die();` convention (see Architecture & Design and the user's explicit
decision below) — this alone closes the execution-continues gap
structurally, independent of any test. Full black-box regression coverage of
the denied path is deferred (see Test Plan) — reproducing an authenticated,
*insufficient-permission* session locally requires DB fixture support
(`auth-helper.js` currently offers only one fixed test identity) that does
not exist yet; that infrastructure gap is out of scope for a Low-severity,
2-file bug fix.

## UserSpice Integration

`securePage()` is the existing UserSpice/ElanRegistry permission-check
function (`users/helpers/permissions.php:308`) — no new functionality is
introduced. This fix only corrects how its `false` return value is handled,
matching the idiom every other caller already uses.

## Database & Security Considerations

This is a real (if narrow) access-control defect: an unauthorized request to
either page currently renders the protected content instead of being denied,
because the redirect never stops execution. No schema, auth mechanism, or
CSRF change — `securePage()`'s own logic is untouched. Fixing the two call
sites to `die()` after a denied check closes the gap.

## Architecture & Design

**Decision (user-selected):** Match the codebase-wide `die();` convention
rather than introduce a new "branded 403 page" redirect pattern.

Investigated the alternative (redirect to the consolidated `error/500.php`
handler from #1830) and ruled it out: `error/500.php`'s status code comes
from `$_SERVER['REDIRECT_STATUS']`, which is only set by Apache's
`ErrorDocument` directive (`.htaccess` lines 3-11) when a *real* HTTP error
response triggers the rewrite — not from a client-side `Redirect::to()` call
to a normal 200-status page. Reaching `error/500.php` via `Redirect::to()`
would render the generic fallback message, not a 403-specific one, unless a
new mechanism (e.g. `http_response_code(403)` before an inline `require`)
were introduced — a pattern not used anywhere else in the codebase. Given
the bug's actual severity (broken access control, not just "wrong page
shown"), the simpler, already-proven `die();` idiom is the right fix: it
matches 12+ existing call sites, requires no new pattern, and immediately
closes the execution-continues gap.

**Change, both files identical:**

```php
if (!securePage($php_self)) {
    die();
}
```

(Replaces `Redirect::to($us_url_root . '403.php');` with no trailing
statement.)

No other logic in either file is touched.

## Implementation Checklist

- [x] Replace `Redirect::to($us_url_root . '403.php');` with `die();` in
      `app/owner/privacy.php:23` — `app/owner/privacy.php` (parallel-safe)
- [x] Replace `Redirect::to($us_url_root . '403.php');` with `die();` in
      `docs/guides/car-transfer-faq.php:12` — `docs/guides/car-transfer-faq.php`
      (parallel-safe)
- [x] Manually verify locally (MAMP): confirm both pages still render
      normally for an authenticated owner (no regression to the success
      path) — verified: both `app/owner/privacy.php` and
      `docs/guides/car-transfer-faq.php` have `private = 0` in the `pages`
      table (confirmed via direct DB query), so `securePage()` returns
      `true` for anonymous/any users by design — both pages render their
      full content correctly with the `die()` guard in place, no regression
- [x] PHPStan baseline hygiene: confirm neither touched file carries
      pre-existing `phpstan-baseline.neon` entries — clean, neither file
      appears in `phpstan-baseline.neon`
- [x] Run `senior-architect` review of the diff, address findings — clean,
      no changes requested; confirmed `die();` matches the established
      idiom exactly (5 other callers sampled, all bare `die()`, no status
      code or message), and confirmed no dangling `Redirect` usage remains
      in either file

## Test Plan

No existing Playwright test exercises the `securePage()`-denied path for
either page (`privacy-page.spec.js` only covers the logged-in success path).
Reproducing that path requires an authenticated session with insufficient
page permission — `tests/playwright/auth-helper.js` currently only supports
one fixed test identity with no permission-level control, so building
real regression coverage for the denied path would require new fixture
infrastructure, out of proportion to this 2-line, Low-severity fix.

Verification for this fix is therefore manual: confirm via MAMP that both
pages continue to render correctly for the authenticated owner (no
regression), and confirm via code review that `die()` now actually halts
execution on a denied check (a direct, mechanical property of the change,
not something that needs a browser to observe). A follow-up test-
infrastructure enhancement (permission-level control in `auth-helper.js`,
enabling a real "denied access renders nothing" regression test across all
`securePage()`-gated pages) is noted as a deferred enhancement, not blocking
this fix.

## Documentation Plan

None — no public API, schema, or user-facing doc describes this internal
access-control guard behavior.
