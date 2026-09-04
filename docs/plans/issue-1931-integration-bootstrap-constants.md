# Issue #1931: test: integration bootstrap does not define ELAN_IMAGE_DIR — CarTransferTest fails standalone

**Branch:** `issue/1931-integration-bootstrap-constants`
**Milestone:** `milestone/v2.30.1`
**Status:** Implemented — pending commit/PR

## Findings

- `usersc/includes/config.php:73` is the single source of `ELAN_IMAGE_DIR` (and
  `ELAN_IMAGE_*`, `TRANSFER_REQUEST_EXPIRY_DAYS`, `EMAIL_SUBJECT_PREFIX`,
  `BACKUP_*`, `ASSET_VERSION`). It is reached only via
  `users/init.php:135` → `users/includes/loader.php:215` →
  `usersc/includes/loader.php:16`.
- `tests/bootstrap-integration.php:140-145` wraps `require_once users/init.php`
  in a deliberately non-fatal `try/catch`. If `init.php` throws before its last
  line, `config.php` never loads and every `Car` instantiation fails with the
  undefined-constant error — unless an earlier test file happened to load
  `config.php` itself (order dependency).
- Not reproducible on this machine today: `init.php` currently completes, so
  `--filter CarTransferTest` passes standalone (13 tests). The failure mode is
  documented as real in `tests/integration/BackupRestorabilityTest.php:10-13`
  ("member function query on null" during early framework startup), and three
  tests already carry per-file workarounds:
  - `tests/integration/database/CarVerificationColumnsHistTest.php:49-58` —
    guard-defines `ELAN_IMAGE_DIR` in `setUp()`
  - `tests/integration/BackupRestorabilityTest.php:10-26` — guarded
    `require_once config.php` at file scope
  - `tests/integration/database/BackupCriticalTablesTest.php:10-24` — same
- `tests/bootstrap-unit.php:35-43` mirrors the values literally (no
  framework available there). The integration bootstrap has the framework, so
  it can load the real file instead — no second copy to drift.

## UserSpice Integration

No framework function applies. The fix reuses the project's own
`usersc/includes/config.php`; `users/` is untouched.

## Database & Security Considerations

None — test bootstrap only. No schema, auth, or input handling changes.

## Architecture & Design

**Approach (chosen):** after the `init.php` try/catch and
`restore_error_handler()` in `tests/bootstrap-integration.php`, load
`config.php` if `init.php` did not get there:

```php
// If users/init.php threw before reaching usersc/includes/loader.php, the
// application constants (ELAN_IMAGE_DIR, BACKUP_*, ...) that Car and
// BackupManager read are still undefined. Load the real config.php rather than
// mirroring its values (as tests/bootstrap-unit.php must, having no framework),
// so there is nothing to drift. Guarded because a successful init.php already
// loaded it and define() cannot run twice.
if (!defined('ELAN_IMAGE_DIR')) {
    // init.php sets these before it can fail; default them anyway so
    // config.php's ASSET_VERSION path build cannot emit undefined-variable
    // warnings.
    $abs_us_root ??= $projectRoot;
    $us_url_root ??= '/';
    require_once $projectRoot . '/usersc/includes/config.php';
}
```

Scope note: PHPUnit 12 includes the bootstrap from a function scope, and
`init.php`/`config.php` are required from that same scope, so `$abs_us_root`
and `$us_url_root` are visible to `config.php` without `global`. (The per-test
files needed `global` because they run in a different scope from the
bootstrap; that complication goes away with them.)

**Alternative rejected:** copy `bootstrap-unit.php`'s literal `define()` block.
Two copies of the values would drift; `config.php` is the source of truth the
user named.

**Fold-in (approved):** delete the three per-test workarounds above. Keeping
them would silently mask a regression of this fix.

## Implementation Checklist

- [x] Add guarded `config.php` load after `restore_error_handler()` —
      `tests/bootstrap-integration.php` (parallel-safe)
- [x] Remove the `ELAN_IMAGE_DIR` guard-define and its comment from `setUp()` —
      `tests/integration/database/CarVerificationColumnsHistTest.php`
      (parallel-safe)
- [x] Remove the file-scope `BACKUP_BASE_DIR` guard block and its comment —
      `tests/integration/BackupRestorabilityTest.php` (parallel-safe)
- [x] Remove the file-scope `BACKUP_BASE_DIR` guard block and its comment —
      `tests/integration/database/BackupCriticalTablesTest.php` (parallel-safe)
- [x] Add `tests/integration/BootstrapConstantsTest.php` asserting the
      `config.php` constants `Car` and `BackupManager` read are defined with
      their `config.php` values (`ELAN_IMAGE_DIR`, `ELAN_IMAGE_MAX`,
      `ELAN_IMAGE_THUMBNAIL_SIZES`, `TRANSFER_REQUEST_EXPIRY_DAYS`,
      `EMAIL_SUBJECT_PREFIX`, `BACKUP_BASE_DIR`) — this is the test that fails
      if the bootstrap regresses, independent of which other test file runs
      first (parallel-safe)
- [x] Standalone runs pass: `--filter CarTransferTest`,
      `--filter CarVerificationColumnsHistTest`,
      `--filter BackupRestorabilityTest`, `--filter BackupCriticalTablesTest`,
      `--filter BootstrapConstantsTest` (depends on: all edits above)
- [x] `composer test:full` passes (depends on: standalone runs)
- [x] PHPStan on the five touched files: `vendor/bin/phpstan analyse <files>`;
      no baseline entries exist for them (verified: none) (depends on: all edits)
- [x] `/security-review` — not required (no forms/SQL/auth touched)
- [x] Run `senior-architect` review of the diff, address findings
      (depends on: test:full)

## Test Plan

- Regression: `tests/integration/BootstrapConstantsTest.php` (new, above).
- Acceptance criteria from the issue, run verbatim:
  - `vendor/bin/phpunit -c phpunit-integration.xml --filter CarTransferTest`
  - `composer test:full`
- Order-independence check: each of the four modified/related test classes run
  standalone with `--filter`.
- Simulated failure path (manual, not committed): temporarily `throw` at the
  top of `users/init.php`, run `--filter CarTransferTest`, confirm
  `ELAN_IMAGE_DIR` is still defined and the test's failure (if any) is the DB
  one, not the constant one; revert.

## Documentation Plan

No doc describes the bootstrap's constant loading. `ENVIRONMENT.md:249-277`
covers `.env.test.local` guards only — unchanged. Inline comments in the
bootstrap carry the rationale.
