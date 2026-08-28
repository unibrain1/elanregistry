# Elan Registry v2.29.5 Release Notes

**Release Date:** August 27, 2026
**Type:** Patch Release - Domain-Class Integrity

## Required Actions After Deployment

1. Run `php scripts/generate-config.php` once against test's own `.env`,
   then once against prod's own `.env`, to populate `ADMIN_EMAILS`/
   `FEEDBACK_EMAIL` there from each environment's live `settings` row
   (#1067) — do this **before** deploying the PR, since the deploy removes
   the web-editable admin fallback for these values. After both
   environments are confirmed working, delete `scripts/generate-config.php`
   from the repo in a small follow-up commit; it is a one-time migration
   tool, not kept as ongoing infrastructure.
2. After deploying #1067, manually confirm the removed
   `app/api/admin/process-settings.php` route 404s on test and prod —
   closes the security threat-model loop the issue was filed to fix (a
   web-writable path to reroute admin/feedback emails via a compromised
   admin session).
3. **Note:** #1067 also raises the max upload file size limit from 2MB to
   3MB on production (`ELAN_IMAGE_UPLOAD_MAX_SIZE`) — confirmed via direct
   DB query that this value already differed between prod (2MB) and
   test/local (3MB) prior to this release; standardized on 3MB everywhere.
   This is a deliberate behavior change, not incidental to the config
   migration.
4. Run the one-time data-cleanup script from #1491 on test, verify affected
   row counts drop to 0, then run on prod.

## User-Facing Changes

### Improvements

- **Clearing car fields now persists** ([#1448](https://github.com/elan-registry/registry/issues/1448)): Emptying color, engine, comments, website, purchase date, or sold date on the edit form now actually saves — previously the value silently reverted on reload despite the save reporting success.
- **Editing a deleted/merged car now shows a clear message** ([#1313](https://github.com/elan-registry/registry/issues/1313)): Submitting an edit for a car that no longer exists now shows "This car could not be found" and redirects to your car list, instead of silently failing to save with no explanation.

## Admin-Facing Changes

### Improvements

- **Image and email config centralized** ([#1067](https://github.com/elan-registry/registry/issues/1067)): Image limits, transfer expiry, and email branding now read from `config.php`/`.env` instead of scattered admin-settings values; the Settings tab and its AJAX write endpoint are removed. Also resolves #1722's `elan_image_max` default conflict (was 6 vs. 10 depending on code path; now a single confirmed value of 6) and raises the maximum upload file size from 2MB to 3MB on production, matching what test/local already allowed.
- **Admin tab test coverage** ([#1660](https://github.com/elan-registry/registry/issues/1660)): Owner-management and health admin tabs now have Playwright smoke coverage.
- **Cars table whitespace cleanup** ([#1491](https://github.com/elan-registry/registry/issues/1491)): Legacy leading/trailing whitespace in `color`, `comments`, `variant`, `series`, `chassis`, `city`, `state`, `fname`, and `lname` is trimmed via a one-time admin script (`app/admin/scripts/fix/12-Trim-Cars-Column-Whitespace.php`, run once via the Maintenance tab). Also fixes a live gap where `CarValidator` never sanitized `fname`/`lname` on ownership transfers — those two fields now trim consistently with `city`/`state`/`country` (ASCII whitespace: spaces, tabs, newlines, CR).
- **Database errors on owner/car lookups no longer fail silently** ([#1505](https://github.com/elan-registry/registry/issues/1505)): `Owner` and `CarRepository` now throw a typed exception on a DB failure instead of returning `false`/`null`/`[]` indistinguishable from "not found." `Owner::create()`/`update()` also gained checked transaction handling, so a mid-write PHP error reliably rolls back instead of possibly leaving a half-written record.

## Issues Resolved

- [#1067](https://github.com/elan-registry/registry/issues/1067) — fix: Car/Owner domain code reads image and email config from scattered $settings properties instead of one source
- WIP: [#1225](https://github.com/elan-registry/registry/issues/1225) — refactor: redesign maintenance.php landing state — default to Maintenance, move Health signals to header (pulled into this milestone from v2.35.0 on 2026-08-28, depends on #1067 landing first)
- [#1313](https://github.com/elan-registry/registry/issues/1313) — fix: edit.php force-logs-out a user when the car is missing/merged instead of showing 404
- [#1448](https://github.com/elan-registry/registry/issues/1448) — fix: Car::update() array_filter prevents clearing color/comments/website/engine/sold-date
- [#1491](https://github.com/elan-registry/registry/issues/1491) — tech-debt: legacy trailing/leading whitespace in cars table string columns; also fixes CarValidator not sanitizing fname/lname
- [#1505](https://github.com/elan-registry/registry/issues/1505) — fix: stop silently returning false/null/[] on DB errors across Owner and CarRepository, including unchecked transaction integrity
- [#1519](https://github.com/elan-registry/registry/issues/1519) — refactor: move Token::check() CSRF validation out of the Car entity
- [#1618](https://github.com/elan-registry/registry/issues/1618) — test: Owner.php — ownership history, location-sync error paths, and quality-badge drift guard
- [#1653](https://github.com/elan-registry/registry/issues/1653) — fix: Car.php catches Exception instead of Throwable at 3 call sites
- [#1654](https://github.com/elan-registry/registry/issues/1654) — chore: add OwnerException abstract base; retire dead OwnerNotFoundException catches
- WIP: [#1660](https://github.com/elan-registry/registry/issues/1660) — test: admin tabs owner-mgmt and health have no Playwright smoke coverage
- WIP: [#1722](https://github.com/elan-registry/registry/issues/1722) — tech-debt: elan_image_max has two conflicting defaults (6 and 10)
