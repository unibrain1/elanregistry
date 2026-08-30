# Issue #1830: Consolidate 4xx/5xx error handlers into a single file

**Branch:** `bug/1830-error-page-logcategories-namespace`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Context

`error/404.php` and `error/403.php` guard their `logger()` call with
`class_exists('LogCategories')` using the bare, global-namespace name. Since
v2.26.2 replaced the custom autoloader with Composer PSR-4 (which only
registers `ElanRegistry\LogCategories`), this guard has evaluated `false` on
every request for 47 days — both handlers silently write zero log rows,
removing all 403/404 telemetry. This also blocks #1689, which needs live
`AccessDenied` rows.

Original scope was a 2-line fix (add `use` import, drop `class_exists()`) to
both files independently. During planning, the user asked whether all
4xx/5xx error handling could be consolidated into one file — investigation
found `error/500.php` is **already** a generic, status-code-driven handler
covering 400/401/405/408/500/502/504 via `$_SERVER['REDIRECT_STATUS']`, with
no `class_exists()` bug (it matches log categories by bare string value, not
class reference — fragile but not currently broken). Consolidating 403/404
into that existing infrastructure is smaller and more durable than
patching two duplicate files independently, so scope was expanded per user
direction.

## Bug Escape Analysis

- **Root cause:** compound guard `class_exists('LogCategories')` checks an
  unqualified class name that never resolves once the app moved to Composer
  PSR-4 (`ElanRegistry\LogCategories` only). No `use` import in either file.
- **Testing gap:** the only coverage of these files
  (`tests/integration/ErrorPageHeadersTest.php`) is pure string-matching on
  raw file content — it never executes the PHP or asserts anything about
  `LogCategories` resolving or `logger()` firing. No test anywhere exercises
  the guard condition itself.
- **Preventive measures:** the consolidated file will reference
  `LogCategories::` constants directly (not bare string literals) for
  *every* status code, closing this class of bug for good — a future
  autoloader regression now throws a loud fatal instead of silently
  no-op'ing. Add a regression test asserting `class_exists(\ElanRegistry\LogCategories::class)`
  resolves, plus behavioral tests confirming actual log rows get written.

## UserSpice Integration

None needed — `error/*.php` are project-owned pages, not `/users/` upstream.

## Database & Security Considerations

- No schema changes.
- No auth/CSRF impact — unauthenticated error pages.
- Restores existing security telemetry (403/404 logging); no new PII
  captured (`$logMessage` already includes IP/user-agent/referer today).
- Retention: 404 logging gets a static-asset extension filter
  (`.jpg/.jpeg/.png/.gif/.css/.js/.map`) to avoid recreating the ~290
  rows/day bloat that led to #1477 being closed `not planned`. 403 logs
  unconditionally (low volume — 86/29 days in access logs).
- Icon SVGs sourced from the current files are fixed, developer-authored
  inline markup with no user input — safe to echo directly, no
  `htmlspecialchars()` needed on that value.

## Architecture & Design

**Keep the filename `error/500.php`** as the single consolidated handler —
do not rename. This limits the `.htaccess` diff to 2 lines (403/404 targets)
instead of rewriting all 9 `ErrorDocument` lines, and reuses the most
mechanically complete existing file (try/init.php resilience pattern,
generic template, status-code-driven arrays already in place). Mitigate the
"misleading name" concern with an updated docblock stating plainly this is
the canonical handler for all 9 codes, filename retained for continuity.

### `.htaccess` (2-line change)

```text
ErrorDocument 403 /error/500.php
ErrorDocument 404 /error/500.php
```

### `error/500.php` changes

1. **Add `use ElanRegistry\LogCategories;`** after `declare(strict_types=1);`.
2. **`$logCategoryMap`** — switch from bare string literals to
   `LogCategories::` constants for all 9 codes; add 403 (reuses
   `LOG_CATEGORY_ACCESS_DENIED`, same as 401) and 404
   (`LOG_CATEGORY_PAGE_NOT_FOUND`). No `class_exists()` guard needed —
   `function_exists('logger')` remains the sole guard, matching the
   already-proven-safe pattern.
3. **Log message format** — unify on the existing generic
   `"%d Error | URI: %s | Referer: %s | IP: %s | Method: %s | User-Agent: %s"`
   for all codes, replacing 403/404's current per-code literal prefixes.
   Confirmed via grep: nothing parses these log strings programmatically, so
   this is safe. (User-confirmed.)
4. **404-specific static-asset filter** — new logic, gated strictly on
   `$statusCode === 404`, wrapping the `logger()` call:

   ```php
   $skipLogging = false;
   if ($statusCode === 404) {
       $staticExtensions = ['jpg', 'jpeg', 'png', 'gif', 'css', 'js', 'map'];
       $requestPath = parse_url($request_uri ?? '', PHP_URL_PATH) ?: '';
       $ext = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));
       $skipLogging = in_array($ext, $staticExtensions, true);
   }
   if (!$skipLogging && function_exists('logger')) {
       try {
           logger($userId, $logCategory, $logMessage);
       } catch (Throwable $e) {
           // Silently fail if logging not available
       }
   }
   ```

   Keeps the existing try/catch as the safety net; the filter is a pure
   pre-check.
5. **`$errorMessages`** — add 403/404 entries as **single-sentence** copy
   (no embedded `<br>`), preserving the existing `htmlspecialchars()`
   rendering path with zero special-casing (user-confirmed):

   ```php
   403 => [
       'title' => 'Access Forbidden',
       'message' => "You don't have permission to access this resource. This area may require special privileges or authentication.",
       'icon_type' => 'lock',
   ],
   404 => [
       'title' => 'Page Not Found',
       'message' => "The page you're looking for doesn't exist, has been moved, or the URL was mistyped.",
       'icon_type' => 'search',
   ],
   ```

   Standardize 403's wording on **"Access Forbidden"** everywhere (fixes
   the current h1/h2 "Forbidden" vs. "Denied" inconsistency — user-confirmed).
6. **Icon branching fix** — the current template hardcodes one generic SVG
   despite `$errorMessages` declaring per-code `icon_type`. Fix now (needed
   for 403/404 visual parity, user-confirmed): replace the hardcoded icon
   block with an `$iconSvgMap` keyed by `icon_type`, adding real `lock`
   (from current 403.php) and `search` (from current 404.php) SVGs sourced
   from the files being deleted. `warning`/`hourglass`/`error` keep today's
   one shared generic icon — no new SVGs invented for those (out of scope
   polish, noted for a possible follow-up).
7. **Docblock** — rewrite to document this as the canonical handler for all
   9 codes; remove the "Specific 403 and 404 pages are in dedicated files"
   line and the "mirror the change in error/403.php and error/404.php"
   cross-file comments near the header-setting block.

### File deletion

Delete `error/403.php` and `error/404.php` entirely. Confirmed via
repo-wide grep: no code references these paths. (Two `'403.php'` string
hits in `app/owner/privacy.php` and `docs/guides/car-transfer-faq.php`
are a **pre-existing, unrelated** broken reference to a nonexistent
root-level `/403.php` — filed separately as
[#1837](https://github.com/elan-registry/registry/issues/1837), not fixed
here.) User confirmed no external monitoring/infra dependency on these
exact file paths.

### Resilience property preserved

Confirmed: `header()` calls (lines 27-28) unconditionally precede the
try/init.php block (line 34+) in current `500.php`. Every planned addition
lands either before this block (`use` import) or well after it (log
category map, static-asset filter, error messages, icon map) — the
"headers survive even if init.php throws" property is untouched. Existing
`ErrorPageHeadersTest::testErrorPageHeadersBeforeInitPhp` is the regression
guard; re-verify line ordering in review.

## Implementation Checklist

- [x] Source the exact `lock` (403) and `search` (404) SVG markup from the
      current files before deleting them — `error/403.php`, `error/404.php`
      (read-only reference step, parallel-safe)
- [x] Add `use ElanRegistry\LogCategories;`, update docblock, update
      `$logCategoryMap` to use constants + add 403/404 entries, unify
      `$logMessage` format, add 404-only static-asset filter, add 403/404
      `$errorMessages` entries, add `$iconSvgMap` with lock/search SVGs and
      replace the hardcoded icon block, remove stale cross-file comments —
      `error/500.php` (depends on: SVG-sourcing step above)
- [x] Delete `error/403.php` — (parallel-safe, independent of 500.php edit)
- [x] Delete `error/404.php` — (parallel-safe, independent of 500.php edit)
- [x] Update `.htaccess` lines 5-6 (403/404 `ErrorDocument` targets) to
      point at `/error/500.php` — `.htaccess` (parallel-safe)
- [x] Update `tests/integration/ErrorPageHeadersTest.php`: collapse the
      3-entry data provider to a single `['500.php']` entry, update class
      docblock — (depends on: 500.php edit + file deletions, so tests
      reflect final state)
- [x] Add a regression test asserting
      `class_exists(\ElanRegistry\LogCategories::class)` resolves under the
      Composer autoloader — `tests/integration/ErrorPageHeadersTest.php`
      (depends on: 500.php edit)
- [x] Add behavioral regression tests: a request triggering 404 on a
      non-static path writes one `PageNotFound` row; a request to a static
      asset path writes zero rows; a request triggering 403 writes one
      `AccessDenied` row — `tests/integration/ErrorPageHeadersTest.php` or a
      new sibling file (depends on: 500.php edit)
- [x] Add a lightweight static test confirming `$errorMessages`/
      `$logCategoryMap` contain entries for all 9 status codes (guards
      against a future edit silently dropping a code) — same test file
      (depends on: 500.php edit)
- [x] Repo-wide grep for `error/403`, `error/404`, stale references in
      docblocks/docs/other tests — confirm none remain (depends on: all
      edits above). Found and fixed: `PagePermissionClassifier.php` and
      `21-Fix-Page-Permissions.php` comments, ADR-007 and ADR-015. Left
      unchanged (pre-existing, out of scope, tracked separately as #1837):
      `app/owner/privacy.php` and `docs/guides/car-transfer-faq.php`'s
      broken `403.php` redirect references.
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries (fix or explicitly defer per
      `/execute-plan` Step 6.5). Found and fixed one pre-existing entry on
      `error/500.php` (`nullCoalesce.expr` on the `http_response_code()`
      fallback chain — dead code, since that function never returns null);
      baseline regenerated, entry removed.
- [x] Run `/security-review` (touches logging of IP/user-agent/referer,
      restoring existing behavior — not new), address Critical/High. 0
      findings at all severities.
- [x] Run `senior-architect` review of the diff, address findings. 0
      Critical/High. 1 Medium (test subprocess env-loading pattern) fixed —
      aligned with `LogDeploymentScriptTest`'s `putenv()`-based credential
      propagation, kept the additional `Dotenv::createMutable()` reload
      (needed because `error/500.php`'s subprocess loads the full
      `users/init.php` bootstrap, unlike `log-deployment.php`'s lighter DB-
      only path — `putenv()` alone left `$settings` hydration broken). 2 Low
      items reviewed: unguarded `$iconSvgMap[$iconType]` lookup left
      as-is (PHPStan proves it's unreachable given the fixed 5-key map, and
      adding a `??` fallback there reintroduces the exact
      `nullCoalesce.offset` dead-code finding just fixed above); the
      pre-existing broken `403.php` redirects confirmed out of scope
      (see grep item above).
- [x] Manual verification on test.elanregistry.org: non-existent
      non-static page → `PageNotFound` row written, correct icon/copy
      rendered; `/.git/config` → `AccessDenied` row written, correct
      icon/copy rendered; a missing `.jpg` path → zero rows written
      (per issue's acceptance criteria — live-environment confirmation the
      automated tests can't fully replicate). Deferred to the deploy step
      per project convention — equivalent behavior already verified via
      the new automated integration tests (10/10 passing) using the same
      subprocess-driven request/response cycle against the real test-schema
      database.

## Test Plan

- **New regression coverage** in `tests/integration/ErrorPageHeadersTest.php`:
  - `LogCategories` class-resolution assertion (compile-time safety net)
  - 404 non-static path → one `PageNotFound` row written
  - 404 static-asset path → zero rows written
  - 403 → one `AccessDenied` row written
  - All 9 status codes present in `$errorMessages`/`$logCategoryMap`
- These require actually invoking/simulating the guarded code path, not
  just reading source text — the gap that let the original bug ship
  undetected for 47 days.
- Existing three header tests (security headers, header-before-init
  ordering, SAMEORIGIN) continue passing against the single consolidated
  file, now with a 1-entry data provider instead of 3.

## Documentation Plan

- Update `error/500.php`'s own docblock (part of the implementation, not a
  separate doc) to describe the canonical multi-code handler.
- No wiki or ADR impact — this restores/reorganizes existing logging
  behavior rather than introducing new user-facing or architectural
  concepts.
