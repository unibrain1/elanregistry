# Issue #1803: stale /usersc/templates/ElanRegistry/assets/images/ path 404s

**Branch:** `bug/1803-stale-image-path-redirect`
**Milestone:** `milestone/v2.29.4`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

- **Root cause:** the site's Open Graph image and email logo assets were
  moved from `usersc/templates/ElanRegistry/assets/images/` to
  `usersc/images/` at some point (correct copies dated 2026-05-01). No
  redirect was added at the time of the move, so external consumers that
  had already cached or captured the old URL (Facebook's link-card scraper,
  already-sent transactional email referencing the logo) continue to
  request the dead path.
- **Testing gap:** not applicable in the traditional sense — this isn't a
  code regression testable by unit/integration/browser tests, since no
  application code ever emits the stale path (confirmed via repo-wide grep:
  the string appears nowhere in the codebase). It's a residual-external-
  reference problem, only observable via production access logs /
  monitoring.
- **Preventive measure:** none needed for recurrence — this is a one-time
  cleanup for a historical asset move that already happened. Future asset
  path changes should add a redirect in the same PR that moves the file, a
  pattern already established by the existing `.htaccess` redirects for
  prior path migrations (e.g. `/docs/assets/` → `/docs/reference/assets/`
  in #715, line 165).

## Architecture & Design

Add a single `Redirect 301` line to `.htaccess`, following the exact
existing convention at line 164-165 (mod_alias `Redirect 301`, not
`RedirectMatch` or a `RewriteRule` — confirmed via Explore agent that this
file has no `RedirectMatch` precedent, and plain `Redirect 301 /old /new`
is the established pattern for simple static directory-to-directory
renames like this one). mod_alias directives are evaluated independently
of `RewriteEngine On`/`RewriteRule` ordering, so placement within the file
doesn't affect correctness — placed alongside the other legacy-path
redirects for grouping consistency.

```apache
# Old asset path moved to usersc/images/ (#1803) — external caches
# (Facebook link-card scraper, already-sent email) still request it
Redirect 301 /usersc/templates/ElanRegistry/assets/images/ /usersc/images/
```

Placed directly after line 165's `/docs/assets/` redirect (same "path
migration" grouping), before the "Additional legacy paths found via GSC"
section.

No template, PHP, or JS changes — confirmed via repo-wide grep that the
stale string doesn't appear anywhere in application code (only in
unrelated documentation/planning files referencing different subpaths
like `file_nav_config.php`, `nav.php`, `footer.php`, `header.php` — not
`assets/images/`).

`.htaccess` deploys identically to every environment via the same
git-push flow as PHP files (confirmed: no deploy-time templating, no
special-casing in `docs/development/DEPLOYMENT.md`) — no extra deployment
step needed beyond the normal `git push prod main`.

## Out of scope (per issue's own scoping)

- Editing page or email templates — confirmed the stale path isn't present
  in either.
- Forcing Facebook's Sharing Debugger re-scrape — a manual, post-deploy
  action for a human to perform (listed in the issue's acceptance criteria
  but not something a code change can do).

## Implementation Checklist

- [x] Add the `Redirect 301` line (with explanatory comment) to `.htaccess`
      after line 165 — `.htaccess` (parallel-safe — single file, one item)
- [x] Manually verify redirect syntax against Apache's mod_alias docs (no
      automated test infrastructure exists for `.htaccess` rules in this
      repo — confirmed no existing tests target `.htaccess` redirects) —
      `apachectl -t` confirms config syntax OK; local curl test against
      MAMP couldn't confirm the 301 end-to-end because MAMP serves this
      repo under `/ElanRegistry/Registry/` rather than site root, and the
      exact same limitation reproduces on the pre-existing, already-
      working-in-production `/docs/assets/` redirect (line 165) tested the
      same way — confirms this is a local-environment path-prefix
      limitation, not a defect in the new rule. Syntax matches the
      established convention exactly.
- [x] Run `composer check:docs` to confirm no doc drift flagged —
      "Documentation checks passed."

## Test Plan

No automated test — this repo has no test infrastructure for `.htaccess`
redirect rules (confirmed: no PHPUnit/Playwright test targets `.htaccess`
behavior anywhere in the existing suite; the existing redirect rules like
line 165 have none either). Verification is manual, post-deploy:

- `curl -I https://elanregistry.org/usersc/templates/ElanRegistry/assets/images/og-lotus-elan.jpg`
  → expect `301` with `Location: /usersc/images/og-lotus-elan.jpg`
- Same for `logo-72x72.png`
- After deploy, use Facebook's Sharing Debugger to force re-scrape affected
  URLs and confirm the link-card image renders
