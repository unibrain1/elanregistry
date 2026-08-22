# Elan Registry v2.29.2 Release Notes

**Release Date:** 2026-08-20
**Type:** Patch Release - Live User-Facing Defects

## Required Actions After Deployment

- Run `21-Fix-Page-Permissions.php` on test then prod to register the new
  `app/admin/scripts/maintenance/25-Cleanup-Rate-Limits.php` maintenance
  script's path in UserSpice's permission table (#1582).
- Manually delete the orphaned `docs/embed.php` from the production
  filesystem (#1594) — it was removed from git in `f70b54b9` but never from
  the deployed server, so it kept running and rebuilding bad redirect URLs on
  every hit. This fix stops the 404s at `pdf-viewer.php` regardless, but the
  orphaned file should still be cleaned up since it has no reason to exist.

## User-Facing Changes

Changes visible to public registry visitors (car listings, owner pages, search, etc.).

### Improvements

- **Join form webview fix** ([#1690](https://github.com/elan-registry/registry/issues/1690)): Every failed/blocked join attempt (CSRF, rate limit, validation, or a client-side-only block like Turnstile failing to load/render or GPS lookup errors) is now logged server-side, with visible per-field status indicators and a Turnstile failure message on the form itself.
- **Location rate-limiting rewrite** ([#1582](https://github.com/elan-registry/registry/issues/1582)): Location search and reverse-geocoding (used during registration) now rate-limit each visitor independently by IP address instead of sharing one global bucket across every anonymous visitor site-wide — fixes a live bug where unrelated traffic could exhaust the shared bucket and block a real registrant. Also fixes a dead input guard that let missing/garbage coordinates silently reach the third-party geocoding API uncounted.
- **PDF viewer wrong-subdir and case-sensitive 404 fix** ([#1594](https://github.com/elan-registry/registry/issues/1594)): `docs/pdf-viewer.php` now resolves a requested document across both allowlisted subdirectories (`reference`, `stories`) with a case-insensitive filename match and 301-redirects to the canonical URL when the requested subdir or filename case doesn't match what's on disk — fixes 404s for documents outside `reference/` and for pre-rename uppercase filenames, both from an orphaned `docs/embed.php` still running on production (see Required Actions).

## Admin-Facing Changes

- **DI-by-default CI guardrail** ([#1515](https://github.com/elan-registry/registry/issues/1515)): One-line description — TBD as work completes.
- **Rate limit log cleanup maintenance script** ([#1582](https://github.com/elan-registry/registry/issues/1582)): New repeatable admin maintenance script (`app/admin/scripts/maintenance/25-Cleanup-Rate-Limits.php`) purges `us_rate_limits` rows older than 24 hours — same operation already available from the Rate Limiting Control Center's cleanup modal, now runnable on demand without visiting that page.

## Issues Resolved

- [#1515](https://github.com/elan-registry/registry/issues/1515) — ci: add DI-by-default convention guardrail
- [#1582](https://github.com/elan-registry/registry/issues/1582) — fix: location rate-limiting — per-requester buckets, dead lat/lon guard, and us_rate_limits retention
- [#1594](https://github.com/elan-registry/registry/issues/1594) — fix: embed.php hardcodes subdir=reference, 404ing every document outside reference/ (plus old-case filenames)
- [#1621](https://github.com/elan-registry/registry/issues/1621) — test: LocationService HTTP fallback branches (cURL failure, non-200, all-services-failed) untested
- [#1690](https://github.com/elan-registry/registry/issues/1690) — fix: join form submit silently fails on iOS/Google-App webview — no POST, no error, no server-side record
- [#1725](https://github.com/elan-registry/registry/issues/1725) — tech-debt: revisit ADR-015 — vendored frontend library versions can drift from package.json
