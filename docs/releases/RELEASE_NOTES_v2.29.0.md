# Elan Registry v2.29.0 Release Notes

**Release Date:** [DATE]
**Type:** Minor Release — SEO & Site Health Fixes

## Required Actions After Deployment

[To be filled in as issues are completed]

- **#1473 (pdf-viewer.php subdir normalization):** After deploying, watch the next scheduled monitoring run for `Security: Invalid subdir attempted: reference/assets` entries to drop to zero — these are now silently 301-redirected and not logged at all. Separately, expect `PageNotFound` rows to rise: requests that omit `subdir` entirely (previously logged as `Security: Non-existent document requested`) and non-legacy invalid subdir values now log at `PageNotFound` instead. Confirm `curl -I 'https://elanregistry.org/docs/pdf-viewer.php?subdir=reference/assets&doc=<any>.pdf'` returns a `301` to the canonical `subdir=reference` form.
- **#1372 (paint-colors.php SEO):**
  - Run `npm run test:e2e` against the deployed test environment to validate the new Playwright title/description assertions against live Apache/PHP config
  - Manual: GSC → URL Inspection → Request Indexing for `https://elanregistry.org/docs/reference/paint-colors.php`
- **#1432 (page title/description convention rollout):**
  - Run `npm run test:e2e` against the deployed test environment to validate the extended Playwright title/description assertions (11 newly-titled pages) against live Apache/PHP config
  - Manual: GSC → URL Inspection → Request Indexing for each of the 11 pages now carrying a distinct title/description (see Issues Resolved for the list)
- **#1394 (Sendinblue plugin update):** Applied on local dev only so far. Still needs the same manual update (Admin → Spice Shaker → Installed Plugins → Update) on test and production, followed by the Test Email + password-reset smoke tests on each — see `docs/development/EMAIL_SYSTEM.md#updating-the-plugin`.
- **#1479 (backups wiped on every deploy):** After deploying, verify on the test server that a file placed in `backups/automated/` survives a subsequent deploy, and confirm `https://elanregistry.org/backups/` (and a direct file URL under it) return 403 on prod. Watch the next scheduled monitoring run for zero new `cleanupOldBackups` `realpath()` failures.
- **#1373 (dynamic sitemap.xml):** After deploying, confirm `curl https://elanregistry.org/sitemap.xml` returns valid XML with `Content-Type: application/xml` and includes `details.php?car_id=` entries. Manual: GSC → Sitemaps → Add a new sitemap: `https://elanregistry.org/sitemap.xml`. Record submission confirmation.
- **#1371 (Schema.org Car JSON-LD; noindex; apple-touch-icon):** After deploying, validate a sample car detail page (e.g. `https://elanregistry.org/app/owner/cars/details.php?car_id=1`) against [Google's Rich Results Test](https://search.google.com/test/rich-results) — confirm no errors. Confirm `https://elanregistry.org/apple-touch-icon.png` and `apple-touch-icon-precomposed.png` return 200. Watch GSC for the ~375/month apple-touch-icon 404s to drop to zero.

## User-Facing Changes

### New Features

- **Schema.org Car structured data** ([#1371](https://github.com/elan-registry/registry/issues/1371)): Car detail pages now include JSON-LD markup, improving Google indexing of the 182 public registry pages.
- **Dynamic sitemap.xml** ([#1373](https://github.com/elan-registry/registry/issues/1373)): Sitemap covering all public car registry pages, submitted to Google Search Console.

### Improvements

- **GSC 404 cleanup** ([#1409](https://github.com/elan-registry/registry/issues/1409)): Legacy path redirects and PDF filename case mismatch fix — eliminates remaining 404 noise from Google Search Console.
- **pdf-viewer.php subdir normalization** ([#1473](https://github.com/elan-registry/registry/issues/1473)): Legacy `docs/pdf-viewer.php?subdir=reference/assets` (and `stories/assets`) URLs — still indexed from before the current `subdir=reference`/`subdir=stories` convention — now 301-redirect to the canonical form instead of soft-erroring at 200. Invalid subdir values and missing documents now correctly return HTTP 404 (previously always 200), so Google Search Console can drop these dead URLs from its index. Also fixes a bug where a request that omitted the `subdir` parameter entirely could check for an existing PDF in the wrong directory and misreport it as "non-existent" in the security log — that request now correctly returns 404 for the invalid-URL shape it actually is, rather than silently misdiagnosing a real file as missing.
- **Paint colors SEO** ([#1372](https://github.com/elan-registry/registry/issues/1372)): `paint-colors.php` now has a descriptive `<title>` and meta description, so it can outrank the generic PDF snippet Google previously showed for this high-traffic page.
- **Page title/description convention rollout** ([#1432](https://github.com/elan-registry/registry/issues/1432)): 11 more top-value pages (car listing, factory data, statistics, docs hub, reference library, and more) now have distinct, descriptive `<title>` and meta description values instead of the generic site-wide default — improving how each page appears in search results and when shared on social media. `og:title`/`twitter:title` (previously always the generic site name regardless of page) now also reflect each page's specific title.
- **Location picker disambiguation** ([#1400](https://github.com/elan-registry/registry/issues/1400)): Searching an ambiguous city name (e.g. "Springfield") no longer silently collapses same-named cities in different states/regions into a single dropdown entry — owners now see all distinct matches (Springfield OH, Springfield MO, etc.) and can pick the correct one.
- **Registration account-enumeration fix** ([#1406](https://github.com/elan-registry/registry/issues/1406)): Registering with an already-registered email no longer reveals that the account exists. The response is identical to any other registration failure — same message text and same response timing — and the existing account holder is silently sent a private recovery email instead. The failure message is now shown in a modal (rather than an auto-dismissing toast) and reworded to point users toward checking their inbox. **Trade-off:** because response *timing* must also be identical regardless of failure reason, every failed registration attempt (not just ones involving an existing email — e.g. a mistyped password confirmation) now takes a fixed ~2 extra seconds before showing the failure message.

## Admin-Facing Changes

### New Features

- **Deployment log** ([#1424](https://github.com/elan-registry/registry/issues/1424)): Each deployment now writes a log entry to the system log, making it easy to correlate errors to a specific release.

### Bug Fixes

- **Admin page browser tab titles** ([#1430](https://github.com/elan-registry/registry/issues/1430)): `app/admin/index.php`, `maintenance.php`, and `design-system.php` now show their page-specific browser tab title (e.g. "Registry Maintenance - Health") instead of the generic site title — the `$pageTitle` variable was being set after the point the header template reads it, so the assignment silently had no effect. Cosmetic only; these pages are admin-only and not search-indexed.
- **DataTables pagination validation** ([#1399](https://github.com/elan-registry/registry/issues/1399)): Invalid pagination parameters (including the former "All" option, `length=-1`) now return a 422 error instead of a SQL 500. The "All" rows option has been removed from the cars and factory table menus; page size is capped at 100 server-side.
- **Location service cache silently disabled when APCu is present but non-functional** ([#1470](https://github.com/elan-registry/registry/issues/1470)): `LocationService`'s geocoding cache and its own per-user geocoding rate limiter both silently stopped working whenever the `apcu` PHP extension was loaded but not actually functional for the running context (e.g. `apc.enable_cli=Off`, common in CLI/cron execution) — the code checked only whether the extension existed, not whether it actually succeeded, so it never fell through to its documented file-based fallback. Both now correctly fall back to file-based caching whenever an APCu operation doesn't succeed. Found while adding automated CI test execution ([#1437](https://github.com/elan-registry/registry/issues/1437)).
- **bootstrap.min.css.map / bootstrap.bundle.min.js.map 404s** ([#1414](https://github.com/elan-registry/registry/issues/1414)): Site-wide (every page, not just the login form) — the Bootstrap CSS/JS loaded via `header.php` referenced source map files that were never deployed. The project's separate, out-of-sync `usersc/` Bootstrap copy (5.3.3) has been removed; the whole site now consistently loads UserSpice's own vendored copy (5.3.8, `users/css`/`users/js`) with matching official source maps. Since `users/` isn't tracked in git, the maps are re-vendored automatically — by a new `.githooks/post-merge` hook locally and by the `post-receive` deploy hook on test/prod — so they can't drift out of sync with future UserSpice updates.
- **Broken asset links on error pages for deeply-nested 404s** ([#1475](https://github.com/elan-registry/registry/issues/1475)): The 403/404/500 error pages hardcoded a single `../` in their CSS/JS/logo asset paths, which only resolves correctly when a failing request is exactly one directory deep. Any 404 nested two or more levels deep (e.g. under `docs/stories/assets/` or `docs/reference/assets/`) rendered the error page with broken styling and a missing logo. Now uses the dynamically-computed site root, correct at any nesting depth.

### Improvements

- **Sendinblue plugin update** ([#1394](https://github.com/elan-registry/registry/issues/1394)): Brevo/Sendinblue email plugin updated from 1.6.0 to 1.6.2 (a newer release than the 1.6.1 originally targeted was published upstream by the time the update was applied).

## Issues Resolved

- [#1371](https://github.com/elan-registry/registry/issues/1371) — feat: Schema.org Car JSON-LD structured data on details.php; noindex on factory.php and privacy.php; apple-touch-icon
- [#1372](https://github.com/elan-registry/registry/issues/1372) — fix: verify paint-colors.php title and meta description; submit to GSC for indexing
- [#1373](https://github.com/elan-registry/registry/issues/1373) — feat: create dynamic sitemap.xml for public car registry pages
- [#1394](https://github.com/elan-registry/registry/issues/1394) — Chore: Update sendinblue plugin from 1.6.0 to 1.6.2
- [#1399](https://github.com/elan-registry/registry/issues/1399) — bug: DataTables length=-1 ("All" option) and negative start cause SQL error in cars/factory list endpoints
- [#1400](https://github.com/elan-registry/registry/issues/1400) — fix: geocoding returns wrong city when multiple US cities share a name (Springfield OH → MO)
- [#1406](https://github.com/elan-registry/registry/issues/1406) — security: fix account enumeration during registration (generic response + silent recovery email)
- [#1409](https://github.com/elan-registry/registry/issues/1409) — fix: GSC 404 cleanup — legacy path redirects and PDF filename case mismatch
- [#1414](https://github.com/elan-registry/registry/issues/1414) — fix: bootstrap.bundle.min.js.map 404 — deploy or suppress source map
- [#1424](https://github.com/elan-registry/registry/issues/1424) — feat: log deployment events to system log on each successful push
- [#1475](https://github.com/elan-registry/registry/issues/1475) — investigate: template emits page-relative usersc asset URLs on docs/stories pages (404s)
- [#1430](https://github.com/elan-registry/registry/issues/1430) — bug: $pageTitle set after header render has no effect on 3 admin pages
- [#1432](https://github.com/elan-registry/registry/issues/1432) — feat: page-specific title/description convention rollout to 11 top-value pages; fix og:title/twitter:title; close #1431 (superseded)
- [#1436](https://github.com/elan-registry/registry/issues/1436) — test: isolate integration tests onto a dedicated test schema
- [#1437](https://github.com/elan-registry/registry/issues/1437) — ci: run PHPUnit unit + regression suites on every PR
- [#1470](https://github.com/elan-registry/registry/issues/1470) — bug: LocationService cache silently disabled when APCu is present but non-functional
- [#1471](https://github.com/elan-registry/registry/issues/1471) — test: SecurityHeadersTest CSP-hash check silently performs zero assertions in CI
- [#1473](https://github.com/elan-registry/registry/issues/1473) — fix: normalize legacy pdf-viewer subdir=reference/assets and return real 404 for invalid subdir/doc
- [#1479](https://github.com/elan-registry/registry/issues/1479) — fix: deploy hook deletes server backups on every push — remove backups/ from .deployignore
- [#1484](https://github.com/elan-registry/registry/issues/1484) — test: dynamic completeness gate for page-title/description convention (prevent silent regression on new pages)
