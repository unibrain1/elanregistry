# Elan Registry v2.29.6 Release Notes

**Release Date:** TBD
**Type:** Patch Release - Security Audit Closure & Browser CI

## Required Actions After Deployment

TBD — filled in as issues are completed.

## User-Facing Changes

Changes visible to public registry visitors (car listings, owner pages, search, etc.).

### Improvements

- [#1452](https://github.com/elan-registry/registry/issues/1452) — fix: non-atomic car-create — cars.image committed before files moved (root cause of #1403); a failed file move during car creation now strips the unmoved filenames from the stored image list instead of leaving the record pointing at files that don't exist
- [#1539](https://github.com/elan-registry/registry/issues/1539) — fix: bare directory URLs (`/app/owner/`, `/app/owner/reports/`, `/docs/stories/`) returned a raw 403 instead of redirecting to a useful page, and a stale redirect chain broke `/app/reports/`; also fixes a guide page's stylesheet (`document-content.css`) 404ing due to a blanket redirect that was never meant to catch it — relocated the file to `app/assets/css/` (consolidated with #1595)

## Admin-Facing Changes

Changes visible only to administrators (admin dashboard, maintenance tools, settings, etc.).

### Improvements

- [#1830](https://github.com/elan-registry/registry/issues/1830) — fix: error/404.php and error/403.php silently stopped logging at v2.26.2 — bare LogCategories no longer resolves under the Composer autoloader; consolidated into error/500.php as the single handler for all 4xx/5xx codes
- WIP: [#1800](https://github.com/elan-registry/registry/issues/1800) — chore: contact the 4 owners whose car photos were lost and invite re-upload
- WIP: [#1689](https://github.com/elan-registry/registry/issues/1689) — fix: .git/.svn probes log as 127.0.0.1 — source-address handling makes them un-blockable and may indicate spoofable client IP

## Developer Notes

Dependency bumps (Dependabot), tracked in this milestone rather than merged
directly to `main`:

- [#1833](https://github.com/elan-registry/registry/pull/1833) — chore(deps-dev): bump phpstan/phpstan from 2.2.8 to 2.2.9
- [#1834](https://github.com/elan-registry/registry/pull/1834) — chore(deps): bump vlucas/phpdotenv from 5.6.4 to 5.7.0
- [#1835](https://github.com/elan-registry/registry/pull/1835) — chore(deps-dev): bump eslint from 10.8.1 to 10.9.1
- [#1836](https://github.com/elan-registry/registry/pull/1836) — chore(deps): bump maplibre-gl from 6.4.1 to 6.6.0

CI/test-infrastructure work, not user- or admin-facing:

- [#1788](https://github.com/elan-registry/registry/issues/1788) — test: `CAR_ID_STANDARD` in Playwright fixtures is now overridable via a `CAR_ID_STANDARD` env var (falls back to id 1), so local MAMP snapshots with different car-id numbering no longer skip or fail fixture-dependent tests
- [#1765](https://github.com/elan-registry/registry/issues/1765) — test: removed a stale `ajax-endpoints.spec.js` test referencing a Google Maps XML endpoint deleted during the MapLibre migration; the other finding (a CSRF check discrepancy) had already been resolved as a side effect of an unrelated PR (#1790)
- WIP: [#1253](https://github.com/elan-registry/registry/issues/1253) — test: add local Playwright tests for owner-only pages (contact-owner, privacy, user settings, verify)
- WIP: [#1781](https://github.com/elan-registry/registry/issues/1781) — bug: e2e/factory-registry-link.spec.js and other 'logged-in' project tests never run — no such Playwright project exists
- WIP: [#1443](https://github.com/elan-registry/registry/issues/1443) — ci: run Playwright browser tests in CI (de-MAMP the suite first)

## Issues Resolved

- [#1830](https://github.com/elan-registry/registry/issues/1830) — fix: error/404.php and error/403.php silently stopped logging at v2.26.2 — bare LogCategories no longer resolves under the Composer autoloader; consolidated into error/500.php as the single handler for all 4xx/5xx codes
- [#1452](https://github.com/elan-registry/registry/issues/1452) — fix: non-atomic car-create — cars.image committed before files moved (root cause of #1403); a failed file move during car creation now strips the unmoved filenames from the stored image list instead of leaving the record pointing at files that don't exist
- WIP: [#1800](https://github.com/elan-registry/registry/issues/1800) — chore: contact the 4 owners whose car photos were lost and invite re-upload
- [#1539](https://github.com/elan-registry/registry/issues/1539) — fix: bare directory URLs (`/app/owner/`, `/app/owner/reports/`, `/docs/stories/`) returned a raw 403 instead of redirecting to a useful page, and a stale redirect chain broke `/app/reports/`; also fixes a guide page's stylesheet (`document-content.css`) 404ing due to a blanket redirect that was never meant to catch it — relocated the file to `app/assets/css/` (consolidated with #1595)
- [#1788](https://github.com/elan-registry/registry/issues/1788) — test: `CAR_ID_STANDARD` in Playwright fixtures is now overridable via a `CAR_ID_STANDARD` env var (falls back to id 1), so local MAMP snapshots with different car-id numbering no longer skip or fail fixture-dependent tests
- [#1765](https://github.com/elan-registry/registry/issues/1765) — test: removed a stale `ajax-endpoints.spec.js` test referencing a Google Maps XML endpoint deleted during the MapLibre migration; the other finding (a CSRF check discrepancy) had already been resolved as a side effect of an unrelated PR (#1790)
- WIP: [#1253](https://github.com/elan-registry/registry/issues/1253) — test: add local Playwright tests for owner-only pages (contact-owner, privacy, user settings, verify)
- WIP: [#1781](https://github.com/elan-registry/registry/issues/1781) — bug: e2e/factory-registry-link.spec.js and other 'logged-in' project tests never run — no such Playwright project exists
- WIP: [#1443](https://github.com/elan-registry/registry/issues/1443) — ci: run Playwright browser tests in CI (de-MAMP the suite first)
- WIP: [#1689](https://github.com/elan-registry/registry/issues/1689) — fix: .git/.svn probes log as 127.0.0.1 — source-address handling makes them un-blockable and may indicate spoofable client IP
- [#1833](https://github.com/elan-registry/registry/pull/1833) — chore(deps-dev): bump phpstan/phpstan from 2.2.8 to 2.2.9
- [#1834](https://github.com/elan-registry/registry/pull/1834) — chore(deps): bump vlucas/phpdotenv from 5.6.4 to 5.7.0
- [#1835](https://github.com/elan-registry/registry/pull/1835) — chore(deps-dev): bump eslint from 10.8.1 to 10.9.1
- [#1836](https://github.com/elan-registry/registry/pull/1836) — chore(deps): bump maplibre-gl from 6.4.1 to 6.6.0
