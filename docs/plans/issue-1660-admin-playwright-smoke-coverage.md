# Issue #1660: Playwright smoke coverage for owner-mgmt and maintenance.php

**Branch:** `issue/1660-admin-playwright-smoke-coverage`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

Issue #1585 (DatabaseInterface migration) changed DB wiring on two admin pages
without any Playwright coverage to catch a wiring regression turning into a
500: `getOwnerQualityReports(dbi())`/`getDuplicateEmailDetails(dbi())` in
`tab-owner_mgmt.php`, and `new BackupManager(dbi(), ...)` in what was then
`tab-health.php`. #1225 (this milestone, just merged) deleted `tab-health.php`
and merged its content into `maintenance.php` as a single page — the original
`?tab=health` target no longer exists. The issue body was updated 2026-08-28
to retarget the second smoke test at `maintenance.php` directly (its
`BackupManager` constructor call and script-enumeration wiring are exactly
the same risk, just no longer behind a tab).

Research (this session) confirmed: `getDuplicateEmailDetails()` in
`tab-owner_mgmt.php` is the one **unguarded** DB call in owner-mgmt (no
try/catch) — but it only executes inside a foreach over actual duplicate-email
rows, so exercising that exact path deterministically would require seeding
test data, which is out of scope for a Complexity-S coverage issue. A basic
"page loads, key DB-backed content renders, no fatal" smoke test is what the
issue's own acceptance criteria and proposed solution call for, matching the
existing `admin-unverified-account-cleanup.spec.js` pattern.

## UserSpice Integration

None needed — pure test addition, no application code changes.

## Database & Security Considerations

- No schema, endpoint, or access-control changes.
- Read-only smoke tests against existing admin pages; no new forms, no CSRF
  bypass (existing CSRF tokens are asserted present, not exercised).

## Architecture & Design

Two new Playwright spec files, following the `admin-unverified-account-cleanup.spec.js`
structural convention (full describe block, `beforeEach` does `ensureLoggedIn`
and `goto`, per-concern `test()` blocks) and the relative-path + baseURL style
used by the more recently touched specs (`admin-page-titles.spec.js`,
`admin-modal-confirmation.spec.js`) rather than the older fully-hardcoded-URL
style.

### `tests/playwright/admin-owner-mgmt.spec.js` (new)

Targets `app/admin/index.php?tab=owner-mgmt`. Assertions:

- Page loads (`networkidle`), "Manage Owners" `h2` heading visible.
- Owner search card present: `#ownerSearchInput`, `#ownerSearchBtn`,
  `#ownerClearBtn`, `#ownerSearchResults` container attached.
- Hidden profile panel `#ownerProfilePanel` attached (visibility not asserted
  — it's conditionally shown by JS, not on load).
- Data Health summary card renders a numeric quality-score percentage — this
  is the direct smoke check on `getOwnerQualityReports(dbi())`: assert the
  score card's `h3`-style value matches a `/\d+(\.\d+)?%/` pattern rather than
  a specific number (real DB data, not deterministic).
- "Car Owners Missing Info" and "Duplicate Email Addresses" report cards are
  attached with a numeric count each — same rationale, pattern not exact
  value.
- No PHP fatal: assert response status is 200 (Playwright fails the whole
  `goto` on non-2xx by default with `waitUntil: 'networkidle'` only if the
  nav itself fails, so explicitly capture the response and assert
  `response.status() === 200`) and body text does not contain "Fatal error".

### `tests/playwright/admin-maintenance-smoke.spec.js` (new)

Targets `app/admin/maintenance.php`. Chosen as a **separate** file from
`admin-page-titles.spec.js`/`admin-modal-confirmation.spec.js` rather than
appending to either — this is page/content-level smoke coverage of the
`BackupManager`/script-enumeration wiring, a distinct concern from those
files' existing title-only and modal-DOM-only checks; keeping it separate
avoids growing an existing file past its current focus. Assertions:

- Page loads (`networkidle`), response status 200, body doesn't contain
  "Fatal error".
- `h1` "Registry Maintenance" visible.
- CSRF hidden input `input[name="csrf"]` present (same truthy check pattern
  as `admin-modal-confirmation.spec.js`, not the strict 64-char hex check
  from account-cleanup — no established constant length assertion needed
  here since this is presence-only smoke coverage).
- All three card headings visible: "Backups", "One-time Migrations",
  "Maintenance Tasks" (the third has no anchor `id` per the #1225 rewrite —
  use `page.getByRole('heading', { name: 'Maintenance Tasks' })`, not an
  `#id` locator).
- `#backups-card` and `#migrations-card` anchors attached (confirms the
  `BackupManager` constructor and script-enumeration calls didn't throw
  before reaching markup — if either threw, these partials would never
  render).
- Do NOT assert specific backup stat numbers (automated/manual/rollback file
  counts, MB sizes) — `getEnhancedBackupStatistics()` is try/catch-guarded
  with a fallback, and real DB data isn't deterministic; asserting presence
  of the Backups card body structure is enough to catch a wiring regression
  without being data-fragile.
- Do NOT re-test modal open/dismiss behavior or exact title text — already
  covered by `admin-modal-confirmation.spec.js` and `admin-page-titles.spec.js`
  respectively.

## Implementation Checklist

- [x] Create `tests/playwright/admin-owner-mgmt.spec.js` — `tests/playwright/admin-owner-mgmt.spec.js` (parallel-safe)
- [x] Create `tests/playwright/admin-maintenance-smoke.spec.js` — `tests/playwright/admin-maintenance-smoke.spec.js` (parallel-safe)
- [x] Run `npm run playwright:test` (or targeted: `npx playwright test
      admin-owner-mgmt admin-maintenance-smoke`), verify both new specs
      pass against local MAMP — both fully pass, 10/10. Fixed 2 real
      selector-strictness bugs found by actually running against the
      page: `getByRole('heading', {name: 'Backups'})` matched 3 headings
      (needed `level: 2`), and `.card`-with-`hasText` locators for the
      owner-mgmt report cards matched multiple nested/ancestor elements
      (rescoped to `h5.card-title` + `xpath=following-sibling::h3[1]`).
- [x] Run full `npm run playwright:test` to confirm no regression in
      other specs — 190 passed, 44 failed (all pre-existing: missing
      webkit/Mobile Chrome browser binaries in this local env, or one
      pre-existing test bug — see below), 100 skipped. Confirmed via
      re-run scoped to `--project=chromium`: same 190 passed.
  - **Found and fixed in scope**: `admin-modal-confirmation.spec.js`'s
    "CSRF token is present for modal-triggered forms" test used an
    unscoped `input[name="csrf"]` locator that matched 4 forms on
    `admin/index.php` (assignCar, deleteCar, contact-owner, etc.) — a
    pre-existing Playwright strict-mode violation, unrelated to #1660
    but a one-line fix (`.first()`, all 4 share the same per-session
    token value) — fixed here per user direction rather than filed
    separately.
- [x] PHPStan baseline hygiene: N/A — no PHP files touched
- [x] Run `senior-architect` review of the diff, address findings — no
      Blocking findings. 2 Recommendations, both addressed:
      1. `getDuplicateEmailDetails(dbi())` (one of the two #1585 wiring
         risks the issue names) is only exercised when local seed data has
         an actual duplicate-email pair — nothing guaranteed that. Rather
         than touch seed data (outside this issue's file scope), documented
         the gap explicitly in `admin-owner-mgmt.spec.js`'s header comment,
         noting `getOwnerQualityReports(dbi())` — the other named risk — IS
         reliably exercised on every page load.
      2. `admin-maintenance-smoke.spec.js` re-navigated in every single
         test instead of once in `beforeEach`, diverging from the sibling
         spec and established `admin-unverified-account-cleanup.spec.js`
         convention. Consolidated navigation into `beforeEach`; the first
         test keeps its own `page.goto()` since it needs the response
         object for the status-code assertion, `beforeEach`'s doesn't
         expose that.
      Re-verified all 3 touched/new spec files after fixes: 18/18 pass
      (4 pre-existing skips, unrelated).

## Test Plan

This issue *is* the test plan — no separate senior-test-engineer consult
needed (Small tier, docs-only-adjacent test addition, no application code
changes to strategize around). Coverage added is exactly the two acceptance
criteria from the issue body: `?tab=owner-mgmt` smoke test, `maintenance.php`
smoke test. No PHPUnit changes.
