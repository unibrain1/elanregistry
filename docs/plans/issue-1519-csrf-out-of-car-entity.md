# Issue #1519: refactor: move Token::check() CSRF validation out of the Car entity

**Branch:** `bug/1448-car-update-clear-fields` (continuing — combined PR per
sprint plan, see `Plans/sprints/v2.29.5.md`: #1448 → #1653 → #1519 land as
one PR)
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented, reviewed — pending commit/PR

## Context

`Car::create()`/`update()` (`usersc/classes/Car/Car.php:159-163, 210-215`)
each call `Token::check($fields['token'])` internally, throwing
`CarCreationException`/`CarValidationException` on failure. `save.php`
already validates CSRF at the endpoint boundary
(`app/api/cars/save.php:66-71`) before either method can be reached — that
check calls `ApiResponse::forbidden(...)->send()`, which has a `never`
return type, terminating the request on failure. `save.php:394-395` then
re-reads the token via `Input::get('csrf')` a second time solely to smuggle
it into `$cardetails['token']` so `Car`'s internal check passes — pure
redundancy with no security benefit, since the value's already been proven
valid by the time it's re-checked.

CSRF enforcement living inside the entity is enforcement someone can delete
by accident during a future refactor without anyone noticing the endpoint
lost its guard — the entity boundary isn't where a reviewer expects to look
for it. Moving it to the endpoint-only removes the redundant re-validation
and the smuggling code, and gives the still-open guardrail issue (#1465's
"forbid `Token::check(` inside `usersc/classes/`" Semgrep rule) something
real to enforce afterward — but that rule itself is cloud-managed (Semgrep
GitHub App Managed Scan, not committed to this repo) and confirmed
out-of-scope for this PR by the user.

**Pre-existing gap surfaced during research, folded into this issue's scope
(triaged and confirmed with user — in-scope/High per the containment +
severity matrix, since deleting the two Car-level CSRF tests below removes
the last, indirect proof this worked):** `save.php`'s own endpoint-level
CSRF check — the thing that makes removing `Car`'s internal check safe in
the first place — has **no dedicated test coverage** for the `addCar`/
`updateCar` actions today. `CarActionsSaveWiringTest.php`'s own docblock
explicitly states it is "not a wiring audit of save.php's auth, CSRF and
method guards." The existing browser-level CSRF-failure pattern
(`tests/playwright/ajax-endpoints.spec.js`) only exercises a different
endpoint (`chassis-availability.php`). This issue adds real endpoint-level
coverage rather than just deleting the two `Car`-internal tests and leaving
nothing behind.

## Safety Verification (confirmed via code, not assumed)

- **Exactly two production callers** of `Car::create()`/`update()` exist in
  the entire codebase: `app/api/cars/save.php:297` (`updateCar()`) and
  `:323` (`addCar()`). Both are reachable only through `save.php`'s
  `switch ($action)` block, which is unconditionally gated by the
  endpoint-level CSRF check at lines 66-71 — confirmed no caller anywhere
  bypasses this and relies solely on `Car`'s internal check.
- **`Token::check()` is stateless** (`users/classes/Token.php:39-59`) — pure
  format validation + `hash_equals()` comparison against the session-stored
  token, no rotation/consumption side effect. Calling it twice per request
  today is redundant, not a bug in itself, and removing the second call
  changes nothing about what gets validated — only removes duplicate work.
- **`Car::delete()`** (`Car.php:444-448`) has an analogous internal
  `Token::check()` call but is **not named in #1519's scope** (issue text
  only says `create()`/`update()`) — left untouched.

## Database & Security Considerations

**This is the security-relevant part of the change — verified, not assumed:**

- Removing the internal check does not weaken CSRF protection: the endpoint
  check already runs first and unconditionally for both real callers (see
  above), and it terminates the request on failure before `Car` is ever
  reached. No code path today reaches `Car::create()`/`update()` without
  having already passed `save.php`'s check.
- **New consideration surfaced during research, not in the issue text:**
  `$fields['token']` is currently `unset()` at `Car.php:163`/`215`
  immediately after the internal check succeeds — before validation. If the
  check block is deleted outright without also removing that `unset()`'s
  *purpose* (stripping `token` before it reaches persistence), a stray
  `token` key would flow into `CarValidator::validateAndSanitizeFields()`.
  Its `default:` case (confirmed at `CarValidator.php:259-264`) does **not**
  drop unrecognized keys — it *keeps* any non-empty/non-null value under an
  unknown key. `create()` then passes fields straight to
  `CarRepository::insertCar()` with **no allowlist** (confirmed in #1448's
  research — `create()` has no `$validCarFields` intersection unlike
  `update()`). The `cars` table has no `token` column (confirmed via
  schema — the only `token`-named column in the baseline migration is
  `users.security_token`, unrelated). Left unhandled, this becomes an
  INSERT-column-mismatch failure on `create()` (and `update()`'s existing
  `$validCarFields` allowlist would silently drop it there, which is fine
  but inconsistent with `create()`'s path).
- **This means the fix is not "just delete the if block."** The `unset()`
  of `token` must be preserved (or equivalently, `save.php` must stop
  sending `token` in `$cardetails` at all) even though the *validation*
  moves out. See Architecture & Design below for the chosen approach.
- No schema, migration, or audit-trail changes needed.

## Architecture & Design

**Chosen approach:** Remove the CSRF *validation* logic from `Car::create()`
and `Car::update()`, but keep each method stripping a `token` key from
`$fields` if present (defense-in-depth against any stray key reaching
persistence, and it costs nothing) — replace the `if (...) { throw ...}`
block with a plain `unset($fields['token']);`, no conditional, no
`Token::check()` call, no exception path. This is the minimal change that:

1. Fully removes `Token::check()` from inside `usersc/classes/` (satisfies
   #1519's stated goal and the not-yet-added Semgrep rule's intent).
2. Doesn't introduce the INSERT-column-mismatch/allowlist-inconsistency risk
   identified above.
3. Requires no change to `save.php`'s `buildCarDetails()` — it can keep
   sending `token` in `$cardetails` harmlessly (still gets stripped), though
   the smuggling comment becomes misleading and should be updated to explain
   *why* `token` is still being passed (harmless legacy shape, not a security
   requirement) or removed if `save.php` stops sending it — decided below.

**Alternative considered and rejected:** Have `save.php` stop sending
`token` in `$cardetails` at all (fully removing the smuggling line), and
have `Car::create()`/`update()` do nothing with a `token` key at all (no
`unset()`, rely on `CarValidator`'s `default:` case never seeing it because
it's never sent). Rejected because: `create()`'s downstream `insertCar()`
has no allowlist, so this depends entirely on **every** caller remembering
never to send `token` — a silent, easy-to-violate invariant, exactly the
kind of implicit contract issue #1519 itself is trying to eliminate at the
CSRF-check layer. Keeping a defensive `unset()` in `Car` costs one line and
removes that fragility regardless of what callers send.

**`save.php:394-395` (the smuggling line):** Remove it. Once `Car` no longer
requires a `token` key to validate, there's no reason for `buildCarDetails()`
to add one — removing it makes the intent honest (stop pretending the entity
needs this) rather than leaving a harmless-but-confusing artifact.

## Bug Escape Analysis

Not applicable — this is a `refactor`-labeled issue, not `bug`. No escape
analysis section per `/start-issue`'s Step 7.2.5 (bug-only).

## UserSpice Integration

None — `Token::check()`/`Token::generate()` remain UserSpice framework
calls used the same way at the endpoint layer; only their *location* within
`usersc/classes/Car/Car.php` changes (removed, not replaced).

## Implementation Checklist

- [x] Replace the CSRF check-and-throw block in `Car::create()` with a bare
      `unset($fields['token']);` (no conditional, no `Token::check()`, no
      exception) — `usersc/classes/Car/Car.php` (parallel-safe)
- [x] Replace the CSRF check-and-throw block in `Car::update()` with a bare
      `unset($fields['token']);` (same shape) — `usersc/classes/Car/Car.php`
      (same file as prior item)
- [x] Remove the `$cardetails['token'] = Input::get('csrf');` smuggling line
      and its now-inaccurate comment — `app/api/cars/save.php`
      (parallel-safe, different file)
- [x] Repurpose `testCreateCarFailsWithInvalidCsrfToken()` and
      `testUpdateCarFailsWithInvalidCsrfToken()` — renamed to
      `testCreateCarIgnoresInvalidTokenField()`/
      `testUpdateCarIgnoresInvalidTokenField()`, now assert success + real
      persistence with an invalid token —
      `tests/integration/database/CarDatabaseOperationsTest.php`
- [x] Audit the ~10 other tests passing `'token' => Token::generate()` —
      confirmed via full run (43 tests, 117 assertions, all pass), no
      changes needed
- [x] **Add new Playwright CSRF-endpoint test for `addCar`/`updateCar`** —
      4 tests added to `tests/playwright/ajax-endpoints.spec.js`: invalid
      token rejected on `addCar`, invalid token rejected on `updateCar`,
      missing token rejected on `addCar`, and a control test proving a real
      token reaches past the CSRF check (distinguishing it from some other
      guard). All 4 pass. 3 pre-existing unrelated failures in the same
      file confirmed via `git stash` to predate this branch.
- [x] Run `composer test:medium`, verify pass — 1688 unit + 92 integration,
      all passing
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries — none found, PHPStan clean on all 3
      touched PHP files
- [x] Run `/security-review` — mandatory given this removes a security
      check from a class, even though the endpoint check makes it
      redundant; address Critical/High — **0 Critical/High/Medium.** Fixed
      one Low finding: `usersc/plugins/ai_prompts/custom_prompts/elanregistry_classes.md.php`
      had a stale example showing `create()`/`update()` accepting a
      `csrf`/`token` field, which could mislead a future contributor (or
      AI assistant reading this required-context file) into building a new
      caller that assumes CSRF is entity-validated. Updated the example and
      added a note that CSRF is caller-validated only.
- [x] Run `senior-architect` review of the diff, address findings — **Go**,
      0 Critical/High/Medium. Fixed the one actionable Low: updated the
      stale `csrf`/`Token::generate()` usage example in
      `docs/development/CLASSES.md`'s `Car` "Common Usage" section (lines
      ~90-113) to match #1519's new behavior. Deliberately did NOT touch
      the separate generic CRUD template further down (~862-878) showing
      an inconsistent `create()`-still-has-inline-CSRF-check vs.
      `update()`-caller-validates split — that template governs all
      domain classes, not just `Car`, so fixing it is out of this issue's
      scope; filed
      [#1812](https://github.com/elan-registry/registry/issues/1812) to
      track it as a deliberate follow-up.
- [N/A — deferred to /commit-push-pr] Note in the PR description: the
      corresponding Semgrep guardrail rule (forbid `Token::check(` inside
      `usersc/classes/`, from #1465) is cloud-managed and out of this
      repo's/PR's scope — flag as a follow-up for whoever manages the
      Semgrep ruleset. Also note #1812 (CLASSES.md CRUD template
      inconsistency) as a related follow-up filed during this issue.

## Test Plan

- Repurpose the two tests directly asserting `Car`'s internal CSRF-failure
  behavior to assert the new no-op-on-bad-token behavior (see checklist).
- Verify existing tests that pass a `token` field just to satisfy the old
  internal check still pass unchanged (they should — the field is now
  silently stripped rather than checked).
- **New: Playwright test(s) proving `save.php`'s endpoint-level CSRF check
  actually rejects a bad/missing token for `action=addCar` and
  `action=updateCar`** — this becomes the *only* layer proving CSRF is
  enforced for these two actions once `Car`'s internal check is gone, so
  this is not optional polish; it's the safety net the refactor depends on.
  Follow `ajax-endpoints.spec.js`'s existing `getCsrfFromSettingsPage()` +
  tampered-token + assert-403 pattern.
- No new positive-path tests needed — behavior for valid requests is
  unchanged.

## Documentation Plan

None — no public API/schema/user-facing change. `docs/development/CLASSES.md`'s
CRUD template (confirmed inconsistent: `create()`'s template still shows an
inline CSRF-check step, `update()`'s already says "CSRF is validated by the
caller") should arguably be corrected to reflect the now-consistent
endpoint-only convention, but that's a documentation-consistency nit outside
this issue's explicit scope — flagging here rather than silently fixing or
silently ignoring it.
