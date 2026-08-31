# Issue #1253: Playwright coverage for remaining owner pages

**Branch:** `issue/1253-owner-page-playwright-coverage`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Context

4 owner-authenticated pages had zero Playwright coverage. One
(`app/owner/contact/owner.php`) already got covered via #1585's branch —
`tests/playwright/contact-owner-page.spec.js` landed as a side effect of
that PR's review pass (confirmed via its own comment history). This issue's
remaining scope is the other 3: `app/owner/privacy.php`,
`usersc/user_settings.php`, `users/verify.php`.

The issue's own suggested approach ("use the existing saved auth state, the
`logged-in` project") doesn't match reality: `playwright.config.js` (local
MAMP config) defines no `logged-in` project at all — that project only
exists in the e2e configs (`playwright.config.test.js`/`.prod.js`), gated on
a saved auth file, and only matches `tests/playwright/e2e/*logged-in.spec.js`
files run against deployed servers. #1781 tracks that gap separately. The
already-landed `contact-owner-page.spec.js` instead uses per-test
`ensureLoggedIn()` (from `auth-helper.js`) under `tests/playwright/` directly
— confirmed as the actual working local pattern, and this issue follows it
rather than building new e2e/auth-file infrastructure (a deliberate,
user-confirmed scope boundary — building a local `logged-in` project is
issue #1781's concern, not this issue's).

**Scope decisions made during planning:**

- `usersc/user_settings.php` (ElanRegistry's customized version — Location
  Picker widget, literal "Update your user settings" heading), not
  `users/user_settings.php` (upstream — language-string-driven heading, has
  a `forceReauth()` step-up that could redirect a fresh test session).
  `usersc/user_settings.php` is what other tests already interact with
  (`ajax-endpoints.spec.js`'s CSRF-token extraction) and what ElanRegistry
  actually serves as its customization-layer override.
- `verify.php`'s "verified" success-path state needs a real DB user row with
  a valid, unexpired vericode — no such fixture exists today, and building
  one is real, separate work (a new fixture user + `hash_equals()`-matching
  vericode). Scoped down to the no-query-params path, which falls through to
  the error/unverified view with zero new fixtures — the plan documents this
  as a known, deliberate gap (not silently dropped) rather than blocking
  this issue on new fixture infrastructure.

## UserSpice Integration

None — pure test-infrastructure addition. `users/verify.php` and
`users/user_settings.php` are read from (never modified) per CLAUDE.md's
upstream-read-only rule; `usersc/user_settings.php` is also read-only here
(it's the target under test, not something this issue changes).

## Database & Security Considerations

None — no schema/auth/CSRF code changes. Tests exercise existing
`securePage()`-gated pages via the existing `ensureLoggedIn()` session
helper; no new credentials or personal data introduced (per the issue's own
acceptance criterion).

## Architecture & Design

Add 2 new Playwright spec files under `tests/playwright/`, following
`contact-owner-page.spec.js`'s exact structure (imports, single
`test.describe`, `beforeEach` → `ensureLoggedIn(page)`, `page.goto(path, {
waitUntil: 'domcontentloaded' })`, login-redirect skip guard, then targeted
locator assertions):

**`tests/playwright/privacy-page.spec.js`** — one test:

- Navigate to `app/owner/privacy.php`
- Assert URL still contains `privacy.php` (no login/403 redirect)
- Assert `h2:has-text("Privacy Policy")` visible (the card heading, per
  `app/owner/privacy.php:66`)
- Assert at least one policy-content anchor/section is present (e.g. a GDPR
  rights section) to confirm the static content actually rendered, not just
  the page chrome

**`tests/playwright/user-settings-page.spec.js`** — one test:

- Navigate to `usersc/user_settings.php`
- Assert URL still contains `user_settings.php` (no login/403 redirect)
- Assert `h1:has-text("Update your user settings")` visible
  (`usersc/user_settings.php:503`)
- Assert key form fields present: username, first name, last name, email,
  confirm-email (per `usersc/user_settings.php`'s rendered fields), and the
  submit button
- Assert the Location Picker widget container (`#location-picker-settings`)
  is present, since it's a distinguishing feature of this customized page
  vs. the upstream version

**`tests/playwright/verify-page.spec.js`** — one test (scoped per the
decision above):

- Navigate to `users/verify.php` with no query parameters
- Assert the page renders the error/unverified view (per `verify.php`'s
  fallthrough at line 330 rendering `_verify_error.php`) rather than a raw
  PHP error or blank page — `users/views/_verify_error.php:24` renders
  `<h1><?=lang("VER_FAIL");?></h1>`, a language-string, not a literal —
  assert on the `h1` element being visible and non-empty rather than
  hardcoding text, or resolve `VER_FAIL`'s actual string value during
  implementation (grep the language file) and assert on that exact text if
  it's stable
- **Does not test the "verified" success-path state** — no fixture exists
  for a real user+vericode pair. Left as a checklist item explicitly noted
  as deferred, not silently dropped, matching the issue's own acceptance
  criteria gap.

No changes to `fixtures.js` (none of the 3 pages need new fixture values
given this scope) and no changes to `playwright.config.js` (both new spec
files run under the existing `chromium`/`Mobile Chrome` default projects,
same as `contact-owner-page.spec.js`).

## Implementation Checklist

- [x] Add `tests/playwright/privacy-page.spec.js` — 1 passed. Deviated from
      the plan's suggested `#your-rights-under-gdpr` selector (that id is on
      an invisible `aria-hidden` permalink, not visible content) — asserts
      on the visible `h2:has-text("Your rights under GDPR")` heading instead,
      documented in a code comment.
- [x] Add `tests/playwright/user-settings-page.spec.js` — 1 passed. Confirmed
      exact field ids from the file: `#username`, `#fname`, `#lname`,
      `#email`, `#confemail`, `#location-picker-settings`.
- [x] Add `tests/playwright/verify-page.spec.js` — 1 passed, no-params/
      error-view path only. `VER_FAIL`'s literal string was found
      (`users/lang/en-US.php:573`) but deliberately not hardcoded — active
      language isn't confirmed pinned to en-US at runtime, so the test
      asserts the `h1` is visible and non-empty instead of exact text (a
      more robust judgment call than the plan's literal suggestion).
      Deferred-gap comment included per plan requirement.
- [x] Run `npm run playwright:test` locally — all 3 new tests pass
      individually and in the full run (not among the 41 failures). Full
      suite: 203 passed, 41 failed, 107 skipped — the 41 failures confirmed
      pre-existing (branch only adds 3 new untracked files, nothing
      modified) and unrelated to this issue's scope. Filed as consolidated
      triage issue #1849 rather than investigating/fixing inside this PR.
- [ ] PHPStan baseline hygiene: N/A — no PHP files touched
- [x] Run `senior-architect` review of the diff, address findings — 0
      Critical/High/Medium; all 3 documented judgment calls confirmed sound
      by independent verification against actual page markup. 1 optional
      Low (submit-button selector too coupled to button text) — fixed,
      simplified to `input[type="submit"]` matching `contact-owner-page.spec.js`'s
      `button[type="submit"]` robustness pattern.

## Test Plan

- 3 new test files, each following the `contact-owner-page.spec.js`
  precedent exactly (goto → login-redirect skip guard → URL assertion →
  targeted content/form-field locator assertions).
- Existing suite (`contact-owner-page.spec.js` and everything else under
  `tests/playwright/`) must continue passing unchanged — these are additive,
  independent files.
- Manual acceptance-criteria cross-check against the issue's own checklist
  before closing: `contact/owner.php` (done via #1585, not this PR),
  `privacy.php` (this PR), `user_settings.php` (this PR, targeting the
  `usersc/` customized page), `verify.php` (this PR, partial — unverified
  path only, verified-path fixture explicitly deferred).

## Documentation Plan

- None — no doc references any of these pages' test-coverage status.
