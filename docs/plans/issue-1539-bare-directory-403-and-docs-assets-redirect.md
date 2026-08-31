# Issue #1539: Bare-directory 403s and docs/assets/ CSS 404 chain

**Branch:** `bug/1539-bare-directory-403-and-docs-assets-redirect`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR. One checklist item
(post-deploy manual `curl` verification against test.elanregistry.org)
intentionally deferred — cannot run until deployed.

## Context

Two related `.htaccess`-routing defects, consolidated into one issue because
both edit the same file for closely-related classes of bug (dead-end URLs the
app creates for itself), found via Google Search Console and production
monitoring respectively:

- **Part A**: `app/`, `app/owner/`, `app/owner/reports/`, and `docs/stories/`
  have `Options -Indexes` set but no `index.php`, so a bare-directory request
  returns a genuine 403. Worse, `.htaccess`'s own legacy redirect
  (`Redirect 301 /app/reports/ /app/owner/reports/`, from the #1040
  `app/owner/` migration) routes a crawler-known URL straight into that 403 —
  we manufacture the dead end ourselves. GSC has already surfaced
  `app/owner/reports/`; the other three are the same defect awaiting
  discovery.
- **Part B**: `.htaccess`'s blanket `Redirect 301 /docs/assets/
  /docs/reference/assets/` (added by #1369 for PDF-library GSC cleanup)
  catches `docs/assets/document-content.css` too — a stylesheet added later
  (#911/#913) that was never part of the PDF migration and was never copied
  to the target. Every request 301s then 404s. Cloudflare's edge cache masks
  this most of the time, but a confirmed real visitor and GoogleOther both
  hit the full chain in one ~42h window per the 2026-08-10 monitoring
  baseline.

**Design decisions made during planning** (both explicitly chosen over a
smaller-diff alternative, per this session's "do it well, not just
minimally" standard):

- **Part A destinations** are not uniform 404s. Where a bare directory has a
  genuinely useful landing page, redirect there; 404 only where none fits:
  - `app/owner/reports/` → `statistics.php` (only file present — the case
    GSC already found)
  - `app/owner/` → `app/owner/cars/` (the real owner feature area; `cars/`,
    `contact/`, `reports/` and a lone `privacy.php` sit there with no single
    obvious index otherwise)
  - `docs/stories/` → `docs/car-stories.php` (the actual stories index/listing
    page — confirmed to exist; it lives outside `docs/stories/` itself)
  - `app/` → 403 via `error/500.php` (the consolidated handler per #1830;
    contains `admin/`, `api/`, `owner/`, `views/` — structurally unrelated
    subdirectories with no coherent single destination even on reflection)
- **Part B**: relocate `document-content.css` to `app/assets/css/`, the
  repo's actual first-party hand-authored CSS convention (alongside
  `edit_car.css`, `location-picker.css` — source + `npm run build`-generated
  `.min.css` pair, referenced with `?v=<?= ASSET_VERSION ?>`), rather than
  leaving it under `docs/` (a content tree) or moving it into
  `docs/reference/assets/` (a PDF-only reference library — confirmed via
  Explore to contain zero CSS today, 23 files all PDF/PNG/TXT). Two
  alternatives were evaluated and rejected:
  - `usersc/css/` — **ruled out entirely**: gitignored, build-generated-only
    output per ADR-018 (`usersc/css/*` in `.gitignore`); a hand-authored file
    placed there would be silently wiped/never committed.
  - Excluding the CSS from the blanket redirect via a `RedirectMatch`
    exception (smaller diff, zero app-code changes) — rejected in favor of
    the proper relocation once the user set the explicit standard that more
    work for a better long-term fit is acceptable.
  - This also **eliminates the need for any `.htaccess` change for Part B** —
    once the file isn't under `docs/assets/` at all, the blanket redirect no
    longer touches it. No exception rule needed.

## Bug Escape Analysis

- **Root cause (Part A):** `Options -Indexes` (set redundantly in the root,
  `app/`, `docs/`, and `docs/stories/` `.htaccess` files) combined with no
  `index.php` in 4 directories produces a 403 on any bare-directory request;
  the `#1040` migration's `Redirect 301 /app/reports/ /app/owner/reports/`
  then actively routes traffic into one of them.
- **Root cause (Part B):** `.htaccess:165`'s blanket `docs/assets/ →
  docs/reference/assets/` redirect (added by #1369, commit `ad4be060`,
  2026-07-20) was written with only the PDF-library migration (#715) in
  mind. `document-content.css` was added a month earlier by #911/#913
  (commit `e928de04`, 2026-06-23) into the same directory, coexisting
  unnoticed until #1369's blanket rule swept it up too.
- **Testing gap:** no test exercised bare-directory requests to any of the 4
  paths, and no test requested `document-content.css` through its `<link>`-tag
  URL end-to-end (only a PDF-redirect e2e test exists at
  `tests/playwright/e2e/not-logged-in.spec.js:577-581`, which doesn't cover
  CSS). `tests/playwright/navigation.spec.js:109-111` covers the *old*
  `/app/reports/` → `/app/owner/reports/` redirect but not what that redirect
  ultimately resolves to (the bare-directory 403 downstream of it).
- **Preventive measures:** new Playwright e2e assertions for all 4
  bare-directory outcomes (Part A) and for `document-content.css` resolving
  to 200 with no redirect (Part B), per CLAUDE.md's Playwright Test
  Maintenance rule (public pages → `not-logged-in.spec.js`).

## UserSpice Integration

None — both parts are `.htaccess`/static-asset routing changes, not
UserSpice framework surface.

## Database & Security Considerations

- No schema changes, no auth/CSRF impact — pure routing/asset-location fixes.
- `Options -Indexes` (the actual security control preventing directory
  listing) is untouched by either part — Part A only changes what a bare
  directory request resolves to *after* being blocked from listing, not the
  listing prevention itself.
- Part A must avoid the exact prefix-match trap the issue calls out: a naive
  `Redirect 301 /app/owner/reports/ /app/owner/reports/statistics.php` also
  matches `/app/owner/reports/statistics.php` itself (mod_alias prefix
  matching), producing
  `/app/owner/reports/statistics.phpstatistics.php` and a broken page. Must
  use `RedirectMatch` with `^...$` anchors instead, following the one
  existing precedent (`docs/.htaccess:44`,
  `RedirectMatch 301 ^/docs/faq/screenshots/(.*)$ ...`).
- Part B's file move needs no path/permission registration
  (`z_us_root.php`'s `$path` array, `21-Fix-Page-Permissions.php`) — it's a
  static asset, not a PHP page.

## Architecture & Design

### Part A: `.htaccess` bare-directory redirects

Add anchored `RedirectMatch` rules (not `Redirect`, to avoid the prefix-match
trap) for the 3 directories getting a redirect, placed near the existing
`#1040` migration block (`.htaccess:138-149`):

```apache
RedirectMatch 301 ^/app/owner/reports/$ /app/owner/reports/statistics.php
RedirectMatch 301 ^/app/owner/$ /app/owner/cars/
RedirectMatch 301 ^/docs/stories/$ /docs/car-stories.php
```

`app/` gets no redirect — verified `.htaccess:5` already has
`ErrorDocument 403 /error/500.php` (wired up by #1830's consolidation), so a
bare `/app/` request already renders the branded handler today, not a raw
Apache 403 page. No `.htaccess` change needed for this directory — the
"branded 404/403" acceptance criterion is already satisfied by existing
infrastructure. This is worth confirming explicitly in the PR rather than
silently assuming, since it means Part A's `app/` case requires zero code
change, only a verification step.

**Legacy redirect chain fix**: update
`.htaccess:141` from `Redirect 301 /app/reports/ /app/owner/reports/` to
target `statistics.php` directly, avoiding the 301→301 chain the issue's
acceptance criteria calls out:

```apache
Redirect 301 /app/reports/statistics.php /app/owner/reports/statistics.php
Redirect 301 /app/reports/ /app/owner/reports/statistics.php
```

(Two rules: the specific-file line must come first so mod_alias's
prefix-match on the bare-directory line doesn't also catch the file path —
same trap, applied to fixing this line. Confirm ordering carefully; mod_alias
evaluates `Redirect` directives in the order they appear and uses the first
match.)

**Verification for every existing redirect in the `#1040`/legacy blocks**
(lines 139-149, 156-161, per the issue's explicit acceptance criterion that
none may regress) — re-check via `curl -sI` against test.elanregistry.org
post-deploy, not just locally, since `.htaccess` behavior can't be verified
by PHPUnit/local tooling.

### Part B: relocate `document-content.css`

1. `git mv docs/assets/document-content.css app/assets/css/document-content.css`
2. Add `'app/assets/css/document-content.css'` to `scripts/build.js`'s
   `cssFiles` array (~line 36-39), alongside `edit_car.css` and
   `location-picker.css`, so `npm run build` generates
   `app/assets/css/document-content.min.css`.
3. Update both `<link>` tags to the minified, versioned path, matching the
   existing `edit_car.min.css` pattern exactly
   (`app/owner/cars/edit.php:131`):
   - `docs/guides/car-transfer-faq.php:521`:
     `<link rel="stylesheet" href="<?= $us_url_root ?>app/assets/css/document-content.min.css?v=<?= ASSET_VERSION ?>">`
   - `app/admin/design-system.php:572`: same pattern
4. Update the doc-text mention at `app/admin/design-system.php:453`
   (`<code>docs/assets/document-content.css</code>`) to reference the new
   path — this is prose inside a live admin page describing the file's
   purpose, not a functional reference, but leaving it stale would
   contradict the code right below it.
5. No `.htaccess` change needed for Part B — once the file isn't under
   `docs/assets/`, the existing blanket redirect (`.htaccess:165`) simply
   doesn't apply to it anymore. `docs/assets/` becomes empty after this move
   (confirmed via Explore: `document-content.css` was the sole remaining
   file there) — leave the directory itself in place (removing it isn't
   required and its `.htaccess`/redirect infrastructure still serves the PDF
   redirect chain for other paths).

## Implementation Checklist

- [x] Add `RedirectMatch` rules for `app/owner/reports/`, `app/owner/`,
      `docs/stories/`; fix the `/app/reports/` legacy redirect to avoid the
      301→301 chain (specific-file rule before bare-directory rule) —
      `.htaccess:141-142,153-155`. Confirmed `ErrorDocument 403` at
      `.htaccess:5` already routes `app/` to the branded handler.
- [x] `git mv docs/assets/document-content.css
      app/assets/css/document-content.css`
- [x] Add `document-content.css` to `scripts/build.js`'s `cssFiles` array —
      `scripts/build.js:34-38`
- [x] Update `<link>` tag to the minified/versioned path —
      `docs/guides/car-transfer-faq.php:521`
- [x] Update `<link>` tag to the minified/versioned path and the doc-text
      mention — `app/admin/design-system.php:453,572`
- [x] Run `npm run build` locally to confirm
      `app/assets/css/document-content.min.css` generates cleanly — "Built 26
      files", no esbuild errors, 1.6KB output confirmed
- [x] Add Playwright e2e coverage — `tests/playwright/e2e/not-logged-in.spec.js`,
      new `describe` block "Bare-directory 403s and docs/assets/ CSS relocation
      (#1539)" with 8 tests: 3 bare-directory redirects, prefix-match-trap
      regression guard (`app/owner/reports/statistics.php` direct → 200 no
      redirect), legacy-chain regression guard (`app/reports/statistics.php` →
      single-hop 301), branded-403 check for `app/`, old-path CSS
      301→404 chain (corrected from initial assumption — file was moved not
      copied, so the untouched blanket `docs/assets/` redirect still applies
      and 404s at the target), new-path CSS 200. **Unverified** — `.htaccess`
      changes aren't deployed to test.elanregistry.org yet (confirmed via
      live `curl`), and local MAMP config excludes `**/e2e/**`. Verify post-
      deploy via `npm run test:e2e:test:not-logged-in`.
- [x] `tests/playwright/navigation.spec.js:109-111` reviewed — its
      `testRedirect()` helper follows final-URL only, doesn't distinguish
      hop count, so it remains accurate but provides no regression coverage
      against the chain bug (that's what the new dedicated tests are for).
      No change needed.
- [ ] Manual verification of every acceptance criterion via `curl -sI`
      against `test.elanregistry.org` post-deploy (cannot be verified by
      local/CI tooling — `.htaccess` behavior is server-config, not
      PHP-testable): bare-directory redirects/404, no redirect loop on
      `statistics.php` itself, existing `#1040`/legacy redirects unbroken,
      `document-content.css` resolves 200 with a cache-bypassed request,
      both referencing pages render styled
- [x] PHPStan baseline hygiene: confirm no touched PHP file carries
      pre-existing `phpstan-baseline.neon` entries — clean, no overrides;
      direct PHPStan run on both touched PHP files also clean, no errors
- [x] Run `security-reviewer` — 0 findings across all severities;
      `RedirectMatch` patterns verified anchored/hardcoded (no open-redirect
      risk), `Options -Indexes`/`ErrorDocument 403` confirmed untouched by
      diff
- [x] Run `senior-architect` review of the diff, address findings — 0
      Critical/High. Verified `RedirectMatch` anchoring and mod_alias
      ordering semantics independently (both correct). One Medium advisory
      (unrelated `.claude/commands/*.md` changes present in working tree,
      not part of this issue) — stashed out per user decision, not committed
      to this branch. One Low (stale test-plan prose superseded by the
      Implementation Checklist's own correction note) — no action needed,
      plan file deleted at merge time per convention.

## Test Plan

- New Playwright e2e tests in `tests/playwright/e2e/not-logged-in.spec.js`:
  - `GET /app/owner/reports/` → 301 → `statistics.php` → 200
  - `GET /app/owner/reports/statistics.php` directly → 200, no redirect
    (regression guard against the prefix-match trap)
  - `GET /app/owner/` → 301 → `app/owner/cars/` → 200
  - `GET /docs/stories/` → 301 → `docs/car-stories.php` → 200
  - `GET /app/` → 403 rendered via the branded `error/500.php` handler
    (already-working behavior per `.htaccess:5`'s existing `ErrorDocument
    403`; this test locks it in as a regression guard, not new behavior)
  - `GET /docs/assets/document-content.css` → 200 directly, no redirect
  - `GET /app/assets/css/document-content.min.css` → 200
- Existing `tests/playwright/navigation.spec.js:109-111` (`/app/reports/`
  redirect) re-verified against the corrected two-rule chain, still passing.
- Existing `tests/playwright/e2e/not-logged-in.spec.js:577-581` (PDF
  redirect under the same `.htaccess` blanket rule) re-verified unaffected
  by Part B's change (that rule still only concerns PDFs now).
- Manual `curl -sI` verification against `test.elanregistry.org` for every
  acceptance criterion — `.htaccess` behavior isn't exercised by PHPUnit and
  Playwright's local config may not hit the same Apache config as the real
  test server, so this is the authoritative check before merge.

## Documentation Plan

- No wiki or ADR impact — this is a routing/asset-location bugfix, not a new
  architectural pattern. `app/assets/css/` is an existing, already-documented
  convention (implicitly, via `CLASSES.md`/`CODING_STANDARDS.md`'s "Minify
  first-party JS/CSS" command); no new convention is being introduced.
- No `docs/development/CLASSES.md` or `DATABASE.md` impact — no classes or
  schema touched.
