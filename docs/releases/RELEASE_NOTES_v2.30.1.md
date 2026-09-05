# Elan Registry v2.30.1 Release Notes

**Release Date:** September 4, 2026
**Type:** Patch Release - Verification System Refresh: Owner-Field Integrity

An owner who updates their name, email, website, or location sees that change
on every car they own, and the registry's records stop drifting from what the
owner last told it. Every owner-contact field mirrored onto `cars` now syncs
from the profile on every edit path, and the privacy policy is brought in line
with what the registry actually does with member data (Brevo, delivery
telemetry, cross-car sync, retention). Two production defects ship alongside:
the car-list CSRF stale-token failure and the admin merge action leaving
`userimages/` files behind. The integration-test gate is decoupled from
UserSpice so CI can run it.

## Required Actions After Deployment

[To be filled in as issues complete]

## User-Facing Changes

### New Features

- [To be filled in as issues complete]

### Improvements

- **Car list and registry searches no longer fail after a session expires** ([#1913](https://github.com/elan-registry/registry/issues/1913)): The car list, factory records list, car history, and statistics panels previously returned a "Could not load" error after a session timed out or the browser was restarted, because the CSRF token embedded in the page became stale. Public read-only endpoints now use rate limiting instead of token checks, eliminating the stale-token failure path entirely.

## Admin-Facing Changes

- [To be filled in as issues complete]

## Issues Resolved

- WIP: [#1752](https://github.com/elan-registry/registry/issues/1752) — tech-debt: decouple integration test suite from UserSpice's DB::getInstance() so CI can run the integration gate
- [#1867](https://github.com/elan-registry/registry/issues/1867) — bug: car merge ("duplicate"/"new owner" admin action) never moves userimages/ files to the surviving car
- WIP: [#1873](https://github.com/elan-registry/registry/issues/1873) — feat: sync all owner-contact fields (including email) to every car the owner has
- WIP: [#1876](https://github.com/elan-registry/registry/issues/1876) — docs: update the privacy policy for Brevo, bounce data, cross-car sync, and retention
- [#1913](https://github.com/elan-registry/registry/issues/1913) — fix: cars-list DataTable never recovers from a stale/lost CSRF token
- [#1931](https://github.com/elan-registry/registry/issues/1931) — test: integration bootstrap does not define ELAN_IMAGE_DIR — CarTransferTest fails standalone
- [#1947](https://github.com/elan-registry/registry/pull/1947) — chore: refresh local dev data from production (developer tooling; no issue)
