# Elan Registry v2.29.4 Release Notes

**Release Date:** TBD
**Type:** Patch Release - Chassis Endpoint Cleanup

## Required Actions After Deployment

TBD — filled in as issues are completed.

## User-Facing Changes

TBD — filled in as issues are completed.

## Admin-Facing Changes

TBD — filled in as issues are completed.

## Issues Resolved

- WIP: [#1509](https://github.com/elan-registry/registry/issues/1509) — refactor: route remaining car-lookup endpoints through CarRepository
- WIP: [#1516](https://github.com/elan-registry/registry/issues/1516) — fix: harden Resize::openImage() and ApiResponse::send() against uncaught throwables
- WIP: [#1616](https://github.com/elan-registry/registry/issues/1616) — test: ApiResponse::send() has no coverage — every AJAX endpoint terminates through it
- WIP: [#1617](https://github.com/elan-registry/registry/issues/1617) — test: ChassisValidator private validation branches (race car, pre/post-1970 formats) untested
- WIP: [#1699](https://github.com/elan-registry/registry/issues/1699) — bug: three playwright npm scripts reference .test.js files that do not exist
- WIP: [#1732](https://github.com/elan-registry/registry/issues/1732) — test: datatables-xss.spec.js History section assumes ambient car data instead of creating its own fixture
- WIP: [#1764](https://github.com/elan-registry/registry/issues/1764) — tech-debt: chassis-availability.php SQL dedup and endpoint happy-path test coverage
- WIP: [#1771](https://github.com/elan-registry/registry/issues/1771) — tech-debt: revisit ADR-017 vendoring decision now that Node is available on prod
- WIP: [#1775](https://github.com/elan-registry/registry/issues/1775) — fix: script #25 (Cleanup Rate Limits) never records completion in fix_script_runs
- WIP: [#1776](https://github.com/elan-registry/registry/issues/1776) — fix: investigate why script #21 (Fix Page Permissions) Last Run doesn't update in production despite correct insert code
- WIP: [#1777](https://github.com/elan-registry/registry/issues/1777) — fix: maintenance/fix-script "Close Window" button navigates away instead of closing the popup window
