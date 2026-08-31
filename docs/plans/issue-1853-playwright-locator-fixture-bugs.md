# Issue #1853: DataTables XSS + admin-cleanup Playwright tests have locator/fixture bugs (not app regressions)

**Branch:** `issue/1853-playwright-locator-fixture-bugs`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

Not applicable in the usual sense — no `bug` label, and the issue explicitly
states these are pre-existing test-side defects, not application
regressions. No production behavior is wrong; the tests fail (or, worse,
silently prove nothing) due to locator/fixture mistakes. Root causes below
per failure.

### 1. `admin-unverified-account-cleanup.spec.js:20` — strict-mode collision

**Root cause:** `page.locator('input[name="ac_threshold"]')` matches 4
elements — confirmed via `grep` on
`app/admin/includes/tab-account_cleanup.php`: one visible `<input
id="ac_threshold" name="ac_threshold">` (line 218) plus three `<input
type="hidden" name="ac_threshold">` duplicates mirrored into the delete/
restore forms (lines 316, 386, and a third). Only the visible input has an
`id` attribute — the hidden mirrors share the `name` but never carry an
`id`. Playwright's strict mode throws because `name`-attribute selectors
aren't unique here.

**Fix:** target by `id` instead of `name` — `page.locator('#ac_threshold')`
/ `page.locator('#acv_threshold')` — since `id` is guaranteed unique per
HTML spec and confirmed unique in this markup (grep shows `id=` only on the
two visible inputs, never on the hidden mirrors).

### 2. `navigation.spec.js:26` — stale assertion text

**Root cause:** `expect(page.locator('h1, h2, .card-header').first()).toContainText(/Car|Details|Information/)`
predates a UI change — the car details page's heading now renders the car's
actual identity (e.g. "1966 Lotus Elan S3 (FHC-preairflow)"), not a
generic "Car Details" label.

**Fix:** per the issue's own suggestion and the test's stated intent ("car
details page loads"), replace the literal-word assertion with a
structural check: the heading is non-empty. This also removes reliance on
wording that's free to change again as the page evolves.

### 3 & 4. `security/datatables-xss.spec.js:168,225` — synthetic row never renders (revised root cause)

**Original hypothesis (WRONG, disproven empirically during implementation):**
initially assumed the synthetic row landed on a non-visible page due to
sort/pagination, and that `table.search(<marker>).draw()` would force it
into view. **This does not work and cannot work** — both `#cartable`
instances (`car-list.js`, `factory-list.js`) are configured with
`serverSide: true`. Verified directly via a throwaway debug spec: with
1,590 real records loaded and confirmed via `table.page.info()`,
`table.row.add({...}).draw(false)` still produces `newRow.node() === null`
unconditionally. Reading DataTables' own source
(`node_modules/datatables.net/js/dataTables.js`, `recordsDisplay()`)
confirms why: server-side mode's rendering is driven entirely by
`ctx.recordsDisplay`/the AJAX response — locally-added rows via
`row.add()` are never rendered into the DOM at all, regardless of
page/search/sort state. This is fundamental to `serverSide: true`, not a
timing or pagination bug.

**Actual root cause:** the test design itself — "inject a row via
`row.add()`, then inspect its rendered DOM node" — is incompatible with a
server-side DataTable. It was likely written against an assumption that
held for a non-serverSide configuration and never actually worked once
`serverSide: true` was introduced (or was never verified to produce a
non-null node at all).

**Fix (revised):** the column `render` functions being tested
(`app/assets/js/car-list.js`'s id-column function and `textRender`;
`app/assets/js/factory-list.js`'s equivalents) are pure, side-effect-free
functions that take `(data, type, row)` and return an HTML string — they
require no DataTables row/table machinery to exercise. Call them directly
with the XSS payload as `data`, inspect the returned string (and, for the
`onerror` case, actually inject that string into a detached DOM element via
`document.createElement` + `innerHTML` to confirm whether the browser would
execute it) — no `row.add()`, no `.draw()`, no dependency on server data,
pagination, or table state at all. This is more direct (tests the actual
unit under test — the render function — rather than an entire
client-server round trip), faster, and cannot produce a vacuous `null`
result under any table state.

## UserSpice Integration

Not applicable — test-only change, no framework surface touched.

## Database & Security Considerations

No schema, auth, or production security-code changes. This PR touches only
Playwright test files. The two XSS tests being fixed are themselves
security regression tests (verifying `render: textRender`/`parseInt()`
guards against stored XSS) — fixing them so they actually execute their
check is itself a net security-coverage improvement, but no new
vulnerability surface or mitigation is introduced.

## Architecture & Design

Three independent, surgical fixes, one per file, no shared code changes
needed beyond the same search-based pattern applied twice in the same
`datatables-xss.spec.js` file. No new helpers required — all three fixes
are self-contained edits to existing test bodies.

**Alternative considered for #3/#4 (rejected):** iterating all DataTable
pages via `table.page.info().pages` and checking each — more code, more
brittle to page-size configuration changes, and the search-based approach
is the standard DataTables idiom for "guarantee a specific row is visible"
recommended by the library's own docs (`table.search()` runs before
`page()` in the DataTables pipeline).

## Implementation Checklist

- [x] Fix locator strict-mode collision — change
      `page.locator('input[name="ac_threshold"]')` /
      `page.locator('input[name="acv_threshold"]')` to
      `page.locator('#ac_threshold')` / `page.locator('#acv_threshold')` —
      `tests/playwright/admin-unverified-account-cleanup.spec.js`
      (parallel-safe)
- [x] Replace stale literal-word assertion with a structural non-empty
      heading check on the car details page test —
      `tests/playwright/navigation.spec.js` (parallel-safe)
- [x] Fix id-column XSS test: add `table.search(<unique-marker>).draw()`
      after row insertion, before the `rowNode`/`hasLink`/`hasImg` checks;
      clear search before row removal —
      `tests/playwright/security/datatables-xss.spec.js` (id-column test,
      not parallel-safe with the factory-table fix below — same file)
- [x] Fix factory-table color-column XSS test: same search-based fix
      pattern, using a collision-proof unique marker (not a plausible-real
      value like `serial: '0001'`) — `tests/playwright/security/datatables-xss.spec.js`
      (factory-table test, depends on: id-column fix, same file — apply
      sequentially, not concurrently)
- [x] Run all four fixed tests locally against MAMP, confirm each passes
      and the XSS/link/img assertions actually execute (not vacuously
      skipped via `null`) — all four pass; id-column and factory-table
      tests confirmed to actually exercise their render functions (verified
      via `renderedHtml` in the failure-message output, not just a boolean)
- [x] Run each fixed file's full suite
      (`admin-unverified-account-cleanup.spec.js`, `navigation.spec.js`,
      `security/datatables-xss.spec.js`) to confirm no regressions to
      other tests in those files — 4/4, 15/15, 12/12 all passed
- [x] PHPStan baseline hygiene: not applicable — no PHP files touched
- [x] Run `/security-review` — not required (test-only change to existing
      security-regression tests; no forms/SQL/auth production code
      touched)
- [x] Run `senior-architect` review of the diff, address findings — clean,
      no Blocking. Independently reran all 3 files (31/31 passing) and
      verified all 4 factual claims against source: `column().init()` is a
      legitimate public DataTables API (not an internal hack), the factory
      color column really does use `$.fn.dataTable.render.text()`
      (confirmed identical to what the test now calls), the `#ac_threshold`/
      `#acv_threshold` id-uniqueness claim holds against real markup, and
      calling render functions directly is a faithful invocation of
      production's actual render path (traced `getCellData()` →
      `column.dataGet()` → `column.renderer()`), not a weaker substitute —
      only the adjacent DataTables draw/DOM-insertion plumbing is no longer
      exercised by these two tests specifically, which remains covered by
      unmodified sibling tests in the same file. Two lightweight
      Recommendations addressed: added an inline comment clarifying the
      omitted `meta` argument; filed
      [#1862](https://github.com/elan-registry/registry/issues/1862) for
      documenting the `serverSide` DataTables testing trap (out of scope
      for this PR). Also independently confirmed the car-history table's
      own `row.add()`-based XSS test (lines 425+, not touched by this PR)
      does NOT share this bug — `car_details.js` has no `serverSide: true`,
      confirmed via grep, and that test already passes.

## Implementation Note: Fix Approach Changed Mid-Implementation

The Architecture & Design section's originally-planned search-based fix for
items #3/#4 (`table.search(<marker>).draw()`) was implemented first, then
**empirically disproven** — it still produced `null`/vacuous results.
Root-caused via a throwaway debug spec (not committed) plus reading
`node_modules/datatables.net/js/dataTables.js`: both `#cartable` instances
use `serverSide: true`, under which DataTables' rendering is driven
entirely by the AJAX response — `table.row.add()` can never produce a
renderable node, regardless of page/search/sort state, confirmed against
both an empty and a 1,590-row live table. See the corrected "Root cause"
subsection above (originally written as a hypothesis, now marked as
disproven) for the full account.

**Final fix actually implemented:** call each column's render function
directly — `table.column(<index>).init().render(...)` for the id column's
bespoke inline function, and `$.fn.dataTable.render.text().display(...)`
directly for the factory color column (which uses the same `textRender`
helper the file's pre-existing `render.text() escapes XSS payload` test
already exercises this way) — then inject the returned HTML string into a
detached DOM element to check for injection. No DataTables row/draw/server
machinery involved at all, so the check can never be vacuous under any
table state.

## Test Plan

- No new test files or PHPUnit involvement — four fixes within three
  existing Playwright spec files.
- Verification: run each fixed test individually first to confirm the
  specific assertion now executes meaningfully (not `null`/vacuous), then
  each file's full suite for regression, all against local MAMP.
- For the two XSS tests specifically: after the fix, deliberately verify
  the chosen search marker is actually unique against current fixture data
  (e.g., search the marker value against the full unfiltered table first
  to confirm exactly one match) — a marker that collides with a seeded row
  would silently reintroduce the same "checking the wrong row" failure
  mode the original bug had, just via `table.search()` matching more than
  one row instead of `row.add()` landing off-page.
