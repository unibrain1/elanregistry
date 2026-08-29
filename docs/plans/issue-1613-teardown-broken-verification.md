# Issue #1613: Remove the broken, unused car-owner verification directory

**Branch:** `bug/1613-teardown-broken-verification`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

`app/admin/verify/` implements an emailed car-owner verification flow that is
broken at three independent layers (dead link path, session-bound CSRF token
that can never match a recipient's session, an admin-only auth gate on an
owner-facing page) and has no navigation entry point anywhere in the app —
only reachable by direct URL. The issue originally proposed an in-place
short-circuit teardown (leave the files, block their side effects) so the
planned v2.30.0 rebuild (#1155/#1156) could edit them in place.

Mid-planning the user redirected: **delete the directory entirely** instead
of patching it — it hasn't been used in a decade, isn't linked from any
menu, and full removal is simpler and safer than maintaining dead
short-circuited code. #1155/#1156 will create new files from scratch rather
than editing these; note that explicitly on those issues once this merges.
Already-mailed legacy links (pointing at either the wrong `app/verify/...`
path or the real `app/admin/verify/...` path) will 404 naturally — the user
confirmed this is fine, since every such link already either 404s or fails
CSRF validation today, so there is no functional regression.

Research (this session) found the underlying service layer
(`CarVerificationManager`, `Car::setVerificationCode()`/`markVerified()`/
`findByVerificationCode()`, `CarRepository::updateVerificationCode()`/
`updateLastVerified()`/`findByVerificationCode()`) is broader than the 3
`app/admin/verify/` files and has its own dedicated unit/integration tests
independent of them. This service layer is **retained** — the v2.30.0
rebuild will almost certainly call into it from new endpoint files, so
deleting it now would create rework. This plan only removes the 4 files in
`app/admin/verify/` (including `_email_template.php`, a private partial not
named in the original issue text but exclusively used by `send_email.php`)
and every reference to them.

## Bug Escape Analysis

- **Root cause:** the feature was built with a session-bound CSRF token
  (`Token::generate()`/`Token::check()`) applied to a link delivered by
  email to a third party — architecturally incompatible, since the
  recipient has no session relationship to the token generator. Combined
  with a wrong hardcoded link path (`app/verify/...` vs. the real
  `app/admin/verify/...`) and an admin-only auth gate on what's meant to be
  an owner-facing page, every layer of the flow independently fails.
- **Testing gap:** no test ever exercised the full emailed-link round trip
  (generate link → simulate a logged-out recipient clicking it → expect
  success), which is exactly the path that was broken. Existing tests
  (`VerifyCarWiringTest.php`, `CarVerificationManagerTest.php`) test
  service-layer wiring and endpoint-source-text patterns in isolation, not
  end-to-end link validity.
- **Preventive measure:** not applicable here — this issue removes the
  broken surface rather than fixing and re-testing it. The design note this
  issue's original text captured (a per-car, per-send token persisted with
  the car, not a session token) is the actual fix, deferred to #1155.

## UserSpice Integration

None needed — this is a deletion, not new functionality.

## Database & Security Considerations

- **No schema changes.** `cars.vericode` values and the columns themselves
  are untouched — out of scope, owned by #1155.
- **Removes an admin-triggerable, unauthenticated-by-design mutation path.**
  `send_email.php` today deletes a `cars_hist` row and sends email on any
  GET request with no confirmation/CSRF check. Deleting the file removes
  this entirely rather than gating it — a strict security improvement over
  the original short-circuit plan (no code path remains to audit).
  Resolves the underlying concern behind #1568 (unguarded `->first()->id`
  dereference) by removing the code, not just guarding it — note on #1568
  and #1155 that this specific code no longer exists.
- **`LogCategories::LOG_CATEGORY_CAR_VERIFICATION` is retained** — used by
  `CarVerificationManager`/`Car`'s verification methods, which stay.

## Architecture & Design

### Files deleted (4)

- `app/admin/verify/index.php`
- `app/admin/verify/send_email.php`
- `app/admin/verify/verify_car.php`
- `app/admin/verify/_email_template.php` (private partial, `include()`'d
  only by `send_email.php`, referenced nowhere else — must go too or the
  directory can't be fully removed)

The directory itself is removed once empty.

### Files edited to remove references

- **`z_us_root.php`** — remove `'app/admin/verify/'` from the `$path`
  array (line ~9). This array is only consulted for path-registration
  purposes for files that call `securePage()`; since no such files remain
  in that directory, the entry is dead.
- **`phpstan-baseline.neon`** — remove all 5 ignore-rule stanzas whose
  `path:` is `app/admin/verify/_email_template.php` (currently lines
  ~55, 61, 67, 73, 79 — read each full stanza before deleting, not just the
  path line).
- **`tests/unit/system/PageMetadataCompletenessTest.php`** — remove the 3
  `EXPECTED_INCOMPLETE_PAGES` entries for the deleted files (lines
  ~110-112). Once the files don't exist, `securePage()`-discovery won't
  find them at all, so they must not remain in this expected-exceptions
  list (a stale entry here would fail the test's own "matches snapshot"
  assertion once discovery no longer surfaces them).
- **`tests/unit/system/LogCategoriesUsageTest.php`** — remove the 2
  `ADMIN_ENDPOINT_FILES` entries for `send_email.php`/`verify_car.php`
  (lines ~68-69).
- **`tests/unit/admin/VerifyCarWiringTest.php`** — delete this entire file
  (153 lines). It exists solely to test `verify_car.php`'s try/catch
  wiring by asserting against the endpoint's source text
  (`VERIFY_ENDPOINT = 'app/admin/verify/verify_car.php'`); once the file
  is gone this test has nothing to assert against.
- **`tests/unit/regression/DatabaseInterfaceUsageRegressionTest.php`**
  (line ~204) — a docblock comment names an exception carve-out for
  `$db->deleteById()` in `send_email.php`. Since that code no longer
  exists, the carve-out itself is moot — remove the comment (and verify the
  scan's actual behavior/count doesn't depend on this file existing; it's
  a comment, but confirm no live assertion references the path string).
- **`tests/unit/users/VerificationSecurityTest.php`** (line ~73) — a
  comment references `verify_car.php`'s regex pattern as context. The test
  itself is about `VerificationSecurity`'s general MD5-format regex, not
  exclusively this file — reword the comment to describe the pattern
  without citing a file that no longer exists; do not delete the test.
- **`usersc/user_settings.php`** (line ~290) — a comment cites
  `verify_car.php`'s defensive-wrap pattern as precedent. Reword to
  describe the pattern generically (or cite the PR number alone) rather
  than a now-nonexistent file.
- **`docs/development/SYSTEM_OVERVIEW.md`** — two edits:
  - Line ~172: the "Car verification" bullet under whatever section lists
    site capabilities currently says it's "wired but broken." Rewrite to
    state it was removed in #1613 pending the v2.30.0 rebuild (#1155/#1156).
  - Lines ~305-312 (§7, "What is built but broken or incomplete"): the
    entire "Car verification does not work end to end" bullet describing
    the bug in present tense needs rewriting — this is no longer "built but
    broken," it no longer exists. Either remove the bullet (since §7 is
    about broken *existing* features) or move a short note to §6
    ("deliberately not built") reframed as "removed pending rebuild."
- **`docs/development/adr/ADR-003-database-audit-trails-triggers-history-tables.md`**
  — per user direction (rewrite to past tense, note removal):
  - Lines ~134-135 (operation-type table): keep the `'VERIFIED'`/
    `'VERIFIED SOLD'` rows as a historical record of what `cars_hist` could
    contain — annotate that the producing code path was removed in #1613.
  - Lines ~217-222 ("History rows are mutable" bullet): update from
    present-tense "is no longer in active use and will fail if attempted"
    to past-tense, stating the mutating code paths (`verify_car.php`,
    `send_email.php`) were removed entirely in #1613, not merely dormant.

### Explicitly retained, no changes

- `usersc/classes/Car/CarVerificationManager.php`, `Car::setVerificationCode()`/
  `markVerified()`/`findByVerificationCode()`, `CarRepository::updateVerificationCode()`/
  `updateLastVerified()`/`findByVerificationCode()` — service layer the
  v2.30.0 rebuild will reuse.
- `tests/unit/cars/services/CarVerificationManagerTest.php`,
  `tests/integration/CarVerificationTest.php`,
  `tests/integration/cars/services/CarRepositoryFindByVerificationCodeFailureTest.php`
  — exercise the retained service layer directly, independent of the
  deleted endpoint files. No changes needed.
- `LogCategories::LOG_CATEGORY_CAR_VERIFICATION` — still used by the
  retained service layer.
- `.htaccess` — confirmed no existing rule references either verify path;
  no redirect added (per user direction, dead links 404 naturally).
- `usersc/classes/admin/PagePermissionClassifier.php` — confirmed no
  reference to any of these 3 paths; no edit needed.

### Cross-issue follow-up (not part of this PR's diff)

- Add a comment to **#1155** noting: (a) the design input already captured
  in #1613's original text — a per-car, per-send token persisted with the
  car, not a session CSRF token, is required for any future emailed-link
  flow; (b) the rebuild creates new files at `app/admin/verify/` rather
  than editing surviving ones, since this PR deletes them; (c) the
  unguarded `->first()->id` dereference #1568 flagged no longer exists —
  don't reintroduce it in the rebuild.
- Close **#1568** with a note that its root cause is now moot (the file is
  deleted), cross-referencing the #1155 comment above so the underlying
  defensive-coding lesson isn't lost.

## Implementation Checklist

- [x] Delete `app/admin/verify/index.php`, `send_email.php`,
      `verify_car.php`, `_email_template.php`, and the now-empty
      `app/admin/verify/` directory — (depends on: none, do first)
- [x] Remove `'app/admin/verify/'` from `z_us_root.php`'s `$path` array —
      `z_us_root.php` (parallel-safe)
- [x] Remove the 5 `app/admin/verify/_email_template.php` ignore stanzas
      from `phpstan-baseline.neon` — done via `composer phpstan:baseline`
      regeneration after all deletions (verified in Step 6.5, not by hand
      editing individual stanzas — PHPStan simply stops reporting for a
      deleted file, and the regenerate step drops the stale entries).
- [x] Remove the 3 deleted-file entries from
      `PageMetadataCompletenessTest.php`'s `EXPECTED_INCOMPLETE_PAGES` —
      `tests/unit/system/PageMetadataCompletenessTest.php` (parallel-safe)
- [x] Remove the 2 deleted-file entries from
      `LogCategoriesUsageTest.php`'s `ADMIN_ENDPOINT_FILES` —
      `tests/unit/system/LogCategoriesUsageTest.php` (parallel-safe)
- [x] Delete `tests/unit/admin/VerifyCarWiringTest.php` entirely —
      `tests/unit/admin/VerifyCarWiringTest.php` (parallel-safe)
- [x] Remove the stale `send_email.php` carve-out comment from
      `DatabaseInterfaceUsageRegressionTest.php` and confirm no live
      assertion depends on the path string —
      `tests/unit/regression/DatabaseInterfaceUsageRegressionTest.php` (parallel-safe)
- [x] Reword the `verify_car.php`-citing comment in
      `VerificationSecurityTest.php` to describe the regex generically —
      `tests/unit/users/VerificationSecurityTest.php` (parallel-safe)
- [x] Reword the `verify_car.php`-citing comment in `user_settings.php` —
      `usersc/user_settings.php` (parallel-safe)
- [x] Update `SYSTEM_OVERVIEW.md`'s capability-list bullet and §7 broken-
      feature bullet to reflect removal, not "broken" —
      `docs/development/SYSTEM_OVERVIEW.md` (parallel-safe). Also added a
      §6 "deliberately not built (for now)" bullet per the plan's own
      alternative, since §7 is specifically about *currently broken*
      features and this no longer qualifies once deleted.
- [x] Update ADR-003's operation-type table annotation and "History rows
      are mutable" bullet to past tense, noting removal —
      `docs/development/adr/ADR-003-database-audit-trails-triggers-history-tables.md` (parallel-safe)
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries beyond the ones intentionally removed
      above (fix or explicitly defer per `/execute-plan` Step 6.5) —
      regenerated via `composer phpstan:baseline`, all 5 verify-related
      entries dropped automatically, `vendor/bin/phpstan analyse` clean.
- [x] Run `composer test:full`, verify pass — specifically confirm
      `PageMetadataCompletenessTest`, `LogCategoriesUsageTest`, and the
      full regression suite are clean with the files gone — OK (1704
      tests, 4698 assertions) unit [down 5 from the deleted
      VerifyCarWiringTest]; OK (494 tests, 1994 assertions) integration
      [unchanged, retained service-layer tests unaffected].
- [x] Run `composer check:docs`, verify no dead links introduced by the
      SYSTEM_OVERVIEW.md/ADR-003 edits — "Documentation checks passed."
- [x] Manual verification: confirm `app/admin/verify/index.php`,
      `send_email.php`, `verify_car.php` (and the legacy `app/verify/...`
      path) all 404 on local MAMP — no notice page, no redirect, per user
      direction. Confirmed via `curl`: all 4 URLs return HTTP 404.
- [x] Run `senior-architect` review of the diff, address findings — no
      Blocking findings. 2 Recommendations, both addressed:
      1. `SYSTEM_OVERVIEW.md`'s §4 cross-reference to "(see §7)" was
         dangling — the §7 bullet it pointed at was removed by this PR and
         its content moved to §6. Fixed to "(see §6)".
      2. `tests/unit/admin/AccountPageWiringTest.php` (added to the repo
         concurrently with this branch, not in the original file sweep)
         cited `VerifyCarWiringTest.php`'s "established precedent" by
         filename — that test is now deleted. Reworded to cite the pattern
         generically with the PR number, same style as the other reworded
         comments in this diff.
      Also filed follow-up issue #1826 (tech-debt, triage label, no
      milestone) for a finding outside this PR's scope: the Fix Page
      Permissions script never prunes `pages` DB rows for deleted files,
      so the 3 removed pages leave orphaned permission-table rows —
      cosmetic, low severity, pre-existing gap not introduced by this PR.
      Re-verified after fixes: `composer test:quick` OK (1704 tests, 4698
      assertions), `composer check:docs` passed, `vendor/bin/phpstan
      analyse` clean.

## Test Plan

- **Removed, not added:** `VerifyCarWiringTest.php` is deleted rather than
  updated, since its entire purpose (testing the deleted endpoint's
  try/catch wiring) no longer applies.
- **Existing coverage updated, not expanded:**
  `PageMetadataCompletenessTest`'s snapshot and `LogCategoriesUsageTest`'s
  file allowlist both shrink to match the file deletions — this is
  necessary maintenance, not new test-writing.
- **No new tests needed.** There is no remaining code path to test — the
  service layer's existing tests (`CarVerificationManagerTest`,
  `CarVerificationTest` integration test, the `CarRepository`
  find-by-verification-code failure test) already cover the retained
  layer independently and require no changes.
- **Manual verification** (see checklist) confirms the removed URLs return
  404, since there's no automated Playwright coverage of a directory that
  no longer exists to assert against (adding a "confirm this 404s"
  Playwright test would be testing the absence of a feature, which isn't
  standard practice here — the file-not-found behavior is Apache/PHP's
  default, not application logic worth pinning).

## Documentation Plan

- `docs/development/SYSTEM_OVERVIEW.md` and
  `docs/development/adr/ADR-003-database-audit-trails-triggers-history-tables.md`
  updated in this PR (see Architecture & Design above) — both are
  in-repo docs describing behavior this diff directly falsifies, updated
  in the same PR per the project's own `/finish-issue` Step 4.6 policy
  rather than deferred.
- No wiki impact — the wiki's architecture pages don't reference this
  directory (not linked from navigation, never documented as a user-facing
  feature).
