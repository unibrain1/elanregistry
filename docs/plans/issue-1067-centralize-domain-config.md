# Issue #1067: Centralize scattered Owner/Car domain config (image, email, expiry settings)

**Branch:** `issue/1067-centralize-domain-config`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

Registry configuration for image handling, admin/feedback email routing, and
transfer expiry lives in a DB-backed, web-editable `settings` table row,
edited via `app/admin/includes/tab-settings.php`. This has two concrete
harms named in the issue: (1) no single authoritative source — some values
are set by a migration, some only by column defaults, some by admin edits
that may never have happened, so nobody can say with confidence what's
actually live; (2) the web-writable settings endpoint
(`app/api/admin/process-settings.php`) is a live path to reroute admin/
feedback email addresses via a compromised admin session.

This closed issue #1722 as superseded — #1722's whole complaint (`tab-
settings.php`'s hardcoded `elan_image_max` fallback of `10` disagrees with
the DB migration's `6`) gets resolved as a side effect once this issue
deletes `tab-settings.php` and hardcodes one authoritative value.

Separately, the `[ELANREGISTRY]` email-subject prefix and the "Lotus Elan
Registry" site name are duplicated as bare string literals across 6+ files
with two inconsistent formats, and `EmailTemplate.php` ignores
`$settings->site_name` entirely.

Research this session (3 Explore agents + PM review) surfaced real scope the
original issue text didn't name:

- `elan_image_max` has two disagreeing "current" values (6 vs 10) — resolved
  below by capturing live values before hardcoding anything.
- `app/api/admin/process-settings.php` (the AJAX write endpoint backing the
  form, with its own duplicate `FIELD_TYPES` allowlist) must be deleted
  alongside `tab-settings.php` — not named in the issue.
- `tests/unit/admin/ProcessAdminSettingsTest.php` (a "mandatory review gate"
  test on that endpoint's field list) and
  `tests/unit/system/LogCategoriesUsageTest.php::testFeedbackEmailSettingIsAutoCreated`
  both need deletion.
- Five of the seven settings (`elan_image_upload_max_size`,
  `elan_image_display_max_size`, `elan_image_thumbnail_sizes`,
  `elan_admin_emails`, `elan_feedback_email`) are never touched by any
  migration — only column-level defaults — so their true live value on
  prod/test is unverified and could differ if an admin ever edited them.

## Confirmed live values (via direct DB query against local/test/prod, 2026-08-28)

| Setting | Local | Test | Prod | Constant value used |
| --- | --- | --- | --- | --- |
| `elan_image_dir` | `userimages/` | same | same | `'userimages/'` |
| `elan_image_max` | `6` | same | same | `6` (resolves #1722's 6-vs-10 discrepancy — prod migration value confirmed correct, `tab-settings.php`'s `10` fallback was stale) |
| `elan_image_upload_max_size` | `3` | `3.00` | `2.00` | **`3.00`** — genuinely differed between prod and test/local. Confirmed with user: standardize on `3` everywhere. **This is a deliberate prod behavior change (raises prod's upload cap from 2MB to 3MB), not a pure refactor — call it out explicitly in the PR description and release notes.** |
| `elan_image_display_max_size` | `2048` | same | same | `2048` |
| `elan_image_thumbnail_sizes` | `100,300,768,1024,2048` | same | same | `'100,300,768,1024,2048'` |
| `elan_admin_emails` | `registrar@elanregistry.org` | same | same | via `.env` (see below) |
| `elan_feedback_email` | `registrar@elanregistry.org` | same | same | via `.env` (see below) |

The prod/test query was run directly via SSH (`ssh a2hosting`) against each
environment's live DB, using connection details read from that
environment's own `.env` — read-only `SELECT`, no writes. This is separate
from and in addition to `scripts/generate-config.php`'s own `.env`-append
role (see below) — the direct query was a one-time planning-time check to
resolve the `elan_image_upload_max_size` discrepancy before hardcoding
`config.php`; it does not need to be repeated for those 5 values, since they
were manually verified and are being hardcoded directly in Phase B.

## Design (settled across several rounds, then finalized after live values were confirmed — this is final)

The key distinction driving the final design: **most of these settings are
non-secret app config with one correct value across all environments**
(confirmed above — `elan_image_dir`/`elan_image_max`/`elan_image_display_max_size`/
`elan_image_thumbnail_sizes` were already identical across local/test/prod;
`elan_image_upload_max_size` differed and was deliberately standardized),
while **`elan_admin_emails` and `elan_feedback_email` are the only
genuinely environment-specific/private values** (real email addresses,
arguably closer to a secret than to app config — though in this case all
three environments happened to hold the same value).

This splits the migration cleanly:

- **The 5 image/display settings** → checked-in constants in
  `usersc/includes/config.php`, alongside the existing `BACKUP_RETENTION_*`/
  `ASSET_VERSION` constants already there, using the confirmed values from
  the table above. No new file, no gitignore/htaccess changes, no PHPStan
  bootstrap concerns (PHPStan's `phpstan-bootstrap.php` directly
  `require_once`s this file — it must stay a normal checked-in file that
  resolves on any fresh checkout/CI runner with no DB access). **Written
  directly as a Phase B code edit, not generated by any script** — the
  values are already known and confirmed, so there is nothing left to
  automate here.
- **`ADMIN_EMAILS` / `FEEDBACK_EMAIL`** → appended to `.env` (not a new
  file) as `ADMIN_EMAILS=...` / `FEEDBACK_EMAIL=...` lines, read via
  `$_ENV` exactly like `DB_HOST` etc. already are. `.env` is already
  gitignored and `.htaccess`-blocked (`<Files "*.env*">`) — no new
  protection mechanism needed. **This is the one thing the generator
  script still does** — see below.

**The generator script's scope narrowed to just the two `.env` emails**,
since the 5 image settings turned out to need a one-time manual DB check
(to catch the `elan_image_upload_max_size` divergence) rather than a
scripted/automated capture — that check has already happened (see table
above) and doesn't need repeating:

- `scripts/generate-config.php` — standalone CLI, connects to the DB
  directly via the local `.env` it's run against (same pattern as
  `scripts/log-deployment.php`: `Dotenv::createImmutable()->safeLoad()`,
  manual `DB_HOST` port-split, direct PDO — no full UserSpice bootstrap,
  since `app/admin/scripts/` requires an authenticated HTTP session via
  `securePage()` and this must run from the CLI).
- Reads only `elan_admin_emails`/`elan_feedback_email` from the `settings`
  row.
- **Appends** `ADMIN_EMAILS=` / `FEEDBACK_EMAIL=` lines directly to `.env`
  in place (preserving every existing key), then re-applies `chmod 600` to
  `.env` after writing (matching documented convention in
  `docs/development/ENVIRONMENT.md`, defensive regardless of the file's
  current permission state).
- **Already run and verified locally** (this session's stop point — see
  below) — confirmed working: correctly appended
  `ADMIN_EMAILS=registrar@elanregistry.org` /
  `FEEDBACK_EMAIL=registrar@elanregistry.org` to the local `.env` and
  re-chmod'd it to 600.
- Still needs to run **once against test's own `.env`, then once against
  prod's own `.env`** (each already holds that environment's own DB
  credentials; this session has SSH access via the `a2hosting` host alias
  and read the `.env` files directly to run the one-time confirmation
  query above, but the actual write-and-append run against test/prod is a
  deploy-time step, done after this branch merges — not from this session
  mid-development).
- **Deleted from the repo** in a small follow-up commit once confirmed
  working on all three environments — a one-time migration tool, not kept
  as ongoing infrastructure.

**Stop point for `/execute-plan` (already reached and cleared):**
implementation paused after the script was written, run, and verified
locally, and after the 5 image-setting values were independently confirmed
via direct DB queries against test/prod (over SSH, read-only) during this
same planning conversation. That verification is complete — proceed to
Phase B using the confirmed values in the table above.

## Database & Security Considerations

- No schema changes in this PR. Column drops are explicitly deferred to a
  follow-up issue (matches the `elan_backup_age`/#706 precedent, and the
  issue's own Out of Scope section) — dropping columns before confirming the
  captured values are correct would remove the ability to double-check.
- Security improvement (explicitly named in the issue): deleting
  `process-settings.php` removes a web-writable path to reroute admin/
  feedback email addresses via a compromised admin session. After deletion,
  manually confirm the old AJAX endpoint 404s — closes the loop on the
  stated threat model.
- `ADMIN_EMAILS`/`FEEDBACK_EMAIL` moving into `.env` puts them under the
  exact same protection `.env` already has (gitignore, `.htaccess` deny,
  `chmod 600`, GitGuardian CI scanning per `ENVIRONMENT.md`) — no new
  protection mechanism to build or verify.
- The generator script only ever `SELECT`s from the DB (read-only) —
  no risk to prod/test data. Its `.env` append is the only write it
  performs, and it's additive (never overwrites/removes existing lines).
- No CSRF/auth impact — this is a config-source refactor, not an
  auth-relevant change to any remaining page.

## Architecture & Design

### Part 0 — Generator script (written, run locally, verified — DONE)

New file: `scripts/generate-config.php`. Follows `scripts/log-deployment.php`'s
shape for env-loading and DB connection. Final scope, after narrowing per
the discussion above: reads only `elan_admin_emails`/`elan_feedback_email`
and appends them to `.env` — does NOT touch `usersc/includes/config.php` at
all (the 5 image settings are hardcoded directly in Phase B, using the
confirmed values in the table above).

```php
require_once __DIR__ . '/../vendor/autoload.php';
// Dotenv::createImmutable(...)->safeLoad(), DB_HOST port-split
// (identical pattern to log-deployment.php), then:
$row = $pdo->query('SELECT elan_admin_emails, elan_feedback_email FROM settings WHERE id = 1')
    ->fetch(PDO::FETCH_ASSOC);

$adminEmails = (string)$row['elan_admin_emails'];
$feedbackEmail = (string)$row['elan_feedback_email'];

// .env is KEY=value, not PHP — quote defensively if the value contains
// whitespace or '#' (dotenv would otherwise treat # as a comment start).
$formatEnvValue = static fn(string $v) =>
    ($v === '' || str_contains($v, ' ') || str_contains($v, '#'))
        ? '"' . str_replace('"', '\\"', $v) . '"'
        : $v;

$envPath = dirname(__DIR__) . '/.env';
$envAppend = "\n# Added by scripts/generate-config.php (#1067)\n"
    . 'ADMIN_EMAILS=' . $formatEnvValue($adminEmails) . "\n"
    . 'FEEDBACK_EMAIL=' . $formatEnvValue($feedbackEmail) . "\n";
file_put_contents($envPath, $envAppend, FILE_APPEND | LOCK_EX);
chmod($envPath, 0600);
```

**Already run and verified locally** — output confirmed correct append to
local `.env` (`ADMIN_EMAILS=registrar@elanregistry.org`,
`FEEDBACK_EMAIL=registrar@elanregistry.org`, `chmod 600` re-applied).

### Part 1 — Migrate Settings tab configuration

Add to `usersc/includes/config.php`, following its existing banner-comment +
PHPDoc + `define()` style exactly (matching the `BACKUP_RETENTION_*` section),
using the confirmed values from the table above:

```php
// ============================================================================
// Media & Image Configuration
// ============================================================================

define('ELAN_IMAGE_DIR', 'userimages/');
define('ELAN_IMAGE_MAX', 6);                        // resolves the 6-vs-10 discrepancy (#1722) — confirmed live on prod/test/local
define('ELAN_IMAGE_UPLOAD_MAX_SIZE', 3.00);         // MB — confirmed via direct DB query: differed prod (2) vs test/local (3); standardized on 3 (deliberate prod behavior change, see PR description)
define('ELAN_IMAGE_DISPLAY_MAX_SIZE', 2048);        // px
define('ELAN_IMAGE_THUMBNAIL_SIZES', '100,300,768,1024,2048');

// ============================================================================
// Transfer & Email Configuration
// ============================================================================

define('TRANSFER_REQUEST_EXPIRY_DAYS', 30);  // was a bare literal in transfer-request.php:126, no prior settings column
define('EMAIL_SUBJECT_PREFIX', '[ELANREGISTRY]');
```

**Call-site updates** (all confirmed via this session's Explore research,
exact current line numbers — re-verify at implementation time since these
shift):

- `usersc/classes/Car/Car.php:84,189` — `$settings->elan_image_dir` → `ELAN_IMAGE_DIR`
- `app/owner/cars/edit.php:29-33` — delete the `??=` guard block entirely
  (lines 29-31; constants can't be null), `$maximages = $settings->elan_image_max`
  → `ELAN_IMAGE_MAX`, lines 394-395/657-658 same substitution
- `app/api/cars/save.php:39-47` — delete the `!isset()` guard block entirely
  (same reasoning), lines 53-54/679-680 substitute constants
- `app/owner/cars/details.php:479`, `app/owner/cars/index.php:120`,
  `app/owner/reports/statistics.php:359` — `$settings->elan_image_dir` → `ELAN_IMAGE_DIR`
- `app/owner/cars/includes/elan-config-island.php:6-7` — `elan_image_thumbnail_sizes` → `ELAN_IMAGE_THUMBNAIL_SIZES`
- `app/admin/verify/send_email.php:41-42` — `elan_image_dir` → `ELAN_IMAGE_DIR`
- `usersc/includes/custom_functions.php:140-164` — `getAdminEmails()`/
  `getFeedbackEmail()` bodies become
  `return $_ENV['ADMIN_EMAILS'] ?? 'registrar@elanregistry.org';` /
  `return $_ENV['FEEDBACK_EMAIL'] ?? 'registrar@elanregistry.org';`
  (keep the functions — 6+ call sites depend on them, only their internals
  change; keep the existing fallback literal as a defensive default in case
  `.env` doesn't have the key yet on some environment)
- `app/api/cars/transfer-request.php:126` — `strtotime('+30 days')` →
  `strtotime('+' . TRANSFER_REQUEST_EXPIRY_DAYS . ' days')`

**Deletions:**

- `app/admin/includes/tab-settings.php` (entire file, incl.
  `processSettingsAutoCreation()`)
- `app/api/admin/process-settings.php` (entire file — the AJAX write
  endpoint; not named in the issue, found via Explore)
- `app/admin/maintenance.php` — remove `'settings' => 'Configuration'` from
  `$validTabs` and the corresponding `<li>` nav entry (confirmed current
  locations: `$validTabs` array and the nav `<li>` block — re-verify exact
  lines at implementation time, issue's cited line numbers didn't match
  current file state)

**`app/admin/scripts/maintenance/24-Regenerate-Optimized-Thumbnails.php`:**

- Line 33's stale `'100,300,600,1024,2048'` fallback → `ELAN_IMAGE_THUMBNAIL_SIZES`
- Lines 338-351 (the "update the setting" DB-write block) — delete entirely;
  there's no DB column left to write to once thumbnail sizes is a constant
- Line 402 — `$settings->elan_image_dir` → `ELAN_IMAGE_DIR`

### Part 2 — Consolidate email subject prefix and site name

**Replace all 8 occurrences of the bare `'[ELANREGISTRY]'` string** with
`EMAIL_SUBJECT_PREFIX` (exact current locations confirmed via Explore):

- `app/api/contact/send-owner-email.php:33`
- `app/api/contact/send-feedback.php:63`
- `app/admin/includes/process-admin-contact.php:114`
- `usersc/classes/Transfer/TransferEmailService.php:116,181,251,319`
- `app/views/email/_feedback.php:30`

**`app/admin/verify/send_email.php:57`** — `"Lotus Elan Registry - ..."`
format (no bracket) is inconsistent with the other 5 files; update to use
`EMAIL_SUBJECT_PREFIX` matching the bracket format, since `$settings` is
already in scope there and the fix is trivial.

**`usersc/classes/EmailTemplate.php`** — 3 hardcoded `"Lotus Elan Registry"`
occurrences (lines 224, 376, 385). `$settings` is not currently available in
this class (confirmed via Explore: no constructor param, no `global` pull,
all ~12 call sites construct with zero args). Add `global $settings;` inside
`getBaseTemplate()` (matching the established codebase convention used by
`index.php`, `usersc/join.php`, `usersc/login.php`,
`usersc/includes/head_tags.php`, `usersc/classes/RegistrationRecoveryNotifier.php`
— none of which use constructor DI for `$settings`), reading
`$settings->site_name ?? 'Lotus Elan Registry'` at each of the 3 sites. The
fallback keeps `tests/unit/classes/EmailTemplateTest.php:23`'s
zero-arg-construction working when no global `$settings` is set in test
context.

## Implementation Checklist

**Phase A — script only, then STOP:**

- [x] Write `scripts/generate-config.php` (reads `elan_admin_emails`/
      `elan_feedback_email` via PDO, appends `ADMIN_EMAILS`/`FEEDBACK_EMAIL`
      to `.env`, re-applies `chmod 600` to `.env`) — parallel-safe. `php -l`
      and PHPStan both clean.
- [x] **Run locally, verify `.env` append.** Confirmed: correctly appended
      `ADMIN_EMAILS=registrar@elanregistry.org` /
      `FEEDBACK_EMAIL=registrar@elanregistry.org`, `chmod 600` re-applied.
- [x] **Confirm the 5 image-setting values are actually environment-uniform
      before hardcoding them.** Ran a direct read-only DB query (via
      `ssh a2hosting`, using each environment's own `.env` credentials)
      against local, test, and prod. Found `elan_image_upload_max_size`
      genuinely differs (prod=2, test/local=3) — resolved with user:
      standardize on 3 everywhere (deliberate prod behavior change). All
      other 4 values matched across all three environments. See the
      Confirmed live values table above.

**Phase B:**

- [x] Add Media & Image Configuration + Transfer & Email Configuration
      sections (with confirmed values) to `usersc/includes/config.php` —
      parallel-safe. `php -l` and PHPStan both clean.
- [x] Update `usersc/classes/Car/Car.php` call sites — `php -l`/PHPStan clean;
      also removed a now-dead local `$settings = getSettings();` in `create()`.
- [x] Update `app/owner/cars/edit.php` (delete `??=` guard, substitute
      constants) — `php -l`/PHPStan clean.
- [x] Update `app/api/cars/save.php` (delete `!isset()` guard, substitute
      constants) — `php -l`/PHPStan clean; also simplified a now-always-true
      `isset()/!empty()` conditional and removed a now-unused `global $settings;`.
- [x] Update `app/owner/cars/details.php`, `app/owner/cars/index.php`,
      `app/owner/reports/statistics.php`,
      `app/owner/cars/includes/elan-config-island.php`,
      `app/admin/verify/send_email.php` (image-dir/thumbnail-sizes
      substitutions) — `php -l`/PHPStan clean on all 5.
- [x] Update `usersc/includes/custom_functions.php`
      (`getAdminEmails()`/`getFeedbackEmail()` bodies read `$_ENV`) —
      `php -l`/PHPStan clean.
- [x] Update `app/api/cars/transfer-request.php` (`TRANSFER_REQUEST_EXPIRY_DAYS`) —
      `php -l`/PHPStan clean.
- [x] Update `app/admin/scripts/maintenance/24-Regenerate-Optimized-Thumbnails.php`
      (constant substitution + delete dead DB-write block) — `php -l`/PHPStan
      clean; also cleaned up dangling `$newSizes`/`$settingsUpdated` report-section
      references and stale "updates 600→768" doc claims in the script's own
      docblock/UI text.
- [x] Delete `app/admin/includes/tab-settings.php` — also deleted
      `app/admin/assets/js/tab-settings.js`/`.min.js` (found mid-execution,
      not named in the plan — the JS exclusively wired up the deleted
      tab's form to the deleted AJAX endpoint; confirmed zero remaining
      references repo-wide before deleting).
- [x] Delete `app/api/admin/process-settings.php`
- [x] Remove `'settings' => 'Configuration'` tab entry + nav `<li>` from
      `app/admin/maintenance.php` — `php -l`/PHPStan clean. Also updated
      the file's top docblock (removed the "TAB 3: Configuration" line,
      added a note pointing to #1067).
- [x] Replace all 8 `'[ELANREGISTRY]'` occurrences with `EMAIL_SUBJECT_PREFIX`
      across the 5 named files — parallel-safe. `php -l`/PHPStan clean;
      grep confirms zero remaining literal occurrences.
- [x] Update `app/admin/verify/send_email.php:57` to use
      `EMAIL_SUBJECT_PREFIX` in bracket format — `php -l`/PHPStan clean.
- [x] Update `EmailTemplate.php` to read `$settings->site_name` via
      `global $settings;` with fallback, at all 3 hardcoded sites —
      `php -l`/PHPStan clean. Used a single local `$siteName` variable
      (htmlspecialchars-escaped, matching the method's existing escaping
      convention) rather than 3 inline reads.
- [x] Add `ADMIN_EMAILS`/`FEEDBACK_EMAIL` to `.env.example` as commented-out
      placeholder entries (documents the new required keys for anyone
      setting up a fresh environment, matching the Turnstile-key pattern
      already in that file) — parallel-safe
- [x] Delete `tests/unit/admin/ProcessAdminSettingsTest.php` — deletion
      rationale (it was a deliberate "mandatory review gate" tripwire) noted
      here for the PR description.
- [x] Remove `testFeedbackEmailSettingIsAutoCreated` from
      `tests/unit/system/LogCategoriesUsageTest.php`. Also found and fixed
      mid-execution (not named in the plan): that same file's
      `CAR_ENDPOINT_FILES`/`ADMIN_ENDPOINT_FILES` data-provider constants
      each still listed `process-settings.php`/`tab-settings.php` — stale
      entries pointing at now-deleted files that would have made their
      data-provider test cases fail trying to read missing files. Removed
      both entries.
- [x] Update `tests/bootstrap-unit.php` — added guarded `define()` block for
      the 5 image constants + `TRANSFER_REQUEST_EXPIRY_DAYS`/
      `EMAIL_SUBJECT_PREFIX` (mirroring the `BACKUP_RETENTION_*` precedent,
      values match the real ones exactly since some tests assert against
      them verbatim); trimmed `getSettings()` mock's now-unused
      `elan_image_dir` property (confirmed via grep nothing else in the
      unit tier reads it). Left `getFeedbackEmail()`/`getAdminEmails()`
      mocks unchanged — they were always standalone stand-ins (never
      delegated to the real `$settings`-reading function, since
      `custom_functions.php` can't load in the unit tier at all), so
      switching production to `$_ENV` doesn't affect them.
- [x] Update `tests/integration/CarImageLifecycleTest.php` — updated to
      read `ELAN_IMAGE_THUMBNAIL_SIZES` (was `$settings->elan_image_thumbnail_sizes`
      via `getSettings()`), preserving the test's own stated rationale
      (assert against the same source production reads, so a config change
      can't silently drift out of sync with the test).
- [x] Checked `tests/integration/database/UpdateSettingsBaselineDefaultsMigrationTest.php`
      — no conflict, no edit needed. It tests the DB migration's own output
      (`elan_image_dir`/`elan_image_max` still get written to the `settings`
      table by that migration — #1067 only stops the *app* from reading
      those columns, the migration itself is unchanged).
- [N/A — post-merge, out of this session's scope, confirmed with user]
      **Manual step (user, not this session):** after this branch is merged
      and deployed, run `scripts/generate-config.php` once against test's
      `.env` and once against prod's `.env` to append `ADMIN_EMAILS`/
      `FEEDBACK_EMAIL` there (the config.php constants are already
      committed and identical across environments, so only the `.env`
      append half of the script matters at this stage). Once confirmed
      working on test and prod, delete `scripts/generate-config.php` from
      the repo in a small follow-up commit — it is a one-time tool, not
      kept as ongoing infrastructure. **Restate in the PR description.**
- [N/A — post-merge, out of this session's scope, confirmed with user]
      **Manual verification (after deploy):** confirm
      `app/api/admin/process-settings.php`'s route 404s — closes the
      security threat-model loop named in the issue. **Restate in the PR
      description.**
- [x] Run `composer test:quick`, verify pass — OK (1703 tests, 4709 assertions).
- [x] Run `composer test:full`, verify pass — OK (1703 unit / 494 integration,
      4709 / 1994 assertions).
- [x] PHPStan baseline hygiene: confirmed 2 touched files carried pre-existing
      debt (`app/admin/verify/send_email.php` — `uniqid()` int/string
      mismatch; `app/views/email/_feedback.php` — undefined-variable
      warnings from the email-template `extract()` pattern shared by
      sibling files). Neither was on lines this plan touched. Asked user:
      fix now vs. carry over — user said fix now. Fixed both (`(string)
      rand()` cast; added `@var` PHPDoc block documenting the caller-
      injected `extract()` variables, matching the file's own "Variables
      available" docblock convention). Also had to strip 2 stale baseline
      entries referencing the now-deleted `process-settings.php`/
      `ProcessAdminSettingsTest.php` before `composer phpstan:baseline`
      would even run. Regenerated baseline (189 errors, down from before);
      re-confirmed zero touched files remain in it; full-project PHPStan
      and `composer test:full` both re-verified clean after the fix.
- [x] Run `/security-review`. Two independent passes (cross-validated,
      identical conclusions): **0 Critical, 0 High, 1 Medium, 0 Low.**
      - **[MEDIUM, fixed]** `scripts/generate-config.php`'s `.env`-value
        quoting was conditional (only quoted on whitespace/`#`) — a DB value
        in `elan_admin_emails`/`elan_feedback_email` containing a literal
        newline followed by `KEY=value`-shaped text would be written
        unquoted and parsed by `vlucas/phpdotenv` as a **separate,
        overriding** env var on next load. Empirically confirmed exploitable
        by the reviewer. Fixed: quote unconditionally, escape
        `\`/`"`/`\r`/`\n`.
      - process-settings.php deletion: confirmed no remaining reachable
        path. 3 stale references to the deleted files found and fixed (not
        named in the plan): `scripts/build.js` (would have failed the JS
        build trying to bundle a deleted source file), `tests/playwright/
        ajax-endpoints.spec.js` (an E2E test POSTing to the deleted
        endpoint — removed the whole now-inapplicable `describe` block),
        `usersc/plugins/ai_prompts/custom_prompts/elanregistry_directories.md.php`
        (AI-context doc example naming the deleted file — updated to the
        one file that remains in that directory).
      - `EmailTemplate.php` site_name interpolation: confirmed
        `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`-escaped, no XSS/HTML-
        injection risk into the email body.
      - `getAdminEmails()`/`getFeedbackEmail()` via `$_ENV`: confirmed risk
        *improved* (no web-writable path to these values remains at all).
      - General SQLi/CSRF/auth-bypass sweep: clean.
      - Re-verified after fixes: `php -l`/PHPStan clean on all 4 touched
        files, full-project PHPStan clean, `composer test:full` clean
        (1703/494), `composer check:docs` clean, `npm run lint` clean.
- [x] Run `senior-architect` review of the diff, address findings. Full
      6-part review across all 34 files + the plan doc. **No
      Critical/Important findings.** 2 Minor, both applied even though
      explicitly optional:
      - `scripts/generate-config.php` re-run isn't idempotent — duplicates
        `.env` lines rather than erroring/updating (phpdotenv first-wins
        makes this silently inert, not harmful, but confusing to find
        later). Added a docblock note warning the operator.
      - `getAdminEmails()`/`getFeedbackEmail()`'s `??` fallback didn't
        cover an empty-string (vs. unset/null) `.env` value — not
        reachable given today's confirmed live values, but closed
        defensively: `($_ENV['X'] ?? '') ?: 'fallback'`.
      Everything else confirmed sound: the 2MB→3MB behavior change is
      documented in 3 places (not silently smuggled in); guard-block
      deletions in `edit.php`/`save.php` verified genuinely dead, no lost
      null-safety; `EmailTemplate.php`'s `global $settings` confirmed
      consistent with existing codebase convention, not new tech debt;
      test coverage changes confirmed coherent, none silently mask a
      regression; zero remaining half-migrated call sites found anywhere.
      Re-verified after the 2 fixes: `php -l`/PHPStan clean, full-project
      PHPStan clean, `composer test:full` clean (1703/494).

## Test Plan

- Update, don't add net-new: this is a config-source refactor, not new
  behavior. Existing tests exercising image upload/display/thumbnail
  behavior, transfer-request expiry, and email sending should continue to
  pass once their settings-object expectations are swapped for constant/
  `$_ENV` expectations.
- `tests/unit/admin/ProcessAdminSettingsTest.php` deletion is itself a test
  change requiring justification (see checklist) — its "mandatory review
  gate" framing means this needs to be explicit in the PR body, not silent.
- `tests/bootstrap-unit.php`'s mock updates are the main test-infrastructure
  change — needs care to keep the `BACKUP_RETENTION_*`-precedent guarded
  `define()` pattern so parallel test runs / re-`require`s don't
  redefine-error.
- No test needed for `scripts/generate-config.php` itself beyond the manual
  local run — it's a one-time deletable tool, not part of the app's ongoing
  request path; a unit test for it would be dead weight the moment the
  script is deleted post-rollout. Phase A's stop-and-verify step is the
  actual verification.
- `composer test:quick` and `composer test:full` both run as part of Phase B
  (existing project convention, not new).

## Documentation Plan

- `docs/development/adr/ADR-004-*` — line 411 references `tab-settings.php`'s
  migration history; add a note that it was deleted entirely in #1067 (not a
  contradiction to fix, just a historical record — ADR docs don't get
  rewritten, but check if a "superseded" note is the repo's convention here).
- `docs/development/QUICK_REFERENCE.md` — the "Custom Functions Available on
  All Pages" table (lines 87-98) lists `getAdminEmails()`/`getFeedbackEmail()`;
  no signature change, but could note they now read from `.env` rather than
  DB settings, if that level of detail is already present for other
  functions in that table (check convention before adding).
- `docs/development/ENVIRONMENT.md` — add `ADMIN_EMAILS`/`FEEDBACK_EMAIL` to
  the documented `.env` var list, following the existing Turnstile-section
  pattern (Usage: pointer to `usersc/includes/custom_functions.php`, each
  var as a bullet). Note `scripts/generate-config.php`'s one-time role for
  populating them and its expected deletion after rollout.
- `docs/development/DEPLOYMENT.md` — "Deployment Verification Checklist"
  (line ~424, "Contact forms send to correct email addresses") — no wording
  change needed, but worth a manual verification pass per the checklist
  item above. Add a one-time deployment note about running
  `scripts/generate-config.php` on test/prod as part of this release.
- Wiki: check `Wiki/` (separate repo) for any page documenting the admin
  Settings tab UI — if one exists, it needs a `/publish-wiki` follow-up
  noting the tab's removal. Flag in PR description if found; do not edit the
  wiki from this branch.
