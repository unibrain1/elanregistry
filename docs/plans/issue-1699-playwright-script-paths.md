# Issue #1699: package.json playwright script paths reference non-existent .test.js files

**Branch:** `bug/1699-playwright-script-paths`
**Milestone:** `milestone/v2.29.4`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

- **Root cause:** the repo migrated Playwright spec files from a `.test.js`
  naming convention to `.spec.js`, but three `package.json` script entries
  were never updated to match: `playwright:security`, `playwright:maps`,
  `playwright:mobile`.
- **Testing gap:** no test or CI check exercises `npm run playwright:*`
  script strings themselves — they're plain shell strings in `package.json`,
  invisible to PHPUnit/PHPStan/ESLint. The rename PR(s) had no mechanism to
  catch a stale reference in a config file outside its own diff.
- **Preventive measure:** explicitly out of scope for this issue (see below,
  user-confirmed) — the issue's own "consider a guard" suggestion is
  optional, and no existing pre-commit/CI infrastructure exists to extend
  cheaply. Deferred rather than built ad hoc.

## Architecture & Design

Verified current file state:

- `tests/playwright/security.test.js` / `security.spec.js` — **neither
  exists**; the security suite lives only under `tests/playwright/security/`
  (7 spec files: account-enumeration, backup-operations-access,
  car-image-ownership, car-update-ownership, clickjacking,
  contact-owner-idor, datatables-xss).
- `tests/playwright/maps-charts.spec.js` — exists.
- `tests/playwright/mobile-responsive.spec.js` — exists.
- `tests/playwright/csp-validation.spec.js` — exists, already correct in
  `package.json`.

Fix `package.json`'s three broken script entries:

```diff
-"playwright:security": "playwright test tests/playwright/security.test.js tests/playwright/security/",
+"playwright:security": "playwright test tests/playwright/security/",
-"playwright:maps": "playwright test tests/playwright/maps-charts.test.js",
+"playwright:maps": "playwright test tests/playwright/maps-charts.spec.js",
-"playwright:mobile": "playwright test tests/playwright/mobile-responsive.test.js",
+"playwright:mobile": "playwright test tests/playwright/mobile-responsive.spec.js",
```

Note `playwright:security`'s fix diverges from the issue's own suggested
diff (which assumed a `security.spec.js` file exists) — confirmed with user
that dropping the missing argument entirely, keeping only the `security/`
directory reference, is correct since no such file exists under either
extension.

`CLAUDE.md` requires no changes — it only lists `npm run` command names in
prose, no file paths.

**Out of scope (user-confirmed):**

- Building a path-validation guard for `playwright:*` scripts — the issue
  phrases this as "Consider...", not an acceptance criterion; no existing
  hook/CI infrastructure to extend. Leave unbuilt; can be filed separately
  if wanted later.
- The stale comment `// tests/playwright/mobile-responsive.test.js` at
  `tests/playwright/mobile-responsive.spec.js:1` — cosmetic only, not
  executed, not in `package.json` (the only file this issue touches). Low
  severity, outside this PR's file scope per the containment matrix — leave
  as-is, not worth a separate issue.

## Implementation Checklist

- [x] Fix `playwright:security`, `playwright:maps`, `playwright:mobile`
      script strings in `package.json` (parallel-safe — single file, no
      other item touches it)
- [x] Run `npm run playwright:security`, `npm run playwright:maps`, `npm run
      playwright:mobile` locally (requires MAMP at localhost:9999) to confirm
      each resolves and runs real tests instead of erroring on a missing file
      — confirmed: 30/9/13 tests listed respectively, no "no tests found" errors
- [x] Run `composer check:docs` to confirm no doc drift flagged —
      "Documentation checks passed."

## Test Plan

No new automated test needed — this is a config-string fix, not application
logic. Verification is running the three corrected `npm run playwright:*`
commands locally and confirming each launches real Playwright tests (not a
"no tests found" error) per the issue's acceptance criteria.
