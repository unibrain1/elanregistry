# Issue #1225: Redesign maintenance.php landing state

**Branch:** `feature/1225-maintenance-landing-state`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

`app/admin/maintenance.php` currently has two tabs: **Health** (read-only,
default landing tab) and **Maintenance** (where an admin actually acts —
backups, one-time fix scripts, recurring maintenance scripts). Landing on
Health costs every admin visit a wasted click, since the job-to-be-done on
this page is almost always to act, not to read a status screen. A third tab,
Configuration, was already removed in #1067, leaving only these two.

Mid-planning, the user changed scope from "collapse the tab nav to one item"
to a full merge: **no tabs at all**. `app/admin/includes/tab-health.php` and
`app/admin/includes/tab-maintenance.php` are both deleted; their content
becomes one page, `app/admin/maintenance.php`, with live health signals
condensed into header chips and a conditional alert, and the maintenance
content (backups, fix scripts, maintenance scripts) immediately visible
below with no navigation required. A `senior-ux-designer` consult (this
session) produced the concrete layout below.

This also retires two pieces of decorative-but-misleading UI the issue
explicitly calls out: the always-green "System Operational" header badge,
and the "Database Health Status" table where 3 of 4 rows are hardcoded
`Healthy`/`Operational`/`Active` regardless of actual system state.

## UserSpice Integration

No new UserSpice functionality needed — this is a display/structure
refactor of an existing admin page. `securePage()`, `Token::generate()`,
`getAdminSystemStatus()` and the existing `BackupManager`/script-enumeration
helpers are reused unchanged.

## Database & Security Considerations

- No schema changes.
- No new endpoints — `backup-operations.php`'s existing AJAX actions
  (`createManualBackup`, `listBackupFiles`, `performBackupCleanup`) are
  unchanged; their triggering buttons just move to a different static
  location on the page. Confirmed `admin-core.js`'s `.nav-tabs` selectors
  are generic (shared with `index.php`, which keeps its own tabs) and will
  simply find no elements on the merged `maintenance.php` — no JS changes
  needed.
- Access control unchanged: `securePage($php_self)` admin-only gate stays
  exactly as-is at the top of `maintenance.php`.
- `PagePermissionClassifier::ADMIN_ONLY_PAGES` currently lists
  `app/admin/includes/tab-health.php` — must be removed since the file no
  longer exists. `maintenance.php` itself is already in that list and stays
  admin-only, so no permission regression.

## Architecture & Design

### Section order (top to bottom), per UX consult

1. Page header (trimmed — see below)
2. Chip row — only rendered when there's something to flag; renders nothing
   when both signals are clean (no decorative "all clear" chip)
3. Real backup-attention `alert-warning` — condition-driven, same content
   `tab-health.php` has today minus the now-pointless "Go to Maintenance"
   button (everything is already on this page)
4. Backups card (L1, `card-header-er-primary`) — anchor `id="backups-card"`
5. One-time Migrations card (L1) — anchor `id="migrations-card"`
6. Maintenance Tasks card (L1)

### What's deleted outright (not relocated)

- The 3-column Database Health / Backup Storage / Pending Migrations card
  row. Database Health is 100% decorative — cut, not moved. Backup Storage
  and Pending Migrations' *real* content already surfaces via the header
  chip + the Backups/Migrations card bodies immediately below — a middle
  summary layer would be a third duplicate presentation of the same two
  numbers.
- The "Database Health Status" table (4 rows, 3 hardcoded). The one real
  row (Backup System) is already covered by the chip + alert + Backups card.
- The always-green "System Operational" badge in the page header.
- The duplicate `alert-warning` currently inside `tab-maintenance.php`
  (lines 105–116) that points at the dying `?tab=health` — the single
  relocated alert (item 3 above) replaces it; no second copy.

### One computation pass

`tab-health.php` and `tab-maintenance.php` currently each run their own
`try/catch` around `$backupManager->getEnhancedBackupStatistics()` — near-
duplicate logic (pre-existing debt, #801's territory, not fully closed by
that issue). The merge computes this once, directly in `maintenance.php`,
before any markup renders: `$backupManager`, `$fixScripts`,
`$maintenanceScripts`, `$scriptRunStatus`, `$maintenanceRunStatus`,
`$scriptRunStatusError`, `$backupStats`, `$backupStatsFallback`,
`$oldBackupsCount`, `$showCleanupPrompt`, `$backupStatusUnknown`,
`$backupFailureDetected`, `$backupNeedsAttention`, `$pendingMigrations`,
`$pendingFixScripts`, `$completedFixScripts`, plus the `$backupStatusLabel`/
`$backupBadgeLabel`/`$backupStatusDetail` match-expressions and the
`scriptDisplayName()` helper — all currently split across the two files,
consolidated into one place in `maintenance.php`.

### File structure

`app/admin/maintenance.php` grows to hold: the computation block above,
the trimmed header, the chip row, the real alert, then three `include`s for
non-tab partials (to keep the file from growing past ~400 lines, per the
UX consult's file-organization suggestion):

- `app/admin/includes/partials/maintenance-backups.php` — the existing
  Backups L1 card body (3-column automated/manual/rollback, action buttons,
  and backup-list modal), unchanged content, just relocated and renamed —
  drops the duplicate alert-warning block per above.
- `app/admin/includes/partials/maintenance-migrations.php` — the existing
  One-time Migrations L1 card body, unchanged content except the card
  header class fix below.
- `app/admin/includes/partials/maintenance-scripts.php` — the existing
  Maintenance Tasks L1 card body, unchanged content.

These are plain includes (share the parent's variable scope via PHP's
normal `include` semantics, same pattern `js-data-island.php` already uses)
— no `?tab=` semantics, no `$tabFile` resolver. `app/admin/includes/tab-
health.php` and `app/admin/includes/tab-maintenance.php` are deleted
entirely, not renamed — their content is redistributed as above, not moved
1:1 into a new file.

### Header chips (UX-specified markup)

Replace the "System Operational" badge with:

```php
<div class="d-flex gap-2 flex-wrap justify-content-end">
    <?php if ($backupNeedsAttention): ?>
        <a href="#backups-card" class="badge text-bg-warning badge-lg text-decoration-none">
            <i class="fas fa-exclamation-triangle"></i>
            <?= match(true) {
                $backupStatusUnknown => 'Backup Status Unavailable',
                $backupFailureDetected => 'Backup Failure Detected',
                default => 'Backup Cleanup Needed',
            } ?>
        </a>
    <?php endif; ?>
    <?php if ($pendingMigrations > 0): ?>
        <a href="#migrations-card" class="badge text-bg-warning badge-lg text-decoration-none">
            <i class="fas fa-wrench"></i>
            <?= $pendingMigrations ?> Pending Migration<?= $pendingMigrations === 1 ? '' : 's' ?>
        </a>
    <?php endif; ?>
</div>
```

Never a green "all clear" chip — absence of a chip is the all-clear signal
(avoids reintroducing the same decorative-badge problem being removed
elsewhere). Both `er-stat-tile` counters (Total Cars, Total Users) stay
unchanged — orthogonal, already using the correct counter pattern.

### Approved cleanup, in scope for this PR (touch-it-fix-it)

- **One-time Migrations card header**: currently `bg-warning text-dark`,
  which `UI_STANDARDS.md`'s own anti-pattern table forbids on card headers
  (`"inconsistent with hierarchy; warning color is semantic"` → use
  `card-header-er-primary`). Fix to `card-header-er-primary` /
  `card-header-er-primary-text` while rewriting this card anyway.
- **Heading depth**: all three card headings are currently `h5` directly
  under the page's `h1` (skips `h2`–`h4`). Since every card heading is
  being rewritten as part of this merge, promote all three to `h2`.
- **Pre-existing stale Playwright test**: `admin-page-titles.spec.js`'s
  `settings tab renders page-specific title` test still asserts
  `?tab=settings` → `'Registry Maintenance - Configuration'`, a path #1067
  already removed — currently silently exercising a fallback rather than
  failing loudly. Fixed in the same pass as the default-tab title update
  below, since it's the same file/same test block.

### `maintenance.php` header/routing changes

- `$validTabs` array deleted entirely — no more tab whitelist.
- `$activeTab`/`?tab=` query-param logic deleted entirely.
- `$pageTitle` becomes the static string `'Registry Maintenance'` (no more
  per-tab suffix — single-page, so no variation to encode).
- Top-of-file doc comment (`TAB 1: Health` / `TAB 2: Maintenance`) rewritten
  to describe the merged single-page structure.
- Nav-tabs `<ul>` block deleted; `card-header p-0` wrapper removed along
  with it (no header needed on the outer `card` once there's no tab strip —
  the L1 cards inside carry their own headers).
- `$tabFile`/`$tabPath`/`tab-placeholder.php` resolver logic deleted;
  replaced with the three direct `include`s above.

## Implementation Checklist

- [x] Rewrite `app/admin/maintenance.php`: delete `$validTabs`/`$activeTab`/
      `?tab=` logic, static `$pageTitle`, move all computation (backup
      stats, script enumeration/run-status, all derived flags) to the top
      of the file before markup, trim header (remove "System Operational"
      badge, add chip row), delete nav-tabs block, add the real
      backup-attention alert, include the three new partials in order —
      `app/admin/maintenance.php` (depends on: partials existing — see next
      3 items, do this last)
- [x] Create `app/admin/includes/partials/maintenance-backups.php` from
      `tab-maintenance.php`'s Backups card content, dropping its duplicate
      `?tab=health`-pointing alert block — `app/admin/includes/partials/maintenance-backups.php` (parallel-safe)
- [x] Create `app/admin/includes/partials/maintenance-migrations.php` from
      `tab-maintenance.php`'s One-time Migrations card content, fixing the
      card header to `card-header-er-primary` and heading to `h2` —
      `app/admin/includes/partials/maintenance-migrations.php` (parallel-safe)
- [x] Create `app/admin/includes/partials/maintenance-scripts.php` from
      `tab-maintenance.php`'s Maintenance Tasks card content, heading to
      `h2` — `app/admin/includes/partials/maintenance-scripts.php` (parallel-safe)
- [x] Delete `app/admin/includes/tab-health.php` — (depends on: maintenance.php rewrite)
- [x] Delete `app/admin/includes/tab-maintenance.php` — (depends on: maintenance.php rewrite)
- [x] Remove `'app/admin/includes/tab-health.php'` from
      `usersc/classes/admin/PagePermissionClassifier.php`'s
      `ADMIN_ONLY_PAGES` — `usersc/classes/admin/PagePermissionClassifier.php` (parallel-safe)
      (also removed `tab-maintenance.php`, deleted by the same rewrite but
      not separately named in this checklist item — leaving it would have
      referenced a nonexistent file)
- [x] Remove the two `tab-health.php` references in
      `tests/unit/admin/PagePermissionClassifierTest.php` (the
      `adminOnlyProvider` data-set entry and the `$maintenancePages` array
      entry in `testMaintenancePortalPagesAreAdminOnly`) —
      `tests/unit/admin/PagePermissionClassifierTest.php` (parallel-safe)
      (also removed the matching `tab-maintenance.php` entries, same reason
      as above)
- [x] Remove `'app/admin/includes/tab-health.php'` from
      `tests/unit/system/LogCategoriesUsageTest.php`'s
      `ADMIN_ENDPOINT_FILES` — `tests/unit/system/LogCategoriesUsageTest.php` (parallel-safe)
      (also removed `tab-maintenance.php`, same reason as above)
- [x] Update `tests/playwright/admin-page-titles.spec.js`: change the
      default-tab test to assert the static title `'Registry Maintenance'`
      (no more per-tab title), and fix/remove the pre-existing stale
      `?tab=settings` test in the same block —
      `tests/playwright/admin-page-titles.spec.js` (parallel-safe)
- [x] Run PHPStan on all touched/new files, fix any errors — clean
      (`vendor/bin/phpstan analyse`: No errors). The 3 new partials'
      `variable.undefined` cross-include-scope errors (e.g. `$backupStats`,
      `$scriptRunStatus`) follow the same established pattern as the deleted
      `tab-health.php`/`tab-maintenance.php` (which had identical baseline
      entries for `$activeTab`/`$validTabs`) — added via
      `composer phpstan:baseline`, not suppressed ad hoc.
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries (fix or explicitly defer per
      `/execute-plan` Step 6.5) — old `tab-health.php`/`tab-maintenance.php`
      entries dropped automatically on regen (files deleted);
      `maintenance.php` itself carries zero baseline entries; only new
      entries added are for the 3 new partials' legitimate cross-scope vars.
- [x] Run `composer test:full`, verify pass — OK (1700 tests, 4704
      assertions) unit; OK (494 tests, 1994 assertions) integration.
- [x] Manual smoke test (local MAMP): verified via one-off Playwright script
      (login + navigate to maintenance.php) — static title "Registry
      Maintenance", zero `.nav-tabs`/`#managementTabs` elements, both
      `#backups-card`/`#migrations-card` anchors present, all 3 card
      headings render as `h2` ("Backups", "One-time Migrations",
      "Maintenance Tasks"), old "System Operational" badge and "Database
      Health Status" table both absent. Local dev data has no pending
      migrations/backup issues, so the clean-state screenshot showed no
      chips (correct — absence is the all-clear signal). To verify the
      needs-attention path, temporarily added a never-executed placeholder
      file to `app/admin/scripts/fix/` (no DB writes, no script run),
      re-screenshotted — confirmed the gold "1 Pending Migration" chip
      renders correctly in the header and links to the Migrations card —
      then deleted the placeholder immediately; `git status` confirmed
      clean afterward.
      Screenshot review caught a real bug the plan's card-header-class fix
      alone didn't cover: the Migrations card's outer wrapper kept
      `border-warning` (from the original tab-maintenance.php), and
      `.card.border-warning .card-header` in the compiled Bootstrap CSS
      overrides the header background regardless of the header's own
      class — so `card-header-er-primary` rendered gold, not the intended
      dark green. Fixed by changing the wrapper to `border-primary` in
      `maintenance-migrations.php`; confirmed via re-screenshot all three
      card headers now render identically.
- [x] Run `senior-architect` review of the diff, address findings — no
      Blocking findings (4 independent review passes: code-reviewer,
      silent-failure-hunter, pr-test-analyzer, fact-check). 4
      Recommendations, all addressed:
      1. Stale `tab-health.php`/`tab-maintenance.php` references in
         `21-Fix-Page-Permissions.php` (docblock + in-page HTML) and
         `fix-script-core.php` (docblock) — updated to reference the
         current single-page structure/partials.
      2. Stale `?tab=maintenance` query param in
         `admin-modal-confirmation.spec.js` (2 occurrences) and
         `admin-fix-script-close-button.spec.js` (1) — removed (page no
         longer reads `$_GET['tab']` at all; was inert, not a false pass,
         but misleading).
      3. `$scriptRunStatusError` banner relocated from the bottom of
         `maintenance-backups.php` (visually distant from the Maintenance
         Tasks table it also covers) to `maintenance.php` itself, directly
         above all three cards, with wording naming both affected sections
         (One-time Migrations and Maintenance Tasks).
      4. New `usersc/classes/admin/MaintenanceStatusLabels.php` extracts
         the chip/alert 3-way `match(true)` precedence (unknown > failure
         > cleanup-needed) into two static pure methods; covered by
         `tests/unit/admin/MaintenanceStatusLabelsTest.php` (9 test cases:
         4 chip-label branches, 4 alert-heading branches, 1 cross-check
         that both methods agree except on the "neither" default wording).

## Test Plan

- No new PHPUnit coverage needed — this is a pure display/routing
  restructure of an existing admin page; existing `PagePermissionClassifier`
  and `LogCategoriesUsageTest` coverage is updated (not expanded) to match
  the file deletions.
- `admin-page-titles.spec.js` is the one existing Playwright test directly
  asserting page-title behavior for this page — updated per the checklist
  above rather than left to silently pass against a stale assertion.
- Manual smoke test (see checklist) is the primary verification for the
  visual/layout change itself, since chip conditional-rendering and anchor
  scroll behavior are not covered by the existing automated suite and
  writing new Playwright coverage for them is out of scope for this issue
  (tracked separately by #1660, "admin tabs owner-mgmt and health have no
  Playwright smoke coverage" — worth flagging in the PR description that
  #1660's "health" tab target no longer exists post-#1225, may need a note
  or scope adjustment on that issue).
