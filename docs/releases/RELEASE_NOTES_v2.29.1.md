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
- [#1538](https://github.com/elan-registry/registry/issues/1538) — fix: pdf-viewer.php wrapper pages flagged as Soft 404 by Google — added a hand-authored per-document metadata map (unique `$pageTitle`/`$pageDescription`/on-page description text per PDF, hardcoded literal strings only, resolved before `?doc=`/`subdir` validation to satisfy the project's before-`init.php` timing convention) plus a crawlable direct download link; unmapped documents (e.g. `docs/stories/*` PDFs) fall back to generic text rather than 404ing; added the 9 reference PDF asset URLs to `sitemap.xml`; added `PdfViewerDocumentMetadataConsistencyTest` to keep the metadata map and sitemap entries from drifting apart
- [#1283](https://github.com/elan-registry/registry/issues/1283) — refactor: extracted a shared `TransferIntegrationTestCase` base class for car-transfer integration tests — consolidated 4 independently-duplicated `createTransferRequest()`/`insertTransferRequest()` fixture helpers (across `CarTransferRepositoryIntegrationTest`, `TransferRequestTest`, `CarTransferWorkflowTest`, and `UserDeletionReassignmentTest`) into one canonical defaults array and teardown loop, and routed every insert through `CarTransferRepository::create()` instead of hand-written raw `INSERT`s; `TransferRequestConstraintTest` still has its own independent copy of the pattern (left out of scope) but a stale docblock reference to it was corrected
- [#1585](https://github.com/elan-registry/registry/issues/1585) — chore: extracted a narrow `ElanRegistry\DatabaseInterface` and a thin `DbAdapter` wrapper around UserSpice's `\DB`, so every production class that needs a database collaborator can be constructed against a real `DatabaseInterface` test double instead of a shared global mock — deleted `tests/bootstrap-unit.php`'s mock `DB`/`QueryResult` classes for good, closing #1441's original acceptance criterion. Added a global `dbi(): DatabaseInterface` helper for constructing the interface-typed handle; the ambient page-scope `$db` global is deliberately left untouched (a real `\DB`) so upstream UserSpice code that type-hints `\DB` directly keeps working. Added a regression guardrail (`DatabaseInterfaceUsageRegressionTest`) and a real-DB contract test (`DbAdapterContractTest`) to keep both halves of the fix from silently regressing. Along the way, found and fixed several latent bugs the interface's stricter typing surfaced: `CarRepository::findById()` and `app/owner/contact/owner.php` could fatal on a DB error/nonexistent method respectively; `BackupManager` could silently write a corrupt backup file if a table was dropped mid-backup; and several call sites bypassed the intended DB wiring entirely.
- [#1652](https://github.com/elan-registry/registry/issues/1652) — fix: `CarShowcaseService`'s `getNewCarIds()`/`buildShowcasePool()` were the one pair of methods in `usersc/classes/Car/` that #1585's DI migration missed — still `static` methods taking a raw `DatabaseInterface $db` parameter instead of constructor injection, so the class had no unit test, only a live-DB integration test. Converted to instance methods with constructor-injected `?DatabaseInterface $db = null` (defaulting to `dbi()`), matching `CarModel.php`'s pattern; updated the two production call sites (`index.php`, `app/owner/cars/index.php`) and the existing integration test accordingly; added a new unit test suite exercising both methods against a `DatabaseInterface` stub, including their DB-error fallback paths. `TransferEmailService`, the issue's other originally-listed straggler, was found already migrated by the time this issue was picked up (landed incidentally in #1662).
- [#1657](https://github.com/elan-registry/registry/issues/1657) — test: `CarDataTablesService::getDataTablesData()`'s two `is_object()` guards (added by #1585 when its DB collaborator was retyped to `DatabaseInterface`) had no test coverage, and the filtered-count code path they sit in (only entered when `search.value` is non-empty) was never reached by any existing unit test — the sole test stub always sent an empty `search.value`. Added 3 unit tests: one exercising the filtered-count block end-to-end with distinct total/filtered counts, and one negative-branch test per `is_object()` guard (mutation-verified locally — each test fails when its corresponding guard is removed).
- [#1679](https://github.com/elan-registry/registry/issues/1679) — tech-debt: automated the install steps that were previously manual or silently skipped, converting them to Phinx migrations. Turnstile hook registration moved off the orphaned `database/seed-turnstile-hooks.sql` (referenced by no doc) into `RegisterTurnstileHooks`. `SettingsBaselineSeed`'s `ELAN_DEFAULTS` never actually reached a real install — UserSpice's install wizard creates `settings` row 1 before `composer seed:run` runs, so the seed's "row exists, return" guard skipped unconditionally, silently dropping values like `elan_image_max` (the root cause of the FilePond `maxFiles: 0` bug); `UpdateSettingsBaselineDefaults` now owns those defaults and creates row 1 itself when provisioning without the wizard. `NoownerSeed` and `BaselinePermissionsSeed` were likewise converted to `RegisterNoownerAccount` and `RegisterBaselinePermissions` migrations (all three superseded seeds deleted), the latter fixing a real gap where a script-provisioned schema had an empty `permissions` table while `PageRegistrationSeed` and `21-Fix-Page-Permissions.php` both hardcode ids 2 and 3. `RegisterLoginLoggerHooks` — the already-merged migration this work mirrors — was wrapped in the transaction its DML always should have had. Validating a fresh-schema provisioning run also surfaced a real regression: transferring a car to the `noowner` account threw `CarValidationException` because `CarAdministrationService::transfer()` denormalizes the target owner's email onto `cars`/`car_history` and `noowner@invalid` deliberately fails `FILTER_VALIDATE_EMAIL` (that address is what keeps the account unreachable by password reset and passwordless login). This silently broke GDPR account deletion — `usersc/scripts/after_user_deletion.php` reassigns every car through that path inside one transaction, so the exception rolled the whole thing back and left the deleted owner's PII attached to their former cars. `transfer()` now blanks a non-routable owner email rather than propagating or rejecting it, and separately restores the owner-identity fields that `CarValidator` drops when empty, so a transfer fully overwrites the previous owner's `email`/`city`/`state`/`country`/`website` instead of leaving them behind. Follow-ups filed: [#1685](https://github.com/elan-registry/registry/issues/1685) (`notifications` table missing from provisioned schema) and [#1686](https://github.com/elan-registry/registry/issues/1686) (`CarValidator`'s drop-on-empty behavior is load-bearing but undocumented)
- [#1562](https://github.com/elan-registry/registry/issues/1562) — fix: admin car-reassignment "No Owner" option hardcoded the `noowner` system account's id as `83` in both `admin-core.js` and `tab-car_mgmt.php`, correct only by accident on production. `app/admin/index.php`'s `reassign` handler now accepts a `no_owner` flag and resolves the target id server-side via `User::find('noowner')`, failing cleanly with a logged error if the account is missing — a client-supplied id is never trusted for this path. Added `AdminCarReassignmentTest.php` asserting the resolved id matches the actual seeded `noowner` account, not a literal 83.
