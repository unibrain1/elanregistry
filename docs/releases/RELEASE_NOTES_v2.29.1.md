# Elan Registry v2.29.1 Release Notes

**Release Date:** [DATE]
**Type:** Patch Release — Honest Tests: Make the Harness Tell the Truth

## Required Actions After Deployment

Do the following, in order, **separately for each environment** (test, then prod):

1. [ ] **One-time, before the first `composer migrate` after this release** ([#1553](https://github.com/elan-registry/registry/issues/1553)): manually stamp the new `AddElanregistryBaseline` migration into `phinxlog`. Running the migration for real would try to `CREATE TABLE car_models` (and 12 other already-existing tables) and fail — the migration itself checks whether `cars` already exists and refuses to run rather than touching anything, so a missed stamp fails the deploy loudly, it doesn't corrupt the schema. Still, do the stamp first every time:
   1. [ ] **Before** `git push test <branch>`, or **before** `git push prod main` — open phpMyAdmin against that environment's database (test server DB, or prod DB — not your local dev DB, which doesn't need this at all).
   2. [ ] Confirm you're on the right database, and that it isn't already stamped:
      ```sql
      SELECT DATABASE();
      SELECT * FROM phinxlog WHERE version = 20260709000000;
      ```
      If the second query already returns a row, stop — already stamped, don't insert again.
   3. [ ] Run the stamp:
      ```sql
      INSERT INTO phinxlog (version, migration_name, start_time, end_time, breakpoint)
      VALUES (20260709000000, 'AddElanregistryBaseline', NOW(), NOW(), 0);
      ```
   4. [ ] Verify: re-run the `SELECT * FROM phinxlog WHERE version = 20260709000000` query — should now return exactly 1 row.
   5. [ ] Now push to that environment: `git push test <branch>` or `git push prod main`. The post-receive hook runs `composer install` and `composer migrate` automatically — `composer migrate` will skip the now-stamped `20260709000000` and apply only whatever else is genuinely pending.
   6. [ ] After the push, confirm via the deploy log or `composer migrate:status` on that server that `20260709000000` shows as already-applied and everything else applied cleanly with nothing pending or errored.

   See `docs/development/DEPLOYMENT.md` → "One-Time: Stamping the ElanRegistry Baseline Migration" for the same procedure in context.

2. [ ] **After the push completes** ([#1495](https://github.com/elan-registry/registry/issues/1495)): confirm the UserSpice framework update took effect on that environment.
   1. [ ] Log in as admin on that environment.
   2. [ ] Go to Admin > Check for Updates.
   3. [ ] Confirm the reported UserSpice version is 6.1.4 or newer.

## User-Facing Changes

### Improvements

- **Public API privacy** ([#1501](https://github.com/elan-registry/registry/issues/1501)): Owner coordinates and internal user IDs are no longer exposed in public car-history and DataTables API responses.

## Admin-Facing Changes

### Bug Fixes

- **Backup data-loss detection** ([#1502](https://github.com/elan-registry/registry/issues/1502)): The backup routine no longer reports "Healthy" when a table dump silently loses its data — failures now surface for real.
- **Admin User Manager XSS** ([#1499](https://github.com/elan-registry/registry/issues/1499)): Closed a stored-XSS vector in the admin User Manager's email column.

### Improvements

- **UserSpice framework update** ([#1495](https://github.com/elan-registry/registry/issues/1495)): Updated to UserSpice >6.1.4.

### Behavior Changes

- **Backup dump failure handling** ([#1502](https://github.com/elan-registry/registry/issues/1502)): A single table's backup failure now aborts the entire backup with no file written (previously wrote a file with an error comment while reporting success). Custom scripts calling `BackupManager::createSchemaBackup()` or `createManualBackup()` must now handle the thrown `BackupException`.

## Issues Resolved

- [#1422](https://github.com/elan-registry/registry/issues/1422) — test: CarTransferRepository DB-error paths and findPendingWithCarById() have no unit tests
- [#1423](https://github.com/elan-registry/registry/issues/1423) — test: LogCategoriesUsageTest not extended for 3 admin files migrated in v2.28.0; CarTransferTest uses base Exception
- [#1440](https://github.com/elan-registry/registry/issues/1440) — test: retire mock Car class — CarCoreTest/CarCrudTest test scaffolding, not the real class
- [#1441](https://github.com/elan-registry/registry/issues/1441) — test: replace always-succeeds mock DB — unit tests can't exercise DB-error paths
- [#1444](https://github.com/elan-registry/registry/issues/1444) — test: retire upload/security helper reimplementations in unit bootstrap — tests validate mocks, not production
- [#1445](https://github.com/elan-registry/registry/issues/1445) — test: UserDeletionCleanupTest exercises a mock cleanup hook, not the real after_user_deletion path
- [#1446](https://github.com/elan-registry/registry/issues/1446) — test: CarValidator unit tests validate against mock CarModel, not real reference data
- [#1453](https://github.com/elan-registry/registry/issues/1453) — fix: scope PHPStan global ignoreErrors so new project code isn't exempt from typing checks
- [#1454](https://github.com/elan-registry/registry/issues/1454) — test: add integration coverage for image lifecycle and backup restorability
- [#1467](https://github.com/elan-registry/registry/issues/1467) — test: mock DB query result triggers PHP warning in CarDataTablesServiceTest
- [#1495](https://github.com/elan-registry/registry/issues/1495) — chore: Update to UserSpice >6.1.4
- [#1499](https://github.com/elan-registry/registry/issues/1499) — security: escape $user->email in admin User Manager (stored XSS)
- [#1500](https://github.com/elan-registry/registry/issues/1500) — chore: fix composer check:php silently skipping PHPStan, quiet checker false positives, fix SecurityHeadersTest CRLF bug
- [#1501](https://github.com/elan-registry/registry/issues/1501) — security: remove lat/lon/user_id from public car-history and DataTables payloads
- [#1502](https://github.com/elan-registry/registry/issues/1502) — fix: BackupManager silent data-loss on backup dump + fake-healthy maintenance badge
- [#1503](https://github.com/elan-registry/registry/issues/1503) — test: make the integration test harness honest
- [#1553](https://github.com/elan-registry/registry/issues/1553) — chore: derive ElanRegistry-only baseline migration (diff vs. stock UserSpice) and implement composer migrate + seed:run provisioning
- [#1554](https://github.com/elan-registry/registry/issues/1554) — test: retire mock Token/Input/MockUser classes in tests/bootstrap-unit.php
- [#1555](https://github.com/elan-registry/registry/issues/1555) — chore: add tests/ to phpstan.neon coverage
- [#1558](https://github.com/elan-registry/registry/issues/1558) — docs: write TESTING_STRATEGY.md documenting testing tier architecture and UserSpice DB conventions
- [#1560](https://github.com/elan-registry/registry/issues/1560) — chore: fix pre-commit's known-broken/broken group naming mismatch; delete stale test:api/test:workflow composer scripts
- [#1550](https://github.com/elan-registry/registry/issues/1550) — test: remove dead noowner skip and fix UserDeletionReassignmentTest not exercising the real reassignment path
- [#1566](https://github.com/elan-registry/registry/issues/1566) — test: retire ad-hoc User double in RegistrationRecoveryNotifierTest.php that pollutes PHPStan analysis project-wide
- [#1556](https://github.com/elan-registry/registry/issues/1556) — test: audit remaining unit test files for undetected mock/fake usage
- [#1551](https://github.com/elan-registry/registry/issues/1551) — test: cars_hist DELETE-order leak in TransferRequestConstraintTest and CarsYearSmallintMigrationTest
- [#1602](https://github.com/elan-registry/registry/issues/1602) — test: 2 LocationServiceUserAgentTest cases seed the cache directly, bypassing the real fallback branch — extracted the guarded fallback to `LocationService::resolveVersion()` so tests drive real temp files
- [#1542](https://github.com/elan-registry/registry/issues/1542) — test: RobotsTxtPolicyTest verifies repo robots.txt, not the as-served file with Cloudflare's injection
- [#1604](https://github.com/elan-registry/registry/issues/1604) — test: VerificationWorkflowTest tests a wholly fictional class — zero coupling to production code
- [#1609](https://github.com/elan-registry/registry/issues/1609) — test: VerificationSecurityTest has tautological tests asserting local data against itself
- [#1603](https://github.com/elan-registry/registry/issues/1603) — test: LocationServiceResponseTest only exercises ApiResponse literals, never the location-search/reverse endpoints
- [#1572](https://github.com/elan-registry/registry/issues/1572) — test: extract shared authenticated-session helper for integration tests, migrating 10 files off the duplicated login idiom
- [#1573](https://github.com/elan-registry/registry/issues/1573) — test: CarActionsTest.php's 10 tests are tautologies — deleted the file, moved 2 real gaps to CarDatabaseOperationsTest.php/CarImageLifecycleTest.php, and 1 to a new save.php action-routing wiring test
- [#1597](https://github.com/elan-registry/registry/issues/1597) — test: AdminContactSanitizationTest reimplements CR/LF-strip regex from process-admin-contact.php — added a source-inspection guard against the real production regex
- [#1598](https://github.com/elan-registry/registry/issues/1598) — test: AssetVersionTest reimplements ASSET_VERSION resolution logic from config.php — extracted to AssetVersionResolver::resolve() so tests exercise the real function
- [#1599](https://github.com/elan-registry/registry/issues/1599) — test: TypeHelpersTest exercised bootstrap-unit.php's dbInt/currentUserId duplicates, not custom_functions.php — extracted dbInt() to TypeHelpers::toInt() so unit tests exercise the real logic, and added integration coverage for currentUserId() against the real function
- [#1600](https://github.com/elan-registry/registry/issues/1600) — test: EmailHeaderSanitizationTest reimplements header sanitization from send-owner-email.php — added a source-inspection guard against the real production derivation, including the first-name-only (no-lname) #1322 privacy contract
- [#1601](https://github.com/elan-registry/registry/issues/1601) — test: UploadPathGuardTest reimplements the path-traversal guard from save.php — extracted to UploadPathGuard::isWithinTarget() so tests exercise the real function
- [#1628](https://github.com/elan-registry/registry/issues/1628) — test: LogCategoriesUsageTest didn't cover most app/admin/ files with logger()/withLogging() calls — extended ADMIN_ENDPOINT_FILES from 3 to 22 files
- [#1633](https://github.com/elan-registry/registry/issues/1633) — test: AdminContactSanitizationTest #661 target_email tests were tautological, asserting PHP's own filter_var() — added a source-inspection guard against the real process-admin-contact.php validation
- [#1537](https://github.com/elan-registry/registry/issues/1537) — fix: test.elanregistry.org is crawlable and indexed — resolved via Cloudflare "Managed robots.txt" toggle (no code change; the edge was injecting an `Allow: /` block that tied against the intended `Disallow: /` group)
- [#1559](https://github.com/elan-registry/registry/issues/1559) — test: relocate tests/regression/ into tests/unit/regression/ tagged #[Group('regression')] — collapsed the standalone directory/testsuite tier, keeping a single source of truth for the regression-test structural check that pre-commit and CI enforce
- [#1536](https://github.com/elan-registry/registry/issues/1536) — test: fixed 3 false-failure assertions in not-logged-in.spec.js — an unscoped `iframe` locator matched Cloudflare Turnstile's injected widget on prod (strict-mode violation) in 2 tests, and a stray leading space made a title regex unmatchable in a 3rd; the title regex was corrected in place, and the 2 iframe assertions were replaced with Turnstile-safe body-text error-message checks (matching the pattern already used elsewhere in the file) so the success-vs-error discriminator isn't lost. Follow-up tracked in [#1648](https://github.com/elan-registry/registry/issues/1648): no test currently asserts the pdf-viewer.php iframe positively renders the correct document `src`, deferred until the Cloudflare Turnstile interference can be worked around
