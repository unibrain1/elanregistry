# Elan Registry v2.29.1 Release Notes

**Release Date:** [DATE]
**Type:** Patch Release — Honest Tests: Make the Harness Tell the Truth

## Required Actions After Deployment

[To be filled in as issues are completed — check for:
- PHPStan baseline regeneration after #1453/#1500 (`composer phpstan:baseline`)
- UserSpice framework update steps from #1495 (manual update — see issue for post-update TODOs)
- Fix-script cleanup if any land during this milestone]

## User-Facing Changes

### Bug Fixes

- **Car field clearing** ([#1448](https://github.com/elan-registry/registry/issues/1448)): Clearing an optional field (color, comments, website, engine, sold date) while editing a car now actually saves as empty instead of silently keeping the old value.

### Improvements

- **Public API privacy** ([#1501](https://github.com/elan-registry/registry/issues/1501)): Owner coordinates and internal user IDs are no longer exposed in public car-history and DataTables API responses.

## Admin-Facing Changes

### Bug Fixes

- **Backup data-loss detection** ([#1502](https://github.com/elan-registry/registry/issues/1502)): The backup routine no longer reports "Healthy" when a table dump silently loses its data — failures now surface for real.
- **Admin User Manager XSS** ([#1499](https://github.com/elan-registry/registry/issues/1499)): Closed a stored-XSS vector in the admin User Manager's email column.
- **Editor-role permission hardening** ([#1450](https://github.com/elan-registry/registry/issues/1450)): Closed an admin-account-takeover path via editor-role owner-email edits and unrestricted car deletion/merge.

### Improvements

- **UserSpice framework update** ([#1495](https://github.com/elan-registry/registry/issues/1495)): Updated to UserSpice >6.1.4.

## Issues Resolved

- [#1267](https://github.com/elan-registry/registry/issues/1267) — docs: rewrite wiki Registry Installation guide to document the actual install flow
- [#1283](https://github.com/elan-registry/registry/issues/1283) — refactor: extract shared transfer test fixture helper into TransferIntegrationTestCase base class
- [#1348](https://github.com/elan-registry/registry/issues/1348) — refactor: align usersc/classes/ directory structure — CarView into Car/, OwnerView into Owner/, admin/ casing
- [#1422](https://github.com/elan-registry/registry/issues/1422) — test: CarTransferRepository DB-error paths and findPendingWithCarById() have no unit tests
- [#1423](https://github.com/elan-registry/registry/issues/1423) — test: LogCategoriesUsageTest not extended for 3 admin files migrated in v2.28.0; CarTransferTest uses base Exception
- [#1440](https://github.com/elan-registry/registry/issues/1440) — test: retire mock Car class — CarCoreTest/CarCrudTest test scaffolding, not the real class
- [#1441](https://github.com/elan-registry/registry/issues/1441) — test: replace always-succeeds mock DB — unit tests can't exercise DB-error paths
- [#1444](https://github.com/elan-registry/registry/issues/1444) — test: retire upload/security helper reimplementations in unit bootstrap — tests validate mocks, not production
- [#1445](https://github.com/elan-registry/registry/issues/1445) — test: UserDeletionCleanupTest exercises a mock cleanup hook, not the real after_user_deletion path
- [#1446](https://github.com/elan-registry/registry/issues/1446) — test: CarValidator unit tests validate against mock CarModel, not real reference data
- [#1448](https://github.com/elan-registry/registry/issues/1448) — fix: Car::update() array_filter prevents clearing color/comments/website/engine/sold-date
- [#1450](https://github.com/elan-registry/registry/issues/1450) — security: editor role can edit any owner's email (admin-takeover vector) and delete/merge cars
- [#1453](https://github.com/elan-registry/registry/issues/1453) — fix: scope PHPStan global ignoreErrors so new project code isn't exempt from typing checks
- [#1454](https://github.com/elan-registry/registry/issues/1454) — test: add integration coverage for image lifecycle and backup restorability
- [#1467](https://github.com/elan-registry/registry/issues/1467) — test: mock DB query result triggers PHP warning in CarDataTablesServiceTest
- [#1495](https://github.com/elan-registry/registry/issues/1495) — chore: Update to UserSpice >6.1.4
- [#1499](https://github.com/elan-registry/registry/issues/1499) — security: escape $user->email in admin User Manager (stored XSS)
- [#1500](https://github.com/elan-registry/registry/issues/1500) — chore: fix composer check:php silently skipping PHPStan, quiet checker false positives, fix SecurityHeadersTest CRLF bug
- [#1501](https://github.com/elan-registry/registry/issues/1501) — security: remove lat/lon/user_id from public car-history and DataTables payloads
- [#1502](https://github.com/elan-registry/registry/issues/1502) — fix: BackupManager silent data-loss on backup dump + fake-healthy maintenance badge
- [#1503](https://github.com/elan-registry/registry/issues/1503) — test: make the integration test harness honest
