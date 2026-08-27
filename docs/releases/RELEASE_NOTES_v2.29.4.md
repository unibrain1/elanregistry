# Elan Registry v2.29.4 Release Notes

**Release Date:** TBD
**Type:** Patch Release - Chassis Endpoint Cleanup

## Required Actions After Deployment

1. **#1798**: verify Turnstile widget reset live on test.elanregistry.org
   (not testable locally — HTTPS required).
2. **#1803**: force Facebook's
   [Sharing Debugger](https://developers.facebook.com/tools/debug/) to
   re-scrape affected link-card URLs.
3. **#1806**: the new build step only takes effect on the *second* push to
   each environment (see DEPLOYMENT.md's "two pushes" hook-update quirk) —
   push, then immediately `git commit --allow-empty && git push` again,
   back-to-back. The frontend (DataTables/maps/charts/uploads) is degraded
   between the two pushes, so do not delay. After push #2, confirm
   `usersc/js`/`usersc/css` regenerated and pages render correctly.

## User-Facing Changes

Changes visible to public registry visitors (car listings, owner pages, search, etc.).

### Improvements

- **Turnstile widget reset** ([#1798](https://github.com/elan-registry/registry/issues/1798)): login form's widget now resets on token expiry or double-submit.
- **Fixed stale image link** ([#1803](https://github.com/elan-registry/registry/issues/1803)): corrected a broken asset path in link previews and email.

## Admin-Facing Changes

Changes visible only to administrators (admin dashboard, maintenance tools, settings, etc.).

### Improvements <!-- markdownlint-disable-line MD024 -->

- **Fix-script popup buttons** ([#1777](https://github.com/elan-registry/registry/issues/1777)): "Close Window" now closes the window instead of navigating away.
- **Fix-script "Last Run" tracking** ([#1775](https://github.com/elan-registry/registry/issues/1775),
  [#1776](https://github.com/elan-registry/registry/issues/1776)): scripts #21
  and #25 now record completion correctly.

## Issues Resolved

- [#1516](https://github.com/elan-registry/registry/issues/1516) — fix: harden
  Resize::openImage() and ApiResponse::send() against uncaught throwables
- [#1616](https://github.com/elan-registry/registry/issues/1616) — test:
  ApiResponse::send() has no coverage — every AJAX endpoint terminates through it
- [#1617](https://github.com/elan-registry/registry/issues/1617) — test:
  ChassisValidator private validation branches (race car, pre/post-1970 formats) untested
- [#1699](https://github.com/elan-registry/registry/issues/1699) — bug: three
  playwright npm scripts reference .test.js files that do not exist
- [#1732](https://github.com/elan-registry/registry/issues/1732) — test:
  datatables-xss.spec.js History section assumes ambient car data instead of
  creating its own fixture
- [#1764](https://github.com/elan-registry/registry/issues/1764) — tech-debt:
  chassis-availability.php SQL dedup and endpoint happy-path test coverage
- [#1771](https://github.com/elan-registry/registry/issues/1771) — tech-debt:
  revisit ADR-017 vendoring decision now that Node is available on prod
- [#1775](https://github.com/elan-registry/registry/issues/1775) — fix: script
  #25 (Cleanup Rate Limits) never records completion in fix_script_runs
- [#1776](https://github.com/elan-registry/registry/issues/1776) — fix:
  investigate why script #21 (Fix Page Permissions) Last Run doesn't update
  in production despite correct insert code
- [#1777](https://github.com/elan-registry/registry/issues/1777) — fix:
  maintenance/fix-script "Close Window" button navigates away instead of
  closing the popup window
- [#1798](https://github.com/elan-registry/registry/issues/1798) — fix: login
  form never resets the Turnstile widget, and empty-token submissions are
  logged nowhere
- [#1803](https://github.com/elan-registry/registry/issues/1803) — fix: stale
  /usersc/templates/ElanRegistry/assets/images/ path 404s — broken Facebook
  link-card image and logo in already-sent email
- [#1806](https://github.com/elan-registry/registry/issues/1806) — tech-debt:
  implement ADR-018 build-at-deploy for frontend vendoring
