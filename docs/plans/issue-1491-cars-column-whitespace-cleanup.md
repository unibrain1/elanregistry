# Issue #1491: tech-debt: legacy trailing/leading whitespace in cars table string columns

**Branch:** `issue/1491-cars-column-whitespace-cleanup`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

`cars.fname`/`cars.lname` carry untrimmed whitespace, and unlike the other 7
columns in scope, this is not just legacy data: `CarValidator`
(`usersc/classes/Car/CarValidator.php`) has no `case` for `fname`/`lname` in
its sanitization switch, so they fall through to the untouched `default`
branch. Every ownership transfer (`CarAdministrationService::transfer()`)
writes these fields through the validator and persists whatever comes out
unmodified.

- **Root cause:** `fname`/`lname` were likely omitted from
  `CarValidator`'s switch because they read as user-identity fields owned by
  `users`/`Owner`, not car-intrinsic fields — but `cars.fname`/`lname` are a
  writable denormalized copy on this table, and every other denormalized
  owner-identity column (`city`/`state`/`country`) does have a case.
  Omission appears to be an oversight when `OWNER_IDENTITY_FIELDS` was
  defined, not an intentional exclusion.
- **Testing gap:** No existing PHPUnit test asserts
  `CarValidator::validateAndSanitizeFields()` output for `fname`/`lname` —
  confirmed by grep; the `city`/`state`/`country` normalization is
  implicitly covered by the general validator tests, but there's no direct
  parity test enumerating "every `OWNER_IDENTITY_FIELDS` string column gets
  normalized."
- **Preventive measure:** Added checklist item for a PHPUnit test asserting
  `fname`/`lname` are trimmed by the validator, so this can't silently
  regress again.

## Database & Security Considerations

- No schema change. Data-only `UPDATE` statements against existing `cars`
  columns.
- No auth/CSRF-relevant code paths beyond the fix script itself, which follows
  the established `secureP104371age()` + CSRF-token-on-POST pattern used by every
  other fix script (see `03-Decode-All-HTML-Encoded-Fields.php`, still visible
  in git history at commit `d4f17956`, used as the direct template for this
  script's structure).
- Column/table names are hardcoded into an allowlist inside the script (not
  user input), following the same defensive pattern as the analog script, so
  no injection surface even though identifiers can't be parameterized in SQL.
- `cars_hist` (audit-trail mirror table) is intentionally **left untouched**
  — confirmed with user. Matches precedent: script 03 also left `cars_hist`
  unchanged when decoding HTML entities, treating audit history as
  preserved-as-recorded.
- `BackupManager::createManualBackup()` backs up `cars` before any write;
  migration aborts cleanly (no writes) if the backup fails.
- Idempotent: uses `LENGTH(col) != LENGTH(TRIM(col))` in the `WHERE` clause,
  so a second run reports zero rows changed.

## Architecture & Design

**Columns to clean**: `color`, `comments`, `variant`, `series`, `chassis`,
`city`, `state`, `fname`, `lname`. The last two were not in the original
issue text — verified against a production dump snapshot
(`Monitoring/data/2026-08-25/database/db-2026-08-25.sql`, imported into a
throwaway scratch DB and dropped after inspection, not left on disk) by
running the `LENGTH(col) != LENGTH(TRIM(col))` check across every string
column in `cars` (1,592 rows total):

| Column | Affected rows |
| --- | --- |
| `color` | 345 |
| `comments` | 58 |
| `variant` | 2 |
| `series` | 2 |
| `chassis` | 1 |
| `city` | 1 |
| `state` | 1 |
| `fname` | 14 |
| `lname` | 4 |
| `vericode`, `model`, `type`, `engine`, `email`, `country`, `website` | 0 |

`fname`/`lname` are the denormalized owner name columns copied onto `cars`
from `users.fname`/`lname` (same denormalization script 03 re-synced for
`city`/`state`/`country` after its HTML-decode pass); trimming them here
only touches the `cars` copies, not the `users` source-of-truth table —
confirmed acceptable with user, added to scope.

**Live bug found — `fname`/`lname` are not protected against future
whitespace, unlike the other 7 columns.** `CarValidator::validateAndSanitizeFields()`
(`usersc/classes/Car/CarValidator.php:87-257`) has a `case` for every other
denormalized owner-identity column — `city`/`state`/`country` (lines
207-213) route through `InputSanitizer::normalize()`, which calls `trim()`
— but **`fname` and `lname` have no case in that switch**, so they fall
through to the `default` branch (lines 239-244), which passes the value
through unmodified: no trim, no length cap, no sanitization at all. This is
live and reachable today: `CarAdministrationService::transfer()`
(`usersc/classes/Car/CarAdministrationService.php:142-165`) writes
`$targetUser->fname`/`lname` into `$ownerFields`, runs it through
`validateAndSanitizeFields($ownerFields, false)`, and persists the
untrimmed result to both `cars` and `cars_hist` on every ownership
transfer — so new untrimmed rows can be created going forward by this code
path, independent of the one-time data cleanup. Confirmed with user: **fix
the validator gap in this PR**, not just clean the existing data.

Fix: add a `case 'fname': case 'lname':` branch to
`CarValidator::validateAndSanitizeFields()`, following the exact pattern of
the existing `case 'city': case 'state': case 'country':` branch (lines
207-213) — `InputSanitizer::normalize($value, 100)` inside an
`if (!empty($value))` guard, no `$requireAll` throw (these are optional
denormalized fields, never required on car create/update, consistent with
how `city`/`state`/`country` are treated). No other call site needs
changes — `CarAdministrationService::transfer()` already routes all
`$ownerFields` through this method before writing.

**Pattern**: New file `app/admin/scripts/fix/12-Trim-Cars-Column-Whitespace.php`,
following `_TEMPLATE_Fix-Script.php` conventions and directly modeled on
`03-Decode-All-HTML-Encoded-Fields.php` (last seen at commit `d4f17956`,
since removed per the delete-when-applied convention):

- `declare(strict_types=1)`, `securePage($php_self)` gate, custom
  `set_error_handler` logging through `LogCategories::LOG_CATEGORY_FIX_SCRIPT`.
- `$db = DB::getInstance();` and `$backupManager = new BackupManager($db, $abs_us_root . $us_url_root . BACKUP_BASE_DIR, (int) $user->data()->id);`
- `const CARS_TRIM_COLUMNS = ['color', 'comments', 'variant', 'series', 'chassis', 'city', 'state', 'fname', 'lname'];`
  drives both the pre-flight count query and the UPDATE step — single source
  of truth, no duplication between report and action.
- `countColumnAffected($db, $column)`: allowlist-checked helper running
  `SELECT COUNT(*) FROM cars WHERE LENGTH(`{$column}`) != LENGTH(TRIM(`{$column}`))`.
  Checks `$db->error()` after query and throws `RuntimeException` on failure
  (per script 03's established pattern — UserSpice's `DB::query()` swallows
  PDOExceptions internally, so try/catch around `$db->query()` never fires;
  must check `$db->error()` explicitly).
- Pre-flight page (GET, no `start` param): shows the same table-style report
  as script 03 — per-column affected counts plus a total, green
  alert-success if already all-zero, amber alert-warning otherwise. CSRF
  token embedded in the start form.
- Processing page (POST with valid CSRF + `start`):
  1. **STEP 1** — `createManualBackup('Trim cars column whitespace — issue #1491', ['cars'])`.
     Abort (no further steps, no writes) if `BackupException` is thrown; log
     and report the abort clearly.
  2. **STEP 2** — for each column in `CARS_TRIM_COLUMNS`, run
     `UPDATE cars SET {$column} = TRIM({$column}) WHERE LENGTH({$column}) != LENGTH(TRIM({$column}))` (backtick-quoted identifiers in the actual code)
     via a allowlist-checked helper `trimColumn($db, $column): int` returning
     affected-row count (via `$db->count()`, matching the template's
     `$db->count()` convention for UPDATE row counts — not re-querying).
     Check `$db->error()` after each UPDATE; throw `RuntimeException` on
     failure so the outer catch aborts the remaining columns cleanly (partial
     completion is acceptable here since each column's UPDATE is independent
     and idempotent — a re-run picks up any column that didn't finish).
  3. Insert into `fix_script_runs` (`script_name` =
     `'12-Trim-Cars-Column-Whitespace.php'`).
  4. `logger()` call summarizing per-column counts as JSON, matching script
     03's `logger(...)` call shape.
  5. Post-run report: per-column rows-updated table, total, backup filename.
- No `Rename-Legacy...`-style special cases needed — this is a single
  mechanical TRIM, no iterative-decode complexity, no unstable-value
  tracking. No re-sync step back to `users`/`profiles` either: `cars.fname`/
  `lname` are a one-way denormalized copy, and trimming the copy doesn't
  desync it from the source (the source `users.fname`/`lname` values are
  presumably already trimmed by the same `InputSanitizer::normalize()` path
  noted in the issue — out of scope to verify/fix here since the issue is
  specifically about `cars` table data quality).

## Implementation Checklist

- [x] Add `case 'fname': case 'lname':` to
      `CarValidator::validateAndSanitizeFields()`, normalizing via
      `InputSanitizer::normalize($value, 100)` under an `if (!empty($value))`
      guard, matching the existing `city`/`state`/`country` case exactly —
      `usersc/classes/Car/CarValidator.php` (parallel-safe)
- [x] Create `app/admin/scripts/fix/12-Trim-Cars-Column-Whitespace.php` per
      the Architecture & Design section above, using
      `_TEMPLATE_Fix-Script.php` as the file skeleton and
      `03-Decode-All-HTML-Encoded-Fields.php`'s git history version
      (commit `d4f17956`) as the pattern reference for
      pre-flight-count/CSRF/backup/allowlist/`$db->error()` conventions —
      `app/admin/scripts/fix/12-Trim-Cars-Column-Whitespace.php`
- [x] Run PHPStan on both touched files and fix all reported errors —
      `vendor/bin/phpstan analyse app/admin/scripts/fix/12-Trim-Cars-Column-Whitespace.php usersc/classes/Car/CarValidator.php`
- [x] Add/update a PHPUnit unit test asserting
      `CarValidator::validateAndSanitizeFields()` trims `fname`/`lname` —
      covers the escape gap directly, prevents regression (parallel-safe)
- [x] Run `21-Fix-Page-Permissions.php` on test environment to register the
      new script path in UserSpice's permission table (per CLAUDE.md: new
      admin scripts require this) (depends on: file created)
- [x] Manually execute the script on the test database via the Maintenance
      tab; verify pre-flight counts are non-zero and roughly consistent with
      the confirmed dump-based counts (color ~345, comments ~58, variant 2,
      series 2, chassis 1, city 1, state 1, fname 14, lname 4 — exact numbers
      may drift slightly if test DB was refreshed from a different snapshot)
      (depends on: permissions registered) — actual run: color 347, comments
      59, variant 2, series 2, chassis 1, city 1, state 1, fname 15, lname 4
      (432 total rows updated); minor drift from dump-based estimate is
      expected (different snapshot). Also fixed a bug found at this step:
      `BackupManager` constructor requires `ElanRegistry\DatabaseInterface`,
      not the raw `DB` singleton — script originally passed
      `DB::getInstance()` (copied from the historical script 03 snapshot,
      which predates the `DatabaseInterface` split); corrected to `dbi()`,
      matching every other current `BackupManager` call site
      (`tab-maintenance.php`, `tab-health.php`,
      `21-Fix-Page-Permissions.php`). Re-verified PHPStan clean after fix.
- [x] Verify post-run counts are 0 for all nine columns via the
      `LENGTH()`-based query, and that a second script run reports 0 rows
      changed (idempotency check) (depends on: test run) — pre-flight page
      reloaded post-run, confirmed "All columns are already clean" (total 0).
- [x] PHPStan baseline hygiene: confirm the new file has no
      `phpstan-baseline.neon` entries needed (new file, should be clean by
      construction) — verified: no touched file (`CarValidator.php`,
      `12-Trim-Cars-Column-Whitespace.php`, `Issue1491RegressionTest.php`)
      appears in `phpstan-baseline.neon`.
- [x] Run `/security-review` (script performs raw SQL UPDATEs and is
      admin-auth-gated), address Critical/High findings — clean, 0 findings
      (Critical/High/Medium/Low all 0). Verified: SQL identifier allowlist
      airtight (column names only ever come from `CARS_TRIM_COLUMNS` const),
      CSRF/auth coverage solid, CarValidator fix doesn't weaken validation
      or introduce XSS, backup-before-write ordering safe (write path gated
      on non-null `$backupPath`).
- [x] Run `senior-architect` review of the diff, address findings — 3
      findings, all addressed:
      - **High**: script's `cars_hist` claim was false — the `cars_update`
        trigger fires unconditionally unless `@disable_triggers` is set,
        which the script never did, so every trim would have flooded the
        audit trail. Fixed: `SET @disable_triggers = 1` before the trim
        loop, reset to `NULL` after (and in the catch block, so a mid-loop
        failure doesn't leave it set for the rest of the connection).
        Re-verified on restored pre-trim data: `cars_hist` row count
        unchanged (35,789 before and after) across a real 432-row trim run.
      - **Medium**: script hand-rolled the `fix_script_runs` insert instead
        of using `admin_script_record_completion()` (the helper added in
        #1796 specifically to fix silent completion-logging gaps). Fixed:
        switched to the helper, which also logs
        `LOG_CATEGORY_FIX_SCRIPT_ERROR` centrally on failure.
      - **Low**: no test asserted the 100-char truncation behavior inherited
        for fname/lname. Fixed: added
        `testValidateAndSanitizeFieldsTruncatesLongNameField` with a data
        provider covering both fields.
      Re-ran PHPStan (clean), full `composer test:quick` (1671 tests, 4624
      assertions, no regressions), and a full manual re-verification cycle
      on test (restored pre-trim `cars` from the first run's backup,
      re-ran the fixed script, confirmed correct trim counts and
      `cars_hist` stability).

## Test Plan

**CarValidator fname/lname fix**: covered by a new PHPUnit unit test (see
Implementation Checklist) asserting `validateAndSanitizeFields()` trims
whitespace from `fname`/`lname` the same way it does for `city`/`state`/
`country`. Run via `composer test:quick`.

**Fix script**: no automated test suite covers one-time fix scripts
(consistent with all prior scripts in `app/admin/scripts/fix/` — none have
PHPUnit coverage). Verification is manual, on the test environment, per the
Implementation Checklist above:

1. Pre-flight report shows expected non-zero counts matching the issue's
   table.
2. Running the script updates exactly those row counts and inserts a
   `fix_script_runs` row.
3. Post-run `LENGTH(col) != LENGTH(TRIM(col))` query returns 0 for all nine
   columns.
4. Re-running the script is a no-op (0 rows updated on all columns),
   confirming idempotency.
5. Spot-check 2-3 previously-affected rows in the UI (e.g. a car with
   `color = "Red               "` before) to confirm the trimmed value
   displays correctly and no other field was altered.

Production run is a separate manual step after test verification succeeds,
matching the issue's "Run on test first ... then run on prod" instruction —
not part of this PR's checklist, since `/execute-plan` doesn't deploy to
prod.
