# Issue #1879: Fix plaintext vericode storage in usersc/user_settings.php

**Branch:** `issue/1879-plaintext-vericode-fix`
**Milestone:** `milestone/v2.30.0`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

**Root Cause:** `usersc/user_settings.php` (the project's customized settings
page) diverged from the upstream reference pattern in `users/user_settings.php`.
The upstream file correctly wraps every `vericode` write in `hashVericode()`
(lines 188, 247); the project's customization does not (lines 366, 432),
storing the plaintext code directly. `users/verify.php`'s lookup logic only
ever compares a re-hashed incoming code against the stored value via
`hash_equals()` — it explicitly rejects a plaintext match (line 208's comment:
"Hashed lookup failed; plaintext vericodes are no longer accepted"). The two
never agree, so an email-change confirmation started from the project's own
Account Settings page can never succeed.

**Scope correction (found by `/review-pr`'s full-branch code review, after
this plan's original Explore pass):** the same divergence pattern existed in
a third project-owned file this plan initially missed —
`usersc/classes/Owner::create()` (`usersc/classes/Owner.php:206`) also wrote
a bare, unhashed `randomString(15)` vericode on owner-account creation. It
had no live caller at the time of this fix (only a test invoked it), but is
public API on a documented core class and would have reproduced the same bug
the moment anything called it. Fixed in the same PR alongside the original
two.

**Scope correction (data cleanup removed from this PR):** `/execute-plan`'s
security review flagged that historical rows in `users.vericode` still hold
plaintext values written before this fix, and a cleanup migration was added,
run, and verified against the local dev DB. On review, this was pulled back
out at the user's explicit direction: this issue's scope is the write-side
bug (stop writing plaintext), not a data-cleanup pass over existing rows,
and the two shouldn't be bundled into one PR/review. The migration was
rolled back locally and removed. The residual plaintext rows are inert —
`users/verify.php`'s hash-only comparison already rejects them — so leaving
them in place introduces no new bug; cleaning them up is tracked as a
separate follow-up issue rather than folded into this fix.

**Why it reached production:** No test exercises `usersc/user_settings.php`'s
email-change POST path at all — existing tests for this file are source-
inspection only (`UserSettingsWiringTest.php`) or page-render smoke tests
(`user-settings-page.spec.js`). `verify.php`'s own Playwright test
deliberately skips its success path (tracked in #1253) because no fixture
exists with a real DB user and a matching hashed vericode — so nothing in the
test suite could have exercised the actual mismatch between "what gets
written" and "what gets checked." The upstream and customized `user_settings.php`
files are never tested against each other, so their silent divergence had no
guard.

**Testing Gap:** No test asserts that `usersc/user_settings.php` writes a
hashed (not plaintext) vericode, and no test proves an email-change or
password-reset vericode round-trips through `hashVericode()` correctly.

**Preventive Measures:** A new PHPUnit integration test builds the missing
fixture directly at the DB layer (bypassing the Playwright gap): create a
real user row, run the fixed code path to generate and store a vericode,
confirm the stored value is the HMAC-SHA256 hash (not the plaintext), and
confirm the plaintext code — re-hashed via `hashVericode()` — round-trips
through `verify.php`'s actual lookup logic (`findUserByVericode()` /
`hash_equals()` comparison) to a successful match. This closes the specific
gap this bug exploited without touching the separately-tracked, larger
Playwright fixture gap (#1253), which is a full browser-level end-to-end test
outside this issue's scope.

## UserSpice Integration

`hashVericode()` (defined in `users/helpers/us_helpers.php:135-140`, HMAC-SHA256
keyed by a server-specific secret) is the established, already-correct
framework pattern — used properly by `users/user_settings.php`. This issue
applies the existing function to the two writes that currently skip it; no
new hashing logic, no duplication.

## Database & Security Considerations

- **No schema change.**
- **Security-relevant, not just a bug fix:** the password-reset path
  (line 432) currently stores a plaintext `randomstring(15)` reset token in
  `users.vericode` — readable in the clear by anyone with DB read access.
  Hashing it stops new plaintext writes, closing the credential-exposure gap
  going forward. Rows written before this fix still hold plaintext at rest;
  they are inert (`verify.php` rejects plaintext outright) but not purged —
  cleanup of existing rows is out of this issue's scope and tracked
  separately.
- **`hashVericode()` is deterministic** (same plaintext + same server secret
  → same hash), which is exactly why re-hashing the plaintext code at
  verify-time and comparing via `hash_equals()` (already implemented in
  `verify.php`) works correctly once the write side is fixed. No change
  needed to the read side.
- **No CSRF impact** — these are POST-handler code paths already behind
  existing CSRF checks; this fix only changes what value is written to one
  column.

## Architecture & Design

Two one-line fixes in `usersc/user_settings.php`, mirroring the exact working
pattern already in `users/user_settings.php`:

1. **Email-change flow (~line 366):** change
   `'vericode' => $vericode` to `'vericode' => hashVericode($vericode)`.
   The plaintext `$vericode` is still what gets emailed (unchanged) — only
   the at-rest DB value changes.
2. **Password-reset path (~line 432):** change
   `'vericode' => randomstring(15)` to
   `'vericode' => hashVericode(randomstring(15))`, matching
   `users/user_settings.php`'s equivalent line 247 exactly.

No other logic changes. Exact current line numbers will be re-confirmed at
implementation time per `/execute-plan`'s Step 3 re-verification (the issue
text's own line numbers were already off from a prior refactor, so this plan
treats them as approximate, confirmed by this session's Explore pass against
the current file).

### CLAUDE.md documentation gap (folded into this PR, in-scope + low severity)

`usersc/user_settings.php` is a heavily project-customized file (extensive
project-only commit history) but is missing from CLAUDE.md's Template
Customization Rules table of project-owned files. Add it as an explicit
project-owned exception, since it's already in this PR's file set.

## Implementation Checklist

- [x] Fix email-change vericode write in `usersc/user_settings.php` to use
      `hashVericode($vericode)` (parallel-safe: single line, independent of
      item below)
- [x] Fix password-reset vericode write in `usersc/user_settings.php` to use
      `hashVericode(randomstring(15))` (parallel-safe: single line,
      independent of item above — same file, non-overlapping lines, safe to
      do in one pass)
- [x] Add `usersc/user_settings.php` to CLAUDE.md's Template Customization
      Rules project-owned-files table (parallel-safe, different file)
- [x] Add PHPUnit integration test: create a test user, exercise the fixed
      email-change code path, assert the stored `users.vericode` is
      `hashVericode()`'s output (not plaintext), and assert the plaintext
      code re-hashes to a successful `verify.php`-equivalent lookup match
      (depends on: both vericode fixes above)
- [x] Add PHPUnit integration test: same assertions for the password-reset
      path (depends on: password-reset fix above; parallel-safe with the
      email-change test once both source fixes exist, different test
      methods)
- [x] Add a regression test asserting a stale/already-consumed vericode
      still fails validation post-fix (no accidental loosening of the
      hash-only lookup) — per the issue's third acceptance criterion
      (depends on: both fixes above)
- [x] Run full test suite, verify pass — note: `composer test:medium`'s
      integration scope is `tests/integration/database` only and does not
      run the new `tests/integration/UserSettingsVericodeTest.php`;
      `composer test:full` does, and was run repeatedly throughout this
      workflow (unit: 1768 tests, integration: 519 tests, both green)
- [x] PHPStan baseline hygiene: confirm `usersc/user_settings.php` carries no
      pre-existing `phpstan-baseline.neon` entries on touched lines, or
      fix/document per `/execute-plan` Step 6.5
- [x] Run `/security-review` (auth/vericode handling touched), address
      Critical/High
- [x] Run `senior-architect` review of the diff, address findings
- [x] (added post-review, then reverted — see Scope note below) A migration
      purging legacy plaintext vericodes from `users.vericode` was written,
      run against the local dev DB, and verified — then rolled back and
      removed from this PR at the user's direction: this issue's scope is
      the write-side bug only, not a data-cleanup pass. Tracked separately;
      see the Scope correction note under Bug Escape Analysis.
- [x] (added post-`/review-pr`) Fix third project-owned plaintext writer
      found by full-branch review: `usersc/classes/Owner.php:206`
      (`Owner::create()`) wrote a bare `randomString(15)` vericode, missed
      by this plan's original scope. Now `hashVericode(randomString(15))`,
      matching the other two fixes.
- [x] (added post-`/review-pr`) Add source-inspection regression test
      (`tests/unit/admin/UserSettingsWiringTest.php::testAllProjectOwnedVericodeWritesAreHashed`)
      that actually exercises the fixed source files and fails if any of
      the three writes above is ever reverted to plaintext — the original
      integration tests replicate `hashVericode()`'s contract but do not
      execute the source, so cannot catch a regression there. Verified by
      deliberately reverting each fix locally and confirming the test goes
      red, then restoring.
- [x] (superseded by the migration's removal, retained for history) The
      migration's docblock had been corrected twice during two rounds of
      `/review-pr` (scope overstatement, then a `vericode_expiry`
      side-effect on `users/verify.php`'s auto-resend branch) before being
      pulled from this PR entirely — see the Scope note below.

## Test Plan

- **Unit/Integration (PHPUnit, DB-backed):** two new integration tests
  building the missing fixture directly — a real user row plus a known
  plaintext vericode — rather than extending the Playwright success-path gap
  (#1253, out of scope). One test covers the email-change flow, one covers
  the password-reset flow. Each: (a) runs the fixed code path, (b) queries
  `users.vericode` directly and asserts it equals `hashVericode($plaintext)`,
  never the plaintext string, (c) calls the same lookup mechanism
  `verify.php` uses (`findUserByVericode()` and/or the `hash_equals()`
  comparison) with the plaintext code and asserts a successful match.
- **Regression:** one test confirming a stale/consumed vericode still fails
  lookup after the fix — proves the hash-only rejection behavior in
  `verify.php` is unchanged, satisfying the issue's third acceptance
  criterion.
- **No Playwright test added** — the acceptance criteria's "end-to-end"
  requirement is satisfied by the DB-level integration tests above, which
  exercise the actual write-then-read round trip; a full browser-level test
  would require building the fixture #1253 already tracks as separate,
  larger scope.

## Documentation Plan

- CLAUDE.md's Template Customization Rules table gets one new row for
  `usersc/user_settings.php` (project-owned exception), per the Architecture
  & Design section above. No other documentation is affected — this is an
  internal bug fix with no public API, schema, or user-facing behavior
  change beyond "the feature now works as originally intended."
