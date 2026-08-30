# Elan Registry v2.29.6 Release Notes

**Release Date:** TBD
**Type:** Patch Release - Security Audit Closure & Browser CI

## Required Actions After Deployment

TBD — filled in as issues are completed.

## User-Facing Changes

Changes visible to public registry visitors (car listings, owner pages, search, etc.).

### Improvements

- WIP: [#1452](https://github.com/elan-registry/registry/issues/1452) — fix: non-atomic car-create — cars.image committed before files moved (root cause of #1403)

## Admin-Facing Changes

Changes visible only to administrators (admin dashboard, maintenance tools, settings, etc.).

### Improvements

- WIP: [#1557](https://github.com/elan-registry/registry/issues/1557) — security: loader.php's settings query fails open if the row is missing
- WIP: [#1468](https://github.com/elan-registry/registry/issues/1468) — fix: sanitizeHTML() strips all attribute values, breaking `<a href>` links in flash messages
- WIP: [#1830](https://github.com/elan-registry/registry/issues/1830) — fix: error/404.php and error/403.php silently stopped logging at v2.26.2 — bare LogCategories no longer resolves under the Composer autoloader
- WIP: [#1800](https://github.com/elan-registry/registry/issues/1800) — chore: contact the 4 owners whose car photos were lost and invite re-upload
- WIP: [#1539](https://github.com/elan-registry/registry/issues/1539) — fix: bare directory URLs return 403 and /docs/assets/ blanket redirect 404s document-content.css
- WIP: [#1689](https://github.com/elan-registry/registry/issues/1689) — fix: .git/.svn probes log as 127.0.0.1 — source-address handling makes them un-blockable and may indicate spoofable client IP

## Developer Notes

Dependency bumps (Dependabot), tracked in this milestone rather than merged
directly to `main`:

- [#1833](https://github.com/elan-registry/registry/pull/1833) — chore(deps-dev): bump phpstan/phpstan from 2.2.8 to 2.2.9
- [#1834](https://github.com/elan-registry/registry/pull/1834) — chore(deps): bump vlucas/phpdotenv from 5.6.4 to 5.7.0
- [#1835](https://github.com/elan-registry/registry/pull/1835) — chore(deps-dev): bump eslint from 10.8.1 to 10.9.1
- [#1836](https://github.com/elan-registry/registry/pull/1836) — chore(deps): bump maplibre-gl from 6.4.1 to 6.6.0

CI/test-infrastructure work, not user- or admin-facing:

- WIP: [#1788](https://github.com/elan-registry/registry/issues/1788) — test: local MAMP DB snapshot is missing car id 1 (CAR_ID_STANDARD), blocking Playwright fixture-dependent tests
- WIP: [#1765](https://github.com/elan-registry/registry/issues/1765) — bug: ajax-endpoints.spec.js has 2 real failures once login/baseURL is fixed (dead map-markers endpoint, CSRF check discrepancy)
- WIP: [#1253](https://github.com/elan-registry/registry/issues/1253) — test: add local Playwright tests for owner-only pages (contact-owner, privacy, user settings, verify)
- WIP: [#1781](https://github.com/elan-registry/registry/issues/1781) — bug: e2e/factory-registry-link.spec.js and other 'logged-in' project tests never run — no such Playwright project exists
- WIP: [#1443](https://github.com/elan-registry/registry/issues/1443) — ci: run Playwright browser tests in CI (de-MAMP the suite first)

## Issues Resolved

- WIP: [#1557](https://github.com/elan-registry/registry/issues/1557) — security: loader.php's settings query fails open if the row is missing
- WIP: [#1468](https://github.com/elan-registry/registry/issues/1468) — fix: sanitizeHTML() strips all attribute values, breaking <a href> links in flash messages
- WIP: [#1830](https://github.com/elan-registry/registry/issues/1830) — fix: error/404.php and error/403.php silently stopped logging at v2.26.2 — bare LogCategories no longer resolves under the Composer autoloader
- WIP: [#1452](https://github.com/elan-registry/registry/issues/1452) — fix: non-atomic car-create — cars.image committed before files moved (root cause of #1403)
- WIP: [#1800](https://github.com/elan-registry/registry/issues/1800) — chore: contact the 4 owners whose car photos were lost and invite re-upload
- WIP: [#1539](https://github.com/elan-registry/registry/issues/1539) — fix: bare directory URLs return 403 and /docs/assets/ blanket redirect 404s document-content.css (consolidated with #1595)
- WIP: [#1788](https://github.com/elan-registry/registry/issues/1788) — test: local MAMP DB snapshot is missing car id 1 (CAR_ID_STANDARD), blocking Playwright fixture-dependent tests
- WIP: [#1765](https://github.com/elan-registry/registry/issues/1765) — bug: ajax-endpoints.spec.js has 2 real failures once login/baseURL is fixed (dead map-markers endpoint, CSRF check discrepancy)
- WIP: [#1253](https://github.com/elan-registry/registry/issues/1253) — test: add local Playwright tests for owner-only pages (contact-owner, privacy, user settings, verify)
- WIP: [#1781](https://github.com/elan-registry/registry/issues/1781) — bug: e2e/factory-registry-link.spec.js and other 'logged-in' project tests never run — no such Playwright project exists
- WIP: [#1443](https://github.com/elan-registry/registry/issues/1443) — ci: run Playwright browser tests in CI (de-MAMP the suite first)
- WIP: [#1689](https://github.com/elan-registry/registry/issues/1689) — fix: .git/.svn probes log as 127.0.0.1 — source-address handling makes them un-blockable and may indicate spoofable client IP
- [#1833](https://github.com/elan-registry/registry/pull/1833) — chore(deps-dev): bump phpstan/phpstan from 2.2.8 to 2.2.9
- [#1834](https://github.com/elan-registry/registry/pull/1834) — chore(deps): bump vlucas/phpdotenv from 5.6.4 to 5.7.0
- [#1835](https://github.com/elan-registry/registry/pull/1835) — chore(deps-dev): bump eslint from 10.8.1 to 10.9.1
- [#1836](https://github.com/elan-registry/registry/pull/1836) — chore(deps): bump maplibre-gl from 6.4.1 to 6.6.0
