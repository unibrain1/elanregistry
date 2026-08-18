# Elan Registry v2.29.1 Release Notes

**Release Date:** [DATE]
**Type:** Patch Release — Honest Tests: Make the Harness Tell the Truth

## Required Actions BEFORE Deployment

Do the following, in order, **separately for each environment** (test, then prod).

Steps 1 and 2 must both be completed **before** you push. Pushing runs the
post-receive hook, which runs `composer install` and `composer migrate`
immediately — there is no window to intervene afterwards.

1. [ ] **Take a full database backup via phpMyAdmin, before anything else.**
   This release applies seven migrations, including ones that drop columns
   (`DropUsersLegacyColumns`) and write to `settings`, `pages`,
   `permission_page_matches` and `users`. A backup taken through the host's
   phpMyAdmin is the rollback path if a migration behaves unexpectedly on real
   data — the application's own backup feature is not a substitute here, since
   it runs on the very database you are about to change.
   1. [ ] Open phpMyAdmin against that environment's database via the host
          control panel (test server DB, or prod DB — not your local dev DB).
   2. [ ] Confirm you are on the correct database: `SELECT DATABASE();`
   3. [ ] Export → **Custom** → select **all tables** → Format: SQL → include
          "Add DROP TABLE" and structure **and** data → Go.
   4. [ ] Save the `.sql` file locally, and confirm it is a plausible size —
          not a few KB, which would mean a truncated or structure-only export.
   5. [ ] Note the filename and timestamp here: ______________________

2. [ ] **One-time, before the first `composer migrate` after this release** ([#1553](https://github.com/elan-registry/registry/issues/1553)): manually stamp the new `AddElanregistryBaseline` migration into `phinxlog`. Running the migration for real would try to `CREATE TABLE car_models` (and 12 other already-existing tables) and fail — the migration itself checks whether `cars` already exists and refuses to run rather than touching anything, so a missed stamp fails the deploy loudly, it doesn't corrupt the schema. Still, do the stamp first every time:
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

3. [ ] **After the push completes** ([#1495](https://github.com/elan-registry/registry/issues/1495)): confirm the UserSpice framework update took effect on that environment.
   1. [ ] Log in as admin on that environment.
   2. [ ] Go to Admin > Check for Updates.
   3. [ ] Confirm the reported UserSpice version is 6.1.4 or newer.

4. [ ] **Re-verify settings that the baseline migration overwrites** ([#1679](https://github.com/elan-registry/registry/issues/1679)): `UpdateSettingsBaselineDefaults` sets roughly two dozen `settings` keys unconditionally on every environment, so any value hand-tuned on that environment outside the baseline is reverted by this deploy. The keys most likely to have been adjusted by hand are `elan_image_max`, `registration`, `min_pw`/`max_pw`, `min_un`/`max_un`, `email_login`, `admin_verify` and `err_time`; the authoritative list is the `UPDATE settings SET` block in `database/migrations/20260817033111_update_settings_baseline_defaults.php`.
   1. [ ] Open Admin > Settings and confirm those values are what that environment should have.
   2. [ ] Restore any that were deliberately different, and note them so a future baseline change doesn't revert them again.

Once the deploy is complete, work through the post-deploy manual verification
checklist for this release to confirm the user- and admin-facing changes on
that environment.

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
- **Fresh installs had no page permissions** ([#1671](https://github.com/elan-registry/registry/issues/1671)): A newly provisioned environment started with empty `pages` and `permission_page_matches` tables, because UserSpice only registers a page when an Administrator visits it — impossible before an admin exists. Provisioning now seeds base pages and permissions itself. Existing test and prod environments are already populated and are unaffected.

### Improvements

- **UserSpice framework update** ([#1495](https://github.com/elan-registry/registry/issues/1495)): Updated to UserSpice >6.1.4.
- **Installation guide rewritten** ([#1267](https://github.com/elan-registry/registry/issues/1267)): The wiki Registry Installation guide now documents the install flow that actually ships. Walking it on a clean environment surfaced three fresh-install defects fixed in this release ([#1667](https://github.com/elan-registry/registry/issues/1667), [#1669](https://github.com/elan-registry/registry/issues/1669), [#1671](https://github.com/elan-registry/registry/issues/1671)).

### Behavior Changes

- **Backup dump failure handling** ([#1502](https://github.com/elan-registry/registry/issues/1502)): A single table's backup failure now aborts the entire backup with no file written (previously wrote a file with an error comment while reporting success). Custom scripts calling `BackupManager::createSchemaBackup()` or `createManualBackup()` must now handle the thrown `BackupException`.
- **Backups now cover every table** ([#1714](https://github.com/elan-registry/registry/issues/1714)): Backups discover all base tables at runtime instead of dumping a hardcoded list of six, which is what makes them actually restorable. Two consequences worth knowing: backup files are substantially larger, and their contents now include UserSpice's authentication tables (`us_totp_secrets`, `us_passkeys`, `us_oauth_server_tokens`, `users_session`) alongside owner PII. Those tables are empty today because MFA and OAuth are disabled — but if either is enabled later, backup files become credential-bearing automatically, with no further code change. The `backups/` directory remains blocked from web access by `.htaccess`, and retention is bounded at 7/30 days.
- **No DataTables change ships in this release** ([#1578](https://github.com/elan-registry/registry/pull/1578)): a dependabot PR bumped the `datatables.net-bs5` npm package from 2.3.8 to 3.0.1, but nothing in the application loads that package. Per ADR-015 the site self-hosts a downloader-built bundle at `usersc/js/datatables.min.js` (DataTables 2.3.8, Buttons 3.2.6, FixedHeader 4.0.6, Responsive 3.0.8), and that bundle is unchanged here. **Production DataTables remains 2.3.8**, so no table behavior changes and no extra post-deploy checking is needed. The npm entry is vestigial; `package.json` now records 3.0.1 while the served bundle is 2.3.8, tracked in [#1725](https://github.com/elan-registry/registry/issues/1725), which also revisits ADR-015 for all six vendored frontend libraries.

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
- [#1267](https://github.com/elan-registry/registry/issues/1267) — docs: rewrote the wiki Registry Installation guide against the install flow that actually ships, replacing steps that no longer matched the code. Wiki-only change — no code in this release. Working through it end-to-end on a clean environment is what surfaced [#1667](https://github.com/elan-registry/registry/issues/1667), [#1669](https://github.com/elan-registry/registry/issues/1669) and [#1671](https://github.com/elan-registry/registry/issues/1671) below, each a fresh-install defect invisible on an already-provisioned database
- [#1671](https://github.com/elan-registry/registry/issues/1671) — fix: a freshly provisioned environment had **no page permissions at all** — `pages` and `permission_page_matches` started empty, because UserSpice only auto-registers a page when an Administrator happens to visit it, which cannot occur before any admin exists. Provisioning now registers base pages and their permissions directly, wrapped in transactions so a partial run cannot leave the permission tables half-populated
- [#1669](https://github.com/elan-registry/registry/issues/1669) — tech-debt: dropped four `users` columns (`twoKey`, `twoEnabled`, `twoDate`, `org`) that the baseline migration added but no application code reads — they are absent from a genuine UserSpice 6.1.4 install, so the migration was manufacturing schema drift against upstream rather than reflecting it
- [#1667](https://github.com/elan-registry/registry/issues/1667) — fix: bumped `brace-expansion` to patch a high-severity DoS advisory (unbounded expansion causing OOM). Transitive dev dependency only — not shipped to browsers, no production exposure
- [#1465](https://github.com/elan-registry/registry/issues/1465) — ci: mechanical guardrails encoding the audit's anti-patterns as convention tests and Semgrep rules, so they fail CI rather than relying on documentation that can itself drift. **Partial coverage in this release** — each rule ships alongside the fix that removes its last violation, so the `no-mock-domain-classes` and `no-always-succeeds-DB-double` guards land here with [#1440](https://github.com/elan-registry/registry/issues/1440)/[#1441](https://github.com/elan-registry/registry/issues/1441), while `no-unregistered-users-edits` ([#1460](https://github.com/elan-registry/registry/issues/1460), v2.29.6) and `no-CSRF-in-entities` ([#1519](https://github.com/elan-registry/registry/issues/1519), v2.29.5) arrive in later milestones
- [#1714](https://github.com/elan-registry/registry/issues/1714) — fix: **manual backups were failing outright.** The admin endpoint kept its own copy of the critical-tables list naming a `car_history` table that does not exist (it is `cars_hist`); once [#1696](https://github.com/elan-registry/registry/issues/1696) corrected the other copy and made a missing table abort rather than degrade silently, every manual backup threw. Rather than fix the name, all three hardcoded lists were deleted and replaced with `BackupManager::getAllTables()`, which discovers every base table from `information_schema` at runtime — a derived list cannot drift, and a table added by a future migration is backed up without anyone updating anything. **Backups now cover all tables, not six**, so they can actually restore the database; both entry points fail loudly if the schema query errors or returns nothing. Also removed an inert `#[Group('regression')]` tag on an integration test (the group only resolves under `tests/unit`, so it never ran under `composer test:regression`) and a stale `plans/` path in `start-issue.md`
- **Internal tooling and dependencies** (no user- or admin-visible change): Claude command thinking triggers and brief-output style ([#1666](https://github.com/elan-registry/registry/pull/1666)); working-doc convention moved out of the repo to a local `.claude.local.md` pointer ([#1589](https://github.com/elan-registry/registry/pull/1589)); Playwright 1.62.0 → 1.62.1 ([#1579](https://github.com/elan-registry/registry/pull/1579)); baseline migration made MODIFY-only for `users` so it never adds or reorders columns ([#1672](https://github.com/elan-registry/registry/pull/1672), folded into [#1553](https://github.com/elan-registry/registry/issues/1553)'s work above)
