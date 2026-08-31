# Issue #1788: Make CAR_ID_STANDARD resolve dynamically in Playwright fixtures

**Branch:** `issue/1788-dynamic-car-id-standard-fixture`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Context

`tests/playwright/fixtures.js:5` hardcodes `CAR_ID_STANDARD = 1`. On this
machine's local MAMP DB snapshot, car id 1 doesn't exist (ids start at 3),
so every test that navigates to a URL built from `CAR_ID_STANDARD` either
hard-fails (most call sites) or silently `test.skip()`s
(`ajax-endpoints.spec.js`, which explicitly checks for lookup failure first).
Confirmed live: `SELECT id FROM cars ORDER BY id ASC LIMIT 10` returns
`3,4,5,6,7,8,9,10,11,12` on this snapshot — id 1 is genuinely absent, not a
config error.

Fix approach (user-selected): resolve `CAR_ID_STANDARD` from an environment
variable with a hardcoded fallback, matching the existing
`process.env.TEST_USERNAME || 'test@example.com'` pattern already used in
`tests/playwright/auth-helper.js:16,80`. A live-DB-lookup alternative was
considered and rejected — Explore confirmed no DB-query infrastructure
exists anywhere in the Playwright suite today (no `mysql`/`mysql2` client
wired in, no `global-setup.js`), so that path would be new infrastructure
work, not a drop-in fix, and is out of scope for this tech-debt issue.

Scope stays narrow: `fixtures.js`'s 3 sibling constants
(`CAR_ID_WITH_HISTORY=1091`, `CAR_ID_WITH_SPECIAL_CHARS=650`,
`CAR_ID_REDIRECT_TEST=100`) were confirmed live to still resolve correctly
on this snapshot — only `CAR_ID_STANDARD` needs the fix.

## UserSpice Integration

None — this is Playwright test-infrastructure only, no application code.

## Database & Security Considerations

None — no schema, auth, or CSRF changes. `.env.local` already holds
DB/`TEST_USERNAME`/`TEST_PASSWORD` values and is gitignored; adding one more
key (`CAR_ID_STANDARD`) to that same file follows the existing convention
and introduces no new sensitive-data handling.

## Architecture & Design

**`tests/playwright/fixtures.js`** — change line 5 from a literal to an
env-var-with-fallback expression, matching `auth-helper.js`'s exact style:

```js
const CAR_ID_STANDARD = Number(process.env.CAR_ID_STANDARD) || 1;
```

`Number(...)` coerces the env var (always a string when set) to match the
numeric type every call site expects (`String(CAR_ID_STANDARD)` is used at
several call sites specifically because the constant is numeric — Explore
confirmed no call site currently expects a string constant). The `|| 1`
fallback preserves current behavior for every developer/CI environment that
doesn't set the override — this is a strictly additive change, not a
default-behavior change.

**`.env.local`** (this machine only, gitignored) — add `CAR_ID_STANDARD=3`
so this developer's own snapshot immediately unblocks without touching
`fixtures.js`'s fallback value (which stays `1` for everyone else, since
Explore found no doc anywhere promising "car id 1" as the seeded contract —
`ENVIRONMENT.md` has no such claim, so `1` remains a reasonable default for
whichever environment(s) do seed it).

**Fixture-file header comment** (`fixtures.js:2-3`) — update to document the
new override mechanism, since the existing comment already warns "Values
must not change without verifying the referenced car/data still exists in
the test DB" — this is exactly the escape hatch that warning implies should
exist but didn't.

**No other file changes required.** Explore confirmed every one of the 7
call sites (`ui-consistency.spec.js`, `csp-validation.spec.js`,
`navigation.spec.js`, `ajax-endpoints.spec.js`, `car-edit-text-save.spec.js`,
`contact-owner-page.spec.js`, `e2e/not-logged-in.spec.js`) imports the
constant from `fixtures.js` and uses it directly — none re-derive or
hardcode their own copy of `1`. A single-file fix at the export covers every
caller.

**No `ENVIRONMENT.md` update required** — Explore confirmed this doc makes
no promise about car id 1 or any Playwright seed-data contract today, so
there's no stale claim to fix. A short new subsection documenting the
`CAR_ID_STANDARD` env-var override is optional polish, not a correctness
requirement; deferred as out of scope to keep this fix minimal.

## Implementation Checklist

- [x] Change `fixtures.js:5` to `Number(process.env.CAR_ID_STANDARD) || 1`,
      update the header comment (lines 2-3) to document the override
      mechanism — `tests/playwright/fixtures.js` — ESLint clean
- [x] Add `CAR_ID_STANDARD=3` to this machine's `.env.local` (gitignored,
      local-only — not part of the committed diff) — confirmed gitignored,
      not staged
- [x] Run the affected local Playwright spec(s) —
      `ajax-endpoints.spec.js`: transfer approve/deny + car-details-lookup
      tests no longer skip (18 passed, 1 intentional permanent skip, 2
      pre-existing unrelated failures confirmed via git-stash comparison —
      same DataTables/mapmarkers failures already tracked as #1765).
      `car-edit-text-save.spec.js`: 5/7 now pass (was hard-failing entirely
      before this fix); remaining 2 failures confirmed pre-existing/unrelated
      via git-stash comparison, filed as #1846 (triage, not root-caused).
- [x] PHPStan baseline hygiene: N/A — no PHP files touched, confirmed
      (`git status --porcelain` shows only `tests/playwright/fixtures.js`)
- [x] Run `senior-architect` review of the diff, address findings — 0
      findings across all severities; correctness verified for every
      input case (unset, valid numeric, invalid, "0", empty string)

## Test Plan

- No new automated test — this issue fixes test *infrastructure* itself.
  Verification is running the existing suite and observing previously
  skipping/failing specs now pass on this machine:
  - `npx playwright test tests/playwright/ajax-endpoints.spec.js` — the
    `process-car-details.php` lookup test and the transfer-approve/deny
    `describe` block's `beforeEach` should no longer skip
  - `npx playwright test tests/playwright/car-edit-text-save.spec.js` — was
    hard-failing (no skip guard) on this snapshot; should pass once
    `CAR_ID_STANDARD` resolves to a real car id
- Confirm the fallback value (`1`) is unchanged for any environment that
  doesn't set the override — this is implicit in the `|| 1` expression, no
  separate test needed, but worth stating explicitly as the invariant this
  change must not break (CI, and any other developer's differently-seeded
  snapshot that does happen to have id 1).

## Documentation Plan

- Update `fixtures.js`'s own header comment to document the env-var
  override (part of the implementation, not a separate doc change).
- No `ENVIRONMENT.md`, wiki, or ADR impact.
