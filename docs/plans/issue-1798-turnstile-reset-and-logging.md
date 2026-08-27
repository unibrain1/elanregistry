# Issue #1798: login form never resets Turnstile widget; empty-token submissions logged nowhere

**Branch:** `bug/1798-turnstile-reset-and-logging`
**Milestone:** `milestone/v2.29.4`
**Status:** Implemented — pending commit/PR

**Post-review fixes applied (not in original checklist):** stale docblock in
`addTurnstile()` corrected (previously claimed callbacks are join-page-only
and unsafe from login — now describes the shared `turnstile-reset.js`);
a stale inline comment near the `$scriptId` assignment corrected similarly.

## Context

Two independent defects in the Turnstile integration, both confirmed via direct
source reads (not assumptions):

1. **Login widget never resets.** `login_form_turnstile.php` calls
   `addTurnstile()` with the default `$withFailureCallbacks = false`, so the
   login page's `.cf-turnstile` div never gets `data-error-callback`/
   `data-expired-callback` attributes — Cloudflare's widget has no callback to
   invoke on expiry, and `join-form-beacon.js` (which defines
   `elanTurnstileExpired`'s `turnstile.reset()` call) isn't loaded on
   `login.php` at all. This isn't a gating bug — the reset call itself was
   never wired to this page. A double-submit or an idle login tab past the
   token's ~300s lifetime produces an unrecoverable `timeout-or-duplicate`.
2. **Empty-token submissions are logged nowhere.** `verifyTurnstile()`
   (`usersc/includes/turnstile.php:100-103`) returns `false` on an empty
   `cf-turnstile-response` with zero `logger()` call — every other failure
   path in that file logs (Cloudflare rejection, cURL failure, invalid JSON).

User confirmed the fix approach: extract a small, shared, form-ID-agnostic
Turnstile reset helper (new file) rather than widening `join-form-beacon.js`'s
existing `#join-form` gate (which wouldn't fix anything — that gate only
covers the widget-render-poll and error/rejection reporting listeners, not the
reset call). `join-form-beacon.js` is refactored to call the shared helper
internally rather than duplicating `turnstile.reset()` logic, avoiding a
double-definition of `window.elanTurnstileExpired`/`elanTurnstileError` if both
scripts ever loaded on the same page.

## Bug Escape Analysis

- **Root cause (bug 1):** `login_form_turnstile.php` was written to call
  `addTurnstile()` with no argument (defaulting to `false`) rather than
  `addTurnstile(true)` — likely because, per `turnstile.php`'s own docblock,
  passing `true` without also loading `join-form-beacon.js` was known to be
  unsafe (undefined callback functions), and no login-appropriate reset script
  existed at the time. The safety guard prevented a worse bug (invoking
  undefined functions) but left the login form with no reset path at all.
- **Root cause (bug 2):** the empty-token branch in `verifyTurnstile()` was
  written as an early-return guard clause without a matching `logger()` call,
  unlike every sibling failure branch in the same function.
- **Testing gap:** no test exercises either path — no Playwright test
  double-submits the login form or lets a token expire before submit; no
  PHPUnit test asserts `verifyTurnstile()` logs on empty token.
- **Preventive measures:**
  - Playwright regression test on the login form's double-submit path (the
    join form's equivalent is already covered per the issue's acceptance
    criteria) — mock/force a Turnstile expiry event and confirm
    `turnstile.reset()` fires and a fresh token is used on resubmit.
  - PHPUnit unit test asserting `verifyTurnstile()` calls `logger()` with
    `LogCategories::LOG_CATEGORY_SECURITY` and the client IP when
    `cf-turnstile-response` is empty/absent, distinguishable from the existing
    Cloudflare-rejection log message.

## Database & Security Considerations

- No schema changes. No new DB writes beyond the new `logger()` call (existing
  `logs` table, existing `logger()` helper — no new write path).
- Security-relevant: this is CSRF/bot-verification code. The new log entry
  must not leak the token value itself (only logs "empty token submitted from
  `<ip>`", never token content) — matches the existing rejection log's
  practice of never logging raw tokens.
- No auth/session logic changes — `verifyTurnstile()`'s control flow
  (return `true`/`false`) is unchanged; only a `logger()` call is added before
  the existing `return false`.
- The new shared JS file adds no new endpoint, no new form, no new CSRF
  surface — it only calls Cloudflare's own `turnstile.reset()` global,
  identical to what `join-form-beacon.js` already does today.

## Architecture & Design

**New file:** `app/assets/js/turnstile-reset.js` — minimal, form-ID-agnostic:

```js
(function () {
    'use strict';
    window.elanTurnstileReset = function () {
        if (window.turnstile && typeof window.turnstile.reset === 'function') {
            window.turnstile.reset();
        }
    };
})();
```

No CSRF/fetch/beacon logic — deliberately narrower than `join-form-beacon.js`,
since login failures don't need "zero server trace" reporting (login already
logs failed attempts server-side via `login_fail_logger.php`).

**Register in `scripts/build.js`'s minification list** (alongside the other
`app/assets/js/*.js` entries) so `turnstile-reset.min.js` gets generated by
`npm run build`.

**`usersc/plugins/hooker/hooks/login_form_turnstile.php`** — change
`addTurnstile();` to `addTurnstile(true);`. Per `turnstile.php`'s existing
signature, this wires `data-error-callback="elanTurnstileError"` and
`data-expired-callback="elanTurnstileExpired"` onto the login widget — so the
login page also needs `window.elanTurnstileError`/`elanTurnstileExpired`
defined, not just a generic reset function. Define both as thin wrappers in
the new shared file:

```js
window.elanTurnstileError = function () { window.elanTurnstileReset(); };
window.elanTurnstileExpired = function () { window.elanTurnstileReset(); };
```

(No status-message DOM update or failure-beacon reporting — login.php has
no `#turnstile-status-message` element and no join-specific beacon endpoint
concern; a silent reset matches the acceptance criteria's actual requirement:
"the widget resets and the second submission carries a fresh token," not a
user-facing message.)

**`usersc/login.php`** — add
`<script src="<?=$us_url_root?>app/assets/js/turnstile-reset.min.js?v=<?= ASSET_VERSION ?>"></script>`
near the existing inline script blocks (no nonce needed — external `src`
scripts don't require the CSP nonce this codebase uses only for inline
`<script>` blocks, confirmed via `_join.php`'s identical pattern for
`join-form-beacon.min.js`).

**`app/assets/js/join-form-beacon.js`** — refactor `elanTurnstileExpired` to
call the shared `window.elanTurnstileReset()` instead of inlining its own
`turnstile.reset()` call, so there's a single source of truth for the reset
logic and no duplicate-definition risk if both scripts ever load on the same
page:

```js
window.elanTurnstileExpired = function () {
    reportJoinFailure('turnstile_expired');
    var msg = document.getElementById('turnstile-status-message');
    if (msg) {
        msg.textContent = 'Verification expired — please complete the verification challenge again before submitting.';
        msg.classList.remove('d-none');
    }
    if (typeof window.elanTurnstileReset === 'function') {
        window.elanTurnstileReset();
    }
};
```

This requires `turnstile-reset.js` to load *before* `join-form-beacon.js` on
`_join.php` — add its `<script>` tag immediately above the existing
`join-form-beacon.min.js` include in `usersc/views/_join.php`.

**`usersc/includes/turnstile.php`** — in `verifyTurnstile()`, move the
existing `global $remote_addr;` declaration above the `empty($token)` check
(currently declared after it) and add a `logger()` call in that branch:

```php
function verifyTurnstile(): bool
{
    if (!isTurnstileEnabled()) {
        return true;
    }
    global $remote_addr;
    $token = $_POST['cf-turnstile-response'] ?? '';
    if (empty($token)) {
        logger(0, LogCategories::LOG_CATEGORY_SECURITY, 'Turnstile: empty token submitted from ' . $remote_addr);
        return false;
    }
    return _verifyTurnstileToken($_ENV['TURNSTILE_SECRET_KEY'], $token, $remote_addr);
}
```

Matches the existing `LogCategories::LOG_CATEGORY_SECURITY` category and
message-wording convention (`'Turnstile <description>...'`) already used by
the sibling rejection-log call in the same file, and includes the client IP
per the issue's acceptance criteria.

## UserSpice Integration

No UserSpice framework functionality duplicated — `logger()`, `LogCategories`,
and the hook-file pattern (`login_form_turnstile.php`) are all existing
project/UserSpice mechanisms, used as-is. No changes to `/users/` (upstream,
do-not-modify per CLAUDE.md).

## Implementation Checklist

- [x] Create `app/assets/js/turnstile-reset.js` with `elanTurnstileReset`,
      `elanTurnstileError`, `elanTurnstileExpired` — `app/assets/js/turnstile-reset.js`
      (parallel-safe)
- [x] Register the new file in `scripts/build.js`'s minification list —
      `scripts/build.js` (depends on: new file created)
- [x] Change `addTurnstile();` to `addTurnstile(true);` —
      `usersc/plugins/hooker/hooks/login_form_turnstile.php` (parallel-safe)
- [x] Add `<script src=".../turnstile-reset.min.js">` tag to `usersc/login.php`
      near existing script blocks (depends on: build.js registration, for the
      `.min.js` file to exist)
- [x] Add the same `<script>` tag to `usersc/views/_join.php`, positioned
      before the existing `join-form-beacon.min.js` include (depends on:
      build.js registration)
- [x] Refactor `join-form-beacon.js`'s `elanTurnstileExpired` to call
      `window.elanTurnstileReset()` instead of inlining `turnstile.reset()` —
      `app/assets/js/join-form-beacon.js` (depends on: turnstile-reset.js
      created)
- [x] Move `global $remote_addr;` above the `empty($token)` check and add the
      `logger()` call in `verifyTurnstile()` —
      `usersc/includes/turnstile.php` (parallel-safe, independent PHP file)
- [x] Run `npm run build` to regenerate `.min.js` outputs, verify
      `turnstile-reset.min.js` and updated `join-form-beacon.min.js` are
      produced — confirmed both files generated
- [x] Write PHPUnit test: `verifyTurnstile()` logs
      `LogCategories::LOG_CATEGORY_SECURITY` with client IP on empty token —
      `tests/unit/security/TurnstileTest.php` (new file, 4 tests, all pass)
- [x] Write Playwright test: login-form double-submit / expired-token
      resubmit path — added to `tests/playwright/login-functionality.spec.js`
      (4 new tests: attribute wiring [skips locally, no Turnstile keys],
      elanTurnstileExpired/Error call reset without throwing, double-submit
      doesn't throw — all pass; join-form-beacon.spec.js's 11 existing tests
      re-verified passing after the beacon refactor)
- [x] PHPStan baseline hygiene: confirm `usersc/includes/turnstile.php`
      carries no pre-existing `phpstan-baseline.neon` entries (fix or
      explicitly defer per `/execute-plan` Step 6.5) — confirmed clean, no
      baseline entry, `vendor/bin/phpstan analyse` reports no errors
- [x] Run `/security-review` (auth-adjacent code touched), address
      Critical/High findings — clean, 0 findings (verified: no injection risk
      from addTurnstile(true), no DOM XSS/prototype pollution in the new/
      refactored JS, no token leakage in the new log line, CSP-compliant
      script tags)
- [x] Run `senior-architect` review of the diff, address findings — one
      pre-merge item found and fixed (stale docblock in `addTurnstile()`
      claiming callbacks are join-page-only, now updated to describe the
      shared `turnstile-reset.js`); 3 test-coverage recommendations
      addressed: (1) new PHPUnit tests asserting `addTurnstile(true)`'s
      actual HTML output — deterministic coverage independent of CI having
      no live Turnstile keys configured, (2) new Playwright test confirming
      `turnstile-reset.js` loads before `join-form-beacon.js` on the join
      page, (3) new Playwright tests (both pages) confirming the reset
      handlers don't throw when `window.turnstile` is undefined. No dead
      code found (old inline reset call cleanly replaced, not left behind).

## Test Plan

**Not verified end-to-end against a live Cloudflare widget on this machine.**
`isTurnstileEnabled()` requires HTTPS; this dev machine's MAMP setup only
serves HTTP, and the Traefik HTTPS proxy option (per CLAUDE.md) was tried and
disabled. All PHPUnit/Playwright tests below exercise the fixed code paths
directly and pass, but the real browser/Cloudflare-widget interaction —
`data-error-callback`/`data-expired-callback` actually firing on real token
expiry, the widget visibly resetting, a double-submit actually recovering —
has not been manually observed. Noted in release notes' "Required Actions
After Deployment" as a manual verification step on test.elanregistry.org.

**PHPUnit (unit, no DB):** new test in `tests/unit/` (or an existing
Turnstile-related test file if one exists — check during implementation)
asserting `verifyTurnstile()`:

- Returns `false` and calls `logger()` exactly once with
  `LogCategories::LOG_CATEGORY_SECURITY` and a message containing the mocked
  `$remote_addr` when `cf-turnstile-response` is absent/empty.
- Does not call `logger()` on this path when a non-empty token is present
  (existing behavior, regression guard).

**Playwright:** new test (or an addition to an existing Turnstile-related
spec) in `tests/playwright/`, following the credentials-optional
(`skipIfNoCreds()`-equivalent) pattern used elsewhere in this suite:

- Navigate to the login page, confirm the `.cf-turnstile` div now carries
  `data-error-callback`/`data-expired-callback` attributes (previously
  absent).
- Simulate a Turnstile expiry (call `window.elanTurnstileExpired()` directly
  via `page.evaluate` — the same technique other specs in this suite use to
  exercise client-side JS behavior without depending on Cloudflare's actual
  timing) and assert `window.turnstile.reset` was invoked (e.g. by stubbing
  `window.turnstile.reset` with a spy before triggering expiry).
- Confirm no JS errors are thrown when `elanTurnstileExpired`/`Error` fire on
  the login page (regression guard against the exact "undefined window
  function" failure mode the original `addTurnstile()` docblock warned about).

No new integration test needed — `verifyTurnstile()`'s only DB-adjacent
behavior is the `logger()` call itself, which is fully exercised at the unit
tier via the existing `$mockLogEntries` spy pattern
(`tests/bootstrap-unit.php`).

## Documentation Plan

No `docs/development/*.md` changes required — `ERROR_HANDLING.md`'s
`logger()`/`LogCategories` guidance already covers this call site's pattern
and needs no update; no new class, endpoint, or schema is introduced. If
`docs/development/CLASSES.md` or similar documents `turnstile.php`'s public
functions with signatures, verify `addTurnstile()`'s signature is unchanged
(it is — only the call-site argument changes) so no doc update is triggered
there either.
