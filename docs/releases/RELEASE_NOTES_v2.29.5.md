# Elan Registry v2.29.5 Release Notes

**Release Date:** August 27, 2026
**Type:** Patch Release - Domain-Class Integrity

## Required Actions After Deployment

1. Ensure `ADMIN_EMAILS` and `FEEDBACK_EMAIL` are set in the `.env` file on
   test and prod **before** deploying the PR for #1067 — it removes the
   web-editable admin fallback for these values.
2. Run the one-time data-cleanup script from #1491 on test, verify affected
   row counts drop to 0, then run on prod.

## Admin-Facing Changes

### Improvements

- **Image and email config centralized** ([#1067](https://github.com/elan-registry/registry/issues/1067)): Image limits, transfer expiry, and email branding now read from `config.php`/`.env` instead of scattered admin-settings values; the Settings tab is removed.
- **Settings default conflict resolved** ([#1722](https://github.com/elan-registry/registry/issues/1722)): `elan_image_max` no longer has two disagreeing defaults.
- **Admin tab test coverage** ([#1660](https://github.com/elan-registry/registry/issues/1660)): Owner-management and health admin tabs now have Playwright smoke coverage.
- **Cars table whitespace cleanup** ([#1491](https://github.com/elan-registry/registry/issues/1491)): Legacy leading/trailing whitespace in `color`, `comments`, `variant`, `series`, `chassis`, `city`, `state`, `fname`, and `lname` is trimmed via a one-time admin script (`app/admin/scripts/fix/12-Trim-Cars-Column-Whitespace.php`, run once via the Maintenance tab). Also fixes a live gap where `CarValidator` never sanitized `fname`/`lname` on ownership transfers — those two fields now trim consistently with `city`/`state`/`country`, so this can't recur.

## Issues Resolved

- WIP: [#1067](https://github.com/elan-registry/registry/issues/1067) — fix: Car/Owner domain code reads image and email config from scattered $settings properties instead of one source
- WIP: [#1313](https://github.com/elan-registry/registry/issues/1313) — fix: edit.php force-logs-out a user when the car is missing/merged instead of showing 404
- WIP: [#1448](https://github.com/elan-registry/registry/issues/1448) — fix: Car::update() array_filter prevents clearing color/comments/website/engine/sold-date
- [#1491](https://github.com/elan-registry/registry/issues/1491) — tech-debt: legacy trailing/leading whitespace in cars table string columns; also fixes CarValidator not sanitizing fname/lname
- WIP: [#1505](https://github.com/elan-registry/registry/issues/1505) — fix: stop silently returning false/null/[] on DB errors across Owner and CarRepository, including unchecked transaction integrity
- WIP: [#1519](https://github.com/elan-registry/registry/issues/1519) — refactor: move Token::check() CSRF validation out of the Car entity
- WIP: [#1618](https://github.com/elan-registry/registry/issues/1618) — test: Owner.php — ownership history, location-sync error paths, and quality-badge drift guard
- WIP: [#1653](https://github.com/elan-registry/registry/issues/1653) — fix: Car.php catches Exception instead of Throwable at 3 call sites
- WIP: [#1654](https://github.com/elan-registry/registry/issues/1654) — chore: add OwnerException abstract base; retire dead OwnerNotFoundException catches
- WIP: [#1660](https://github.com/elan-registry/registry/issues/1660) — test: admin tabs owner-mgmt and health have no Playwright smoke coverage
- WIP: [#1722](https://github.com/elan-registry/registry/issues/1722) — tech-debt: elan_image_max has two conflicting defaults (6 and 10)
