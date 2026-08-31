# Issue #1854: length-validation.spec.js sends JSON body, never reaches PHP $_POST validation (all 24 tests vacuous)

**Branch:** `issue/1854-length-validation-json-vs-form`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR (all checklist items complete)

## Bug Escape Analysis

**Root cause (test-side, as diagnosed in the issue):** every request in
`tests/playwright/length-validation.spec.js` used Playwright's `data: {...}`
option, which serializes as `application/json` by default — but both
target endpoints (`chassis-availability.php`, `transfer-request.php`) read
input via UserSpice's `Input::existsPost()` → `!empty($_POST)`, and PHP
never populates `$_POST` for a raw JSON body. Confirmed: fixed by changing
every `data: {...}` to `form: {...}` (24 call sites), which Playwright
form-encodes as `application/x-www-form-urlencoded`.

**Second root cause, discovered during implementation (application-side,
not test-side):** fixing the JSON/form issue alone was not sufficient.
`transfer-request.php` throws `CarTransferException('specific message')`
with only the constructor's first argument (the *technical* message) for
every length-validation failure (and, on inspection, for every other
`throw new CarTransferException(...)` in the file — 16 total). Per
`ElanRegistryException::__construct()`, `$userMessage` defaults to
`static::getDefaultUserMessage()` when not explicitly passed —
`CarTransferException`'s default is the generic "Unable to transfer the
car. Please try again." This means `getUserMessage()` (what
`ApiResponse::error($e->getUserMessage(), 400)` actually sends to the
client) **never returned the specific validation text** for any of these
16 throw sites — confirmed empirically via a direct authenticated POST
with `form:`-encoded data, which returned the generic fallback instead of
the expected chassis-length message. Only 2 pre-existing throws in the
same file already used the correct `CarTransferException::withUserMessage
($technicalMessage, $userMessage)` factory (an established pattern used
extensively elsewhere in the codebase, e.g. `usersc/classes/Owner.php`).

**Why it reached this state:** the JSON/form bug meant these tests never
executed real requests against `$_POST`-based validation at all — so the
`CarTransferException` message bug was never exercised or caught either.
Both bugs were latent and mutually hiding: the test bug prevented the
exception bug from ever being tested, and even a syntactically-correct
test would have failed against the exception bug had the test bug not
existed first.

**Testing gap:** no test previously exercised the actual HTTP response
body from a form-encoded request to either endpoint with realistic
boundary-value data — the entire file's assertions were checking
properties of a generic "malformed request" response, satisfied by any
JSON body.

**Preventive measure:** the fix itself is the regression test — once
`data:`→`form:` is fixed and `withUserMessage()` is applied consistently,
these 24 tests exercise the real validation paths and would catch a
regression to either bug independently (a future `data:` reintroduction
would revert to "No data received"; a future bare `throw new
CarTransferException(...)` would revert to the generic fallback message,
both of which fail the specific-message assertions already in this file).

## Database & Security Considerations

- No schema or DB changes.
- No new auth/CSRF surface — `Token::check()` behavior is unchanged; this
  only changes what user-facing text an already-thrown, already-caught
  exception carries.
- **Reviewed for information disclosure:** one throw site
  (`CarValidator::parseModel()` failure) previously built its technical
  message by concatenating raw user input (`'Invalid model format: ' .
  $model`). Applying `withUserMessage()` naively would have surfaced that
  raw input back to the client in the JSON response. Deliberately gave
  this one a distinct, generic user message ("Invalid model format.
  Expected series|variant|type (e.g. S4|SE|FHC).") while keeping the
  detailed, input-echoing text only in the technical/log message —
  consistent with the file's own existing pattern at the DB-error throw
  site (line 103), which already keeps `$e->getMessage()` detail
  server-side only and gives the client a separate generic message.
- All other 15 throw sites use static, pre-written strings for both the
  technical and user messages (identical text) — no new information is
  exposed that wasn't already a static string in the codebase.

## Architecture & Design

Two independent fixes, one test-only and one application-only, both
required for the issue to actually be resolved (not just "look" resolved):

1. **Test file:** mechanical `data: {` → `form: {` replacement across all
   24 call sites in `tests/playwright/length-validation.spec.js`. No
   assertion logic needed to change — every existing regex/property check
   was verified to match the real endpoint messages once requests actually
   reach `$_POST` (verified directly against `chassis-availability.php`'s
   `ApiResponse::error()` calls and `transfer-request.php`'s exception
   messages).

2. **Application file:** converted all 16 `throw new
   CarTransferException(...)` call sites in `app/api/cars/transfer-request.php`
   to `CarTransferException::withUserMessage($technical, $user, ...)`,
   using identical text for both arguments except the model-format throw
   (see Security Considerations above). This was discovered mid-implementation
   (not part of the original issue) and explicitly scoped with the user:
   originally considered fixing only the 9 length-validation-specific
   throws (this issue's direct need), but expanded to all 16 in the file
   since every one has the identical defect and leaving 7 more instances
   of it sitting untouched in the same file, freshly reviewed, was judged
   not worth the future rediscovery cost.

**Alternative considered and rejected:** changing `CarTransferException`'s
`getDefaultUserMessage()` to return `$this->getMessage()` (the technical
message) instead of a hardcoded fallback, which would have fixed all 16
sites without touching each call site individually. Rejected: this would
silently change the *default* behavior for any future `throw new
CarTransferException(...)` anywhere in the codebase (not just this file),
including any that currently rely on the safe generic fallback for
messages not meant for client display — a much larger, less-reviewable
blast radius than fixing 16 known call sites in one already-reviewed file.

## Implementation Checklist

- [x] Replace all 24 `data: {` occurrences with `form: {` in
      `tests/playwright/length-validation.spec.js` — verified via `grep -c`
      before/after (24 → 0 remaining `data: {`, 24 `form: {`)
- [x] Convert all 16 `throw new CarTransferException(...)` call sites in
      `app/api/cars/transfer-request.php` to
      `CarTransferException::withUserMessage(...)`, matching the existing
      pattern already used at line 103 and extensively in
      `usersc/classes/Owner.php`; gave the model-format-parse-failure site
      a distinct generic user message instead of echoing raw input
      (depends on: none — independent file from the test fix, but same PR)
- [x] Run `vendor/bin/phpstan analyse app/api/cars/transfer-request.php` —
      clean, no errors
- [x] Confirm no pre-existing `phpstan-baseline.neon` entries for this file
      — clean, none found
- [x] Run the full `length-validation.spec.js` file locally against MAMP,
      confirm all 24 tests pass genuinely (not vacuously) — 24/24 passed;
      re-ran immediately after to confirm no rate-limit flakiness on
      back-to-back runs — 24/24 passed again
- [x] Run `composer test:full` to confirm no regression to existing
      PHPUnit suites from the `transfer-request.php` change — unit
      1715/4720 OK, integration 503/2051 OK
- [x] Run `/security-review` — required (production PHP file touched,
      exception/error-message handling changed). Result: 0 Critical/High/
      Medium, 1 Low. Confirmed no SQLi (parameterized queries throughout),
      no XSS (JSON API), CSRF/auth/rate-limit control flow byte-for-byte
      unchanged, `parseModel()` failure path correctly avoids reflecting
      raw input to the client. **Low finding, accepted as intended
      behavior (user-confirmed):** surfacing the chassis-lookup trio's
      distinct messages ("no car found" / "already own this car" /
      "pending transfer exists") lets an authenticated, rate-limited user
      distinguish whether a given chassis number exists in the registry.
      Not a new disclosure — car records (including chassis numbers) are
      already fully public via the car-listing page, so this doesn't
      reveal anything not already visible by browsing the registry. Users
      also need the specific reason a transfer request failed to take the
      correct next action, so collapsing these three back to one generic
      message would trade away real UX value for no actual confidentiality
      gain. Documented here and in the PR description as a reviewed,
      accepted tradeoff rather than an oversight.
- [x] Run `senior-architect` review of the diff, address findings — clean,
      no Blocking/Recommendation. Verified all 16 `withUserMessage()`
      conversions correct (no argument-slot swaps), confirmed no other
      throw site echoes raw user input, and — critically — searched the
      whole codebase (`app/assets/js/car-edit.js`, Playwright fixture
      helpers, PHPUnit exception-hierarchy tests) and confirmed nothing
      anywhere depends on the old generic-fallback behavior for any of the
      16 changed sites. Reconfirmed the chassis-lookup-trio tradeoff
      reasoning holds.

## Test Plan

- No new test files. The 24 existing tests in `length-validation.spec.js`
  become the real regression coverage for both bugs once both fixes land
  together — verified they now exercise genuine pass/fail paths rather
  than accidentally-satisfied generic assertions.
- Verification already performed: direct empirical reproduction of both
  bugs before the fix (a standalone `page.request.post()` with `data:`
  confirmed "No data received"; a `form:`-encoded request before the
  exception fix confirmed the generic fallback message), and confirmation
  after each fix that the specific expected message is returned.
- Full PHPUnit suite (`composer test:full`) run to confirm the
  `transfer-request.php` message-text change doesn't break any existing
  integration test that might assert on exception messages (none do,
  confirmed via `grep` — the one PHPUnit test referencing "No car found"
  tests only the underlying DB query, not the HTTP response body).
