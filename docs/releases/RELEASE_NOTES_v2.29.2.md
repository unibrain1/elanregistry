# Elan Registry v2.29.2 Release Notes

**Release Date:** 2026-08-20
**Type:** Patch Release - Live User-Facing Defects

## Required Actions After Deployment

[To be filled in as issues are completed — likely includes running
`21-Fix-Page-Permissions.php` for #1582's new maintenance script, and a
manual removal of the orphaned `embed.php` from prod for #1594.]

## User-Facing Changes

Changes visible to public registry visitors (car listings, owner pages, search, etc.).

### Improvements

- **Join form webview fix** ([#1690](https://github.com/elan-registry/registry/issues/1690)): Every failed/blocked join attempt (CSRF, rate limit, validation, or a client-side-only block like Turnstile failing to load/render or GPS lookup errors) is now logged server-side, with visible per-field status indicators and a Turnstile failure message on the form itself.
- **Location rate-limiting rewrite** ([#1582](https://github.com/elan-registry/registry/issues/1582)): One-line description of the fix — TBD as work completes.
- **Docs embed 404 fix** ([#1594](https://github.com/elan-registry/registry/issues/1594)): One-line description of the fix — TBD as work completes.

## Admin-Facing Changes

- **DI-by-default CI guardrail** ([#1515](https://github.com/elan-registry/registry/issues/1515)): One-line description — TBD as work completes.

## Issues Resolved

- [#1515](https://github.com/elan-registry/registry/issues/1515) — ci: add DI-by-default convention guardrail
- [#1582](https://github.com/elan-registry/registry/issues/1582) — fix: location rate-limiting — per-requester buckets, dead lat/lon guard, and us_rate_limits retention
- [#1594](https://github.com/elan-registry/registry/issues/1594) — fix: embed.php hardcodes subdir=reference, 404ing every document outside reference/ (plus old-case filenames)
- [#1621](https://github.com/elan-registry/registry/issues/1621) — test: LocationService HTTP fallback branches (cURL failure, non-200, all-services-failed) untested
- [#1690](https://github.com/elan-registry/registry/issues/1690) — fix: join form submit silently fails on iOS/Google-App webview — no POST, no error, no server-side record
- [#1725](https://github.com/elan-registry/registry/issues/1725) — tech-debt: revisit ADR-015 — vendored frontend library versions can drift from package.json
