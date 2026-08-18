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

### Bug Fixes

- **PDF reference documents no longer flagged as Soft 404** ([#1538](https://github.com/elan-registry/registry/issues/1538)): The 9 reference PDF viewer pages (workshop manual, parts lists, paint codes, engine-number breakdown, and others) now have unique, descriptive page titles, meta descriptions, and on-page text describing each document, plus a direct download link to the PDF itself. Previously each page was just an iframe with almost no extractable text, so Google's soft-404 heuristic flagged all of them — 16 pages and growing per Search Console. The 9 PDFs are now also listed directly in `sitemap.xml`.

### Improvements

- **Public API privacy** ([#1501](https://github.com/elan-registry/registry/issues/1501)): Owner coordinates and internal user IDs are no longer exposed in public car-history and DataTables API responses.

## Admin-Facing Changes

### Bug Fixes

- **Backup data-loss detection** ([#1502](https://github.com/elan-registry/registry/issues/1502)): The backup routine no longer reports "Healthy" when a table dump silently loses its data — failures now surface for real.
- **Admin User Manager XSS** ([#1499](https://github.com/elan-registry/registry/issues/1499)): Closed a stored-XSS vector in the admin User Manager's email column.
- **Admin car-reassignment "No Owner" hardcoded ID** ([#1562](https://github.com/elan-registry/registry/issues/1562)): The "No Owner" option in the admin car-reassignment tool no longer hardcodes user id `83`. The id was only correct by accident on production; on any freshly-provisioned environment it could silently transfer a car to an unrelated account or throw an error. The "No Owner" checkbox now sends a flag, and the server resolves the actual `noowner` account id itself.

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
- [#1602](https://github.com/elan-registry/registry/issues/1602) — test: LocationServiceUserAgentTest bypassed the real fallback branch — extracted it to `LocationService::resolveVersion()`
- [#1542](https://github.com/elan-registry/registry/issues/1542) — test: RobotsTxtPolicyTest verifies repo robots.txt, not the as-served file with Cloudflare's injection
- [#1604](https://github.com/elan-registry/registry/issues/1604) — test: VerificationWorkflowTest tests a wholly fictional class — zero coupling to production code
- [#1609](https://github.com/elan-registry/registry/issues/1609) — test: VerificationSecurityTest has tautological tests asserting local data against itself
- [#1603](https://github.com/elan-registry/registry/issues/1603) — test: LocationServiceResponseTest only exercises ApiResponse literals, never the location-search/reverse endpoints
- [#1572](https://github.com/elan-registry/registry/issues/1572) — test: extract shared authenticated-session helper for integration tests, migrating 10 files off the duplicated login idiom
- [#1573](https://github.com/elan-registry/registry/issues/1573) — test: deleted CarActionsTest.php (10 tautological tests), relocating its 3 real gaps to existing suites and a new save.php routing test
- [#1597](https://github.com/elan-registry/registry/issues/1597) — test: AdminContactSanitizationTest reimplements CR/LF-strip regex from process-admin-contact.php — added a source-inspection guard against the real production regex
- [#1598](https://github.com/elan-registry/registry/issues/1598) — test: AssetVersionTest reimplements ASSET_VERSION resolution logic from config.php — extracted to AssetVersionResolver::resolve() so tests exercise the real function
- [#1599](https://github.com/elan-registry/registry/issues/1599) — test: TypeHelpersTest exercised bootstrap duplicates, not the real functions — extracted `dbInt()` to `TypeHelpers::toInt()` and added integration coverage for `currentUserId()`
- [#1600](https://github.com/elan-registry/registry/issues/1600) — test: EmailHeaderSanitizationTest reimplemented send-owner-email.php's sanitization — added a source-inspection guard, including #1322's first-name-only privacy contract
- [#1601](https://github.com/elan-registry/registry/issues/1601) — test: UploadPathGuardTest reimplements the path-traversal guard from save.php — extracted to UploadPathGuard::isWithinTarget() so tests exercise the real function
- [#1628](https://github.com/elan-registry/registry/issues/1628) — test: LogCategoriesUsageTest didn't cover most app/admin/ files with logger()/withLogging() calls — extended ADMIN_ENDPOINT_FILES from 3 to 22 files
- [#1633](https://github.com/elan-registry/registry/issues/1633) — test: AdminContactSanitizationTest #661 target_email tests were tautological, asserting PHP's own filter_var() — added a source-inspection guard against the real process-admin-contact.php validation
- [#1537](https://github.com/elan-registry/registry/issues/1537) — fix: test.elanregistry.org was crawlable and indexed — resolved via Cloudflare's "Managed robots.txt" toggle; the edge was injecting an `Allow: /` that tied against the intended `Disallow: /` (no code change)
- [#1559](https://github.com/elan-registry/registry/issues/1559) — test: relocated tests/regression/ into tests/unit/regression/ tagged `#[Group('regression')]`, collapsing the standalone tier to one source of truth for the check pre-commit and CI enforce
- [#1536](https://github.com/elan-registry/registry/issues/1536) — test: fixed 3 false-failure assertions in not-logged-in.spec.js caused by Cloudflare Turnstile's injected iframe and a stray leading space in a title regex (follow-up: [#1648](https://github.com/elan-registry/registry/issues/1648))
- [#1538](https://github.com/elan-registry/registry/issues/1538) — fix: pdf-viewer.php pages flagged as Soft 404 by Google — added per-document titles, descriptions, on-page text and download links, listed the 9 PDFs in `sitemap.xml`, and added a drift test
- [#1283](https://github.com/elan-registry/registry/issues/1283) — refactor: extracted a `TransferIntegrationTestCase` base class, consolidating 4 duplicated fixture helpers and routing inserts through `CarTransferRepository::create()` instead of raw SQL
- [#1585](https://github.com/elan-registry/registry/issues/1585) — chore: extracted `ElanRegistry\DatabaseInterface` and a `DbAdapter` around UserSpice's `\DB`, so production classes take a real test double instead of a global mock — deleting the mock `DB`/`QueryResult` from `tests/bootstrap-unit.php`. The stricter typing surfaced latent production bugs (fatals in `CarRepository::findById()` and `app/owner/contact/owner.php`, a corrupt-backup path in `BackupManager`)
- [#1652](https://github.com/elan-registry/registry/issues/1652) — fix: converted `CarShowcaseService`'s two static methods — the pair #1585's DI migration missed — to constructor-injected instance methods, with unit coverage of their DB-error fallbacks
- [#1657](https://github.com/elan-registry/registry/issues/1657) — test: covered `CarDataTablesService::getDataTablesData()`'s two `is_object()` guards and the filtered-count path they sit in, which no existing test reached — 3 unit tests, mutation-verified
- [#1679](https://github.com/elan-registry/registry/issues/1679) — tech-debt: converted manual or silently-skipped install steps into Phinx migrations — Turnstile hooks, settings defaults (root cause of the FilePond `maxFiles: 0` bug), the `noowner` account, baseline permissions. **Also fixed a silent GDPR-deletion failure:** reassigning cars to `noowner` threw on its deliberately-invalid address, rolling back the deletion and leaving the owner's PII on their former cars. Follow-ups: [#1686](https://github.com/elan-registry/registry/issues/1686), [#1547](https://github.com/elan-registry/registry/issues/1547)
- [#1562](https://github.com/elan-registry/registry/issues/1562) — fix: admin car-reassignment hardcoded the `noowner` account id as `83`, correct only by accident on production. The server now resolves the id itself via `User::find('noowner')` and never trusts a client-supplied id for this path
- [#1702](https://github.com/elan-registry/registry/issues/1702) — fix: `database/4-sample-data.sql` aborted mid-load on a dropped `ModifiedBy` column, silently leaving a half-populated database. Fixing it surfaced that the sample car was never valid registry data (wrong `model` format and `type`, a chassis failing `ChassisValidator`, three nonexistent images). Corrected, added a 26R for the statistics map filter test, dropped a manual `cars_hist` INSERT the trigger already writes, and regenerated the password hash to match the documented `password123`
- [#1706](https://github.com/elan-registry/registry/issues/1706) — docs: ADR-006 referenced the deleted `database/3-configuration.sql` in three places. Since ADR-015 supersedes it and nothing reads the `elan_*_cdn` columns now, those references are recorded as history rather than repointed as if still live
- [#1707](https://github.com/elan-registry/registry/issues/1707) — tech-debt: removed a dead branch in `scripts/check-docs.php` that read the deleted `database/1-schema.sql` behind a `file_exists()` guard that could never pass. The migration scan above it already covers the same ground; check output is byte-identical
- [#1709](https://github.com/elan-registry/registry/issues/1709) — chore: deleted `app/admin/scripts/fix/_ARCHIVE/` (17 executed scripts, three of which would now fatal if run) and rewrote the archive process as a delete process across the docs, so no milestone recreates it. Also fixed the documented git recovery command, which recovered a stale pre-archive version of every script, and dropped six now-inert exclusions from tooling and tests
- [#1708](https://github.com/elan-registry/registry/issues/1708) — tech-debt: cleared the five `phpstan-baseline.neon` suppressions on three files touched this milestone. Two were real (an over-narrow `@param`, a never-read property); the three in `user_manager_columns.php` were false positives from single-file analysis, so they became an inline `@phpstan-ignore` documenting the include contract
- [#1711](https://github.com/elan-registry/registry/issues/1711) — tech-debt: `/review-pr` reviewed tests but never ran them, so it could report "Local review clean" over a failing branch. It now runs `composer test:full`, `check:docs` and PHPStan before the agents, and reports real counts. Verified that an unreachable DB exits **0** having run zero tests, so the gate counts PHPUnit summary lines rather than trusting `$?`; unexpected skips and warnings are Blocking alongside errors. This is the only automated step anywhere that runs `tests/integration/`
