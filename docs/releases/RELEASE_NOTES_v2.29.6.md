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
- [#1850](https://github.com/elan-registry/registry/issues/1850) — perf: statistics map pins now build popup content lazily on first open (instead of eagerly for every marker) and use the `-resized-100` thumbnail instead of the full-size original photo — eliminates an unconditional full-resolution image download for every car on page load
- [#1837](https://github.com/elan-registry/registry/issues/1837) — fix: `privacy.php` and `car-transfer-faq.php` sent a redirect header to a nonexistent `403.php` on a denied `securePage()` check but never stopped execution, so the protected content rendered anyway regardless of the check's result — both now call `die()` on denial, matching the convention used by every other `securePage()`-gated page in the codebase

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
- [#1253](https://github.com/elan-registry/registry/issues/1253) — test: added local Playwright coverage for `privacy.php`, `user_settings.php`, and the unverified-state path of `verify.php` (`contact/owner.php` landed separately via #1585); the "verified" success-path for `verify.php` is deliberately deferred, no DB fixture exists for it yet
- WIP: [#1781](https://github.com/elan-registry/registry/issues/1781) — bug: e2e/factory-registry-link.spec.js and other 'logged-in' project tests never run — no such Playwright project exists
- WIP: [#1443](https://github.com/elan-registry/registry/issues/1443) — ci: run Playwright browser tests in CI (de-MAMP the suite first)

## Issues Resolved

- [#1830](https://github.com/elan-registry/registry/issues/1830) — fix: error/404.php and error/403.php silently stopped logging at v2.26.2 — bare LogCategories no longer resolves under the Composer autoloader; consolidated into error/500.php as the single handler for all 4xx/5xx codes
- [#1452](https://github.com/elan-registry/registry/issues/1452) — fix: non-atomic car-create — cars.image committed before files moved (root cause of #1403); a failed file move during car creation now strips the unmoved filenames from the stored image list instead of leaving the record pointing at files that don't exist
- WIP: [#1800](https://github.com/elan-registry/registry/issues/1800) — chore: contact the 4 owners whose car photos were lost and invite re-upload
- [#1539](https://github.com/elan-registry/registry/issues/1539) — fix: bare directory URLs (`/app/owner/`, `/app/owner/reports/`, `/docs/stories/`) returned a raw 403 instead of redirecting to a useful page, and a stale redirect chain broke `/app/reports/`; also fixes a guide page's stylesheet (`document-content.css`) 404ing due to a blanket redirect that was never meant to catch it — relocated the file to `app/assets/css/` (consolidated with #1595)
- [#1850](https://github.com/elan-registry/registry/issues/1850) — perf: statistics map pins now build popup content lazily on first open (instead of eagerly for every marker) and use the `-resized-100` thumbnail instead of the full-size original photo — eliminates an unconditional full-resolution image download for every car on page load
- [#1837](https://github.com/elan-registry/registry/issues/1837) — fix: `privacy.php` and `car-transfer-faq.php` sent a redirect header to a nonexistent `403.php` on a denied `securePage()` check but never stopped execution, so the protected content rendered anyway regardless of the check's result — both now call `die()` on denial, matching the convention used by every other `securePage()`-gated page in the codebase
- [#1788](https://github.com/elan-registry/registry/issues/1788) — test: `CAR_ID_STANDARD` in Playwright fixtures is now overridable via a `CAR_ID_STANDARD` env var (falls back to id 1), so local MAMP snapshots with different car-id numbering no longer skip or fail fixture-dependent tests
- [#1765](https://github.com/elan-registry/registry/issues/1765) — test: removed a stale `ajax-endpoints.spec.js` test referencing a Google Maps XML endpoint deleted during the MapLibre migration; the other finding (a CSRF check discrepancy) had already been resolved as a side effect of an unrelated PR (#1790)
- [#1253](https://github.com/elan-registry/registry/issues/1253) — test: added local Playwright coverage for `privacy.php`, `user_settings.php`, and the unverified-state path of `verify.php` (`contact/owner.php` landed separately via #1585); the "verified" success-path for `verify.php` is deliberately deferred, no DB fixture exists for it yet
- WIP: [#1781](https://github.com/elan-registry/registry/issues/1781) — bug: e2e/factory-registry-link.spec.js and other 'logged-in' project tests never run — no such Playwright project exists
- WIP: [#1443](https://github.com/elan-registry/registry/issues/1443) — ci: run Playwright browser tests in CI (de-MAMP the suite first)
- WIP: [#1689](https://github.com/elan-registry/registry/issues/1689) — fix: .git/.svn probes log as 127.0.0.1 — source-address handling makes them un-blockable and may indicate spoofable client IP
- [#1833](https://github.com/elan-registry/registry/pull/1833) — chore(deps-dev): bump phpstan/phpstan from 2.2.8 to 2.2.9
- [#1834](https://github.com/elan-registry/registry/pull/1834) — chore(deps): bump vlucas/phpdotenv from 5.6.4 to 5.7.0
- [#1835](https://github.com/elan-registry/registry/pull/1835) — chore(deps-dev): bump eslint from 10.8.1 to 10.9.1
- [#1836](https://github.com/elan-registry/registry/pull/1836) — chore(deps): bump maplibre-gl from 6.4.1 to 6.6.0
