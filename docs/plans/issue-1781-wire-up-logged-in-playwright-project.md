# Issue #1781: e2e/factory-registry-link.spec.js and other 'logged-in' project tests never run — no such Playwright project exists

**Branch:** `bug/1781-wire-up-logged-in-playwright-project`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

**Root cause:** `tests/playwright/e2e/factory-registry-link.spec.js` and
`tests/playwright/e2e/logged-in.spec.js` gate every test behind
`testInfo.project.name !== 'logged-in'` → skip. No Playwright config defines
a project literally named `logged-in` that these files' `testMatch` can
reach at the same time:

- `playwright.config.js` (local, `npm run playwright:test`) — `testIgnore:
  '**/e2e/**'` excludes the whole `e2e/` directory outright. Neither spec is
  ever collected here, regardless of project name.
- `playwright.config.prod.js` / `playwright.config.test.js` (deployed,
  `npm run test:e2e` / `test:e2e:test`) — these *do* define a `logged-in`
  project (conditionally, only `if (hasAuthFile)`), but each project's
  `testMatch` is a literal filename regex: `/.*logged-in\.spec\.js/` and
  `/.*not-logged-in\.spec\.js/`. `logged-in.spec.js` matches and can run (if
  a `.auth/user.json`/`user-test.json` storageState file happens to exist
  locally on the machine running the command). `factory-registry-link.spec.js`
  matches neither regex — it is excluded from these configs regardless of
  auth file presence.

Net effect: `factory-registry-link.spec.js` has never executed under any
config, ever. `logged-in.spec.js` only executes when a developer has
manually run `scripts/playwright-auth-setup.js` (which requires solving a
CAPTCHA by hand against the live prod login) and kept the resulting auth
file around — not something CI does, since no GitHub Actions workflow
invokes Playwright at all (confirmed: no `playwright`/`test:e2e` references
in `.github/workflows/*.yml`). Both `test:e2e`/`test:e2e:test` are
developer-run-manually commands per this project's own docs, not gated in
CI.

**Why it reached production silently:** there is no assertion anywhere that
a given spec file is reachable by at least one project in at least one
config. A `testInfo.skip()` produces a green "skipped" result indistinguishable
at a glance from "ran, nothing applicable." `npx playwright test
factory-registry-link.spec.js` under the local config reports "0 tests"
(excluded by `testIgnore`), which also doesn't visually scream "dead code" —
it looks like "there are no matching files," which is easy to misread as
"wrong path typed" rather than "this file is structurally unreachable."

**Testing gap:** no test/check asserts "every spec file with a
`testInfo.project.name` gate has at least one project name in at least one
config that satisfies it." This is a config-authoring correctness property,
not something a spec file's own test bodies could ever catch (the spec
never runs, so it can't assert anything about itself).

**Preventive measure:** not adding a general lint/test for this — three
Playwright config files with hand-authored project/testMatch pairs is a
narrow surface, and a bespoke checker is disproportionate to the risk here.
Instead this fix wires up the actually-intended structure (documented
below) so the specific reachability holes close. Noted as accepted residual
risk: a future spec file could reintroduce the same class of bug by copying
the `testInfo.project.name !== 'X'` gating pattern without checking `X` is
defined and reachable everywhere it's expected to run. No automated guard
is added for this in this issue.

## UserSpice Integration

Not applicable — this is test-tooling-only (Playwright config + a new setup
spec), no UserSpice framework surface touched.

## Database & Security Considerations

- No schema, trigger, or audit-trail changes.
- No auth/session/CSRF code changes — this only adds a **test-only**
  Playwright `storageState` auth flow (industry-standard Playwright pattern:
  a `setup` project logs in once, other projects reuse the resulting
  storage state via `dependencies`), scoped entirely to
  `tests/playwright/e2e/` and `.gitignore`d output
  (`tests/playwright/.auth/`, already ignored).
- No sensitive/GDPR-relevant data introduced. `TEST_USERNAME`/`TEST_PASSWORD`
  already exist in the developer's own gitignored `.env.local` and are
  already used by numerous other local specs (`ajax-endpoints.spec.js`,
  `functionality.spec.js`, `length-validation.spec.js`, security specs,
  etc.) via the same env-var convention — this plan adds no new secret or
  credential-handling surface, only a new consumer of an existing one.
- `logged-in.spec.js`'s "should be able to update car information" test
  performs a real mutation (submits a car comment field) against whatever
  car is reached via `/users/account.php` → "Update Car" for the
  `TEST_USERNAME` account. Verified this is **not** an accumulating/dirty-data
  risk: the test writes a single fresh timestamped string into one `comment`
  column and immediately re-reads the same field to assert the round-trip —
  it overwrites the same value on every run, it does not append rows or grow
  unbounded state. No cleanup step is needed.
- `screenshots/car-update-result.png` (written by the same test) is already
  covered by the root `.gitignore`'s `screenshots/` entry — confirmed, no
  dirty-working-tree risk from running this locally.

## Architecture & Design

**Decisions already made with the user (do not revisit):**

1. Add a `logged-in` project to the **local** `playwright.config.js` only.
   Do not restructure the existing `chromium`/`Mobile Chrome` projects or
   migrate other local specs into a logged-in/not-logged-in split — narrow,
   targeted fix.
2. Also fix `testMatch` in `playwright.config.prod.js` and
   `playwright.config.test.js` so `factory-registry-link.spec.js` is
   reachable there too (issue's acceptance criteria explicitly asks to
   verify the dead tests "actually run," which isn't satisfied by a
   local-only fix).
3. Out of scope: a dev-environment E2E config (`playwright.config.dev.js`
   targeting local MAMP or `dev.elanregistry.org` for the deployed-style
   `test:e2e` flow) — filed separately as
   [#1858](https://github.com/elan-registry/registry/issues/1858), since it
   needs its own scoping decisions (canonical dev baseURL, credential
   source) unrelated to this bug fix.

**Local auth setup mechanism — Playwright `setup` project +
`dependencies`, not a standalone script.**

`scripts/playwright-auth-setup.js` (used for prod) is the right shape for
its own job — CAPTCHA forces a human in the loop, so a one-off manually-run
script makes sense there. Local MAMP has no CAPTCHA and should be able to
run unattended, so it uses Playwright's built-in project-dependency
pattern instead, which is the modern recommended idiom for this exact
scenario and avoids inventing a second bespoke auth script:

- New file `tests/playwright/e2e/auth.setup.js`: calls the existing
  `login()` helper from `tests/playwright/auth-helper.js` against the local
  `baseURL` using `process.env.TEST_USERNAME`/`TEST_PASSWORD`, then
  `page.context().storageState({ path: 'tests/playwright/.auth/user.json' })`.
- `playwright.config.js` gains:
  - A `setup` project: `testMatch: /.*\.setup\.js/` (or an explicit
    `testMatch: 'e2e/auth.setup.js'`), running under `chromium`.
  - A `logged-in` project: `testMatch: /.*logged-in\.spec\.js|.*factory-registry-link\.spec\.js/`
    (see below), `dependencies: ['setup']`,
    `use: { storageState: 'tests/playwright/.auth/user.json' }`.
  - Both new projects must **not** run when `TEST_USERNAME`/`TEST_PASSWORD`
    are unset, so `npm run playwright:test` doesn't hard-fail for a
    contributor without local credentials configured — mirrors the existing
    `if (!process.env.TEST_USERNAME || !process.env.TEST_PASSWORD) test.skip(...)`
    convention used throughout the rest of the local suite. Implementation:
    `auth.setup.js` itself calls `test.skip()` when the env vars are absent
    (skip propagates as a soft, green "skipped" result rather than a setup
    failure that would cascade to every dependent test in `logged-in`).
  - `testIgnore: '**/e2e/**'` on the *existing* top-level config needs to
    stop blanket-excluding `e2e/` now that specific e2e specs are meant to
    run under the new `logged-in`/`setup` projects. Scope this precisely: add
    `testMatch` to each existing project (`chromium`, `Mobile Chrome`) instead
    of removing `testIgnore` outright, so those two projects' existing
    behavior (never touching `e2e/`) is unchanged, and only the new `setup`/
    `logged-in` projects reach into `e2e/`.

**`testMatch` fix for `factory-registry-link.spec.js` reachability
(prod/test configs + new local `logged-in` project):**

Broaden the regex from matching only literal `*logged-in.spec.js` filenames
to also match `factory-registry-link.spec.js`, since it uses the identical
`testInfo.project.name !== 'logged-in'` gate and is intended to run
alongside `logged-in.spec.js`. Chosen approach: an explicit alternation
(`/.*(logged-in|factory-registry-link)\.spec\.js/`) rather than a rename —
renaming `factory-registry-link.spec.js` to something matching
`*logged-in.spec.js` was considered but rejected: the filename correctly
describes what the spec covers (Factory page's Registry Link feature), and
folding "logged-in" into the name would make an already-long filename
worse for a property (auth requirement) that's better expressed via the
config's `testMatch` than via naming convention. Applied identically to
`playwright.config.prod.js`, `playwright.config.test.js`, and the new local
`logged-in` project so the three configs use the same reachability rule.

**Alternative considered and rejected:** matching by directory or a shared
naming prefix (e.g., moving both files under `e2e/logged-in/`) — larger
structural change than this bug warrants, and the issue's suggested fix
explicitly lists "wire up a real `logged-in` project" or "replace the gate
with a runtime auth check" as the two options; a directory reorg is neither.

## Triaged Finding (discovered during checklist re-run)

Running `logged-in.spec.js`/`factory-registry-link.spec.js` locally for the
first time (they previously never executed under any config) surfaced 16/19
failures via `.dataTables_wrapper` timeouts, root-caused to leading-slash
`page.goto('/app/...')` calls — the identical bug diagnosed and resolved in
closed issue #881 (leading-slash `goto()` discards the local baseURL's
`/ElanRegistry/Registry/` path prefix per the WHATWG URL spec, resolving to
`http://localhost:9999/app/...` → 404). Both spec files were authored only
against the deployed configs (root-path `baseURL`), so this was invisible
until they became locally reachable via this issue's fix.

**Triage: in-scope files (both specs are this issue's direct subject),
high severity (blocks core acceptance criteria) → fixed in this PR**, per
CLAUDE.md's containment+severity matrix. Applied #881's resolved Option B
pattern (relative `goto()` calls, no leading slash — same convention
already used by `auth-helper.js`'s `login()`).

## Implementation Checklist

- [x] Create `tests/playwright/e2e/auth.setup.js` — logs in via
      `auth-helper.js`'s `login()` using `TEST_USERNAME`/`TEST_PASSWORD`
      against the local `baseURL`, saves storageState to
      `tests/playwright/.auth/user.json`; calls `test.skip()` when the env
      vars are unset — `tests/playwright/e2e/auth.setup.js` (new file)
- [x] Add `testMatch`/`testIgnore` to the existing `chromium` and
      `Mobile Chrome` projects in `playwright.config.js` so blanket `e2e/`
      exclusion is preserved for those two projects specifically, replacing
      the top-level `testIgnore: '**/e2e/**'` — `playwright.config.js`
      (depends on: auth.setup.js existing, so testMatch patterns can
      reference it)
- [x] Add `setup` and `logged-in` projects to `playwright.config.js`
      (`logged-in` depends on `setup`, uses the saved storageState,
      `testMatch` covers both `logged-in.spec.js` and
      `factory-registry-link.spec.js`) — `playwright.config.js` (depends on:
      previous item, same file). **Post-implementation fix:** the regex as
      originally specified in this plan
      (`/.*(logged-in|factory-registry-link)\.spec\.js/`) substring-matches
      `not-logged-in.spec.js` too (since "logged-in" is a substring of
      "not-logged-in"), which would pull `not-logged-in.spec.js` into the
      `logged-in` project's listing — a live instance of the exact class of
      bug this issue exists to fix. Corrected in all three configs
      (`playwright.config.js`, `.prod.js`, `.test.js`) to
      `/(?:^|\/)(logged-in|factory-registry-link)\.spec\.js$/`, which
      anchors on the full filename rather than substring-matching. Verified
      via `npx playwright test --list --config=playwright.config.js
      --project=logged-in`: lists exactly `factory-registry-link.spec.js`
      (7 tests) + `logged-in.spec.js` (15 tests), no `not-logged-in.spec.js`
      entries.
- [x] Broaden `testMatch` for the `logged-in` project in
      `playwright.config.prod.js` to include
      `factory-registry-link.spec.js` — `playwright.config.prod.js`
      (parallel-safe)
- [x] Broaden `testMatch` for the `logged-in` project in
      `playwright.config.test.js` to include
      `factory-registry-link.spec.js` — `playwright.config.test.js`
      (parallel-safe)
- [x] Fix leading-slash `page.goto()` calls in
      `tests/playwright/e2e/factory-registry-link.spec.js` (9 call sites)
      and `tests/playwright/e2e/logged-in.spec.js` (2 call sites; the
      remaining `goto(path)`/`goto(linkPath)` calls use runtime path
      variables already documented in a `pages` array as `/`-prefixed
      strings — see Architecture note) to relative form, matching #881's
      resolved pattern — `tests/playwright/e2e/factory-registry-link.spec.js`,
      `tests/playwright/e2e/logged-in.spec.js`
- [x] Run `npm run playwright:test -- tests/playwright/e2e/logged-in.spec.js
      tests/playwright/e2e/factory-registry-link.spec.js` locally (or
      equivalent project-scoped invocation) and confirm tests actually
      execute (not skipped/0-collected) and pass against local MAMP.
      **Additional fixes required beyond the original checklist** (all
      discovered by actually running these files locally for the first
      time — see Triaged Finding section above for the goto()/regex
      fixes; three more surfaced after that):
      - `factory-registry-link.spec.js`: 7 call sites used the DataTables
        1.x-only selector `.dataTables_wrapper`; the app now renders
        DataTables 2.x markup. Replaced with `auth-helper.js`'s existing
        `waitForDataTables()` helper, which already handles both versions.
      - `logged-in.spec.js` menu test: locators used `nav a`/`header a`,
        but `file_nav_custom.php`'s menu is a bare `<ul class="us_menu">`
        with no `<nav>`/`<header>` wrapper. Changed to `.us_menu a`.
        Separately, "Feedback" and "Account" links live inside the
        collapsed username dropdown (`.us_sub-menu`) — changed their
        assertions from `toBeVisible()` to `toBeAttached()`, matching this
        same file's existing convention for other dropdown-only items
        (`Identification Guide`, `Production Records`, `Reference Library`).
      - `logged-in.spec.js` car-update test: `TEST_USERNAME` has zero
        registered cars locally, so account.php's per-car "Update Car"
        button never renders. Added `test.skip()` when no such button is
        present, matching the "skip rather than assume" convention already
        used in `factory-registry-link.spec.js` for fixture-dependent
        tests.
      - `logged-in.spec.js` internal-links test: the downloadable-file HEAD
        check hardcoded `baseURL = 'https://elanregistry.org'` regardless
        of which config ran the test, so locally it 404'd against a
        nonsensical prod URL. Changed to resolve each scraped link against
        `page.url()` (the actual running environment), removing the
        hardcoded prod assumption.
      Final result: `logged-in` project — 21 passed, 1 skipped (documented
      fixture gap), 0 failed.
- [x] Run `npm run playwright:test` (full local suite) to confirm the
      `chromium`/`Mobile Chrome` projects' existing behavior is unchanged
      (no new specs picked up, no regressions from the `testIgnore` →
      `testMatch` swap). Verified via `--list`: `chromium` 236 tests/35
      files, `Mobile Chrome` 13 tests/1 file — matches pre-change baseline,
      no `e2e/` files present in either.
- [x] Update `CLAUDE.md`'s Playwright command list / relevant docs if the
      new `setup`/`logged-in` local projects change how
      `npm run playwright:test` behaves for contributors without
      `TEST_USERNAME`/`TEST_PASSWORD` set (confirm graceful skip, document
      if needed) — `CLAUDE.md` (added a note under `npm run playwright:test`
      describing the new logged-in e2e project and its graceful skip)
- [x] PHPStan baseline hygiene: not applicable — no PHP files touched
      (confirmed via `git status --short`: only `.js` config/spec files and
      `CLAUDE.md`)
- [x] Run `/security-review` — not required (no forms/SQL/auth production
      code touched; test-only storageState handling of already-existing
      local dev credentials). Skip per CLAUDE.md's own guidance ("Run
      /security-review when changes touch forms, SQL queries, auth, or
      user input" — this touches none of those in application code).
- [x] Run `senior-architect` review of the diff, address findings — clean
      review, no blockers. Verified in Node that the regex fix correctly
      excludes `not-logged-in.spec.js` while matching both target files;
      confirmed no credential/security issues (`.auth/`, `.env*` all
      gitignored); confirmed all 6 spec-file fixes are genuine root-cause
      fixes against real markup/helpers, not workarounds. One adjacent,
      out-of-scope finding (`toBeDefined()` no-op assertion on the
      "Reference" dropdown items check) filed separately as
      [#1859](https://github.com/elan-registry/registry/issues/1859).
- [x] Ran `/review-pr` (code-reviewer + pr-test-analyzer in parallel) before
      pushing. `pr-test-analyzer` found one genuine **Blocking** issue:
      `auth.setup.js`'s `test.skip()` when `TEST_USERNAME`/`TEST_PASSWORD`
      are unset does not prevent the dependent `logged-in` project from
      running — Playwright's `dependencies: ['setup']` only blocks
      dependents on setup *failure*, not *skip*, so a stale
      `tests/playwright/.auth/user.json` from a prior run would be silently
      reused, running tests under a possibly-expired session instead of
      skipping. Confirmed reproducible (a stale file was present on disk).
      **Fixed:** `auth.setup.js` now `fs.rmSync(authFile, { force: true })`
      before the skip. Verified by temporarily disabling `.env.local`
      entirely (backed up first, restored after, diffed identical) — stale
      file confirmed deleted, setup test confirmed skipped. Re-ran the full
      `logged-in` project afterward with real credentials restored:
      21 passed, 1 skipped, 0 failed — unchanged.
      Also flagged (Recommendation, addressed): the `logged-in` project's
      `testMatch` is an exact-filename allowlist — any future authenticated
      spec added under `e2e/` needs to be added to the regex explicitly or
      it will be silently unreachable (same failure class as this issue).
      Added an inline comment in `playwright.config.js` documenting this
      trade-off and the required action for future spec authors, rather
      than broadening the match (a directory/suffix-based match risks
      re-capturing `not-logged-in.spec.js`, the exact bug the anchoring fix
      just resolved).
      Also independently reconfirmed the `toBeDefined()` finding already
      tracked as #1859 — no new issue needed.

## Test Plan

- **Type:** Playwright config + a new setup spec only. No PHPUnit
  involvement (no PHP changes).
- **Auth mechanism:** Playwright's built-in `setup` project +
  `dependencies` + `storageState`, not a standalone script — chosen over
  extending `scripts/playwright-auth-setup.js` because that script's design
  (headed browser, manual CAPTCHA solve, 5-minute wait) exists specifically
  to handle prod's CAPTCHA and is unsuitable for repeated local/CI-style
  runs; local MAMP has no CAPTCHA and should authenticate unattended.
- **Graceful skip:** contributors without `TEST_USERNAME`/`TEST_PASSWORD`
  in `.env.local` must see the new `setup`/`logged-in` projects skip
  cleanly (matching the established local-suite convention), not fail the
  overall `npm run playwright:test` run.
- **Mutation safety:** confirmed `logged-in.spec.js`'s car-update test
  overwrites a single field idempotently (no accumulating rows) and its
  screenshot output is gitignored — no additional test-hygiene changes
  needed.
- **Fixture-dependent skips:** `factory-registry-link.spec.js`'s
  matched-chassis/pagination tests already `test.skip()` themselves when
  fixture data is absent (default local provisioning doesn't run
  `--full`). This is correct existing behavior — no changes needed; the
  fix here only makes these tests reachable, their internal skip logic
  already handles the "insufficient fixture data" case appropriately.
- **Verification:** run both affected spec files directly to confirm
  non-zero executed-test counts (previously: 0 collected / all skipped),
  then the full local suite to confirm no regression to the untouched
  `chromium`/`Mobile Chrome` projects.
