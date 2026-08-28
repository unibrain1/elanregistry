# Issue #1654: OwnerException abstract base; retire dead OwnerNotFoundException

**Branch:** `issue/1654-owner-exception-base`
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

The Car domain has `CarException` (abstract, `usersc/classes/Exceptions/CarException.php`)
specifically so callers can `catch (CarException $e)` for any car-domain
error. The Owner domain has no equivalent — its five exceptions
(`OwnerCreationException`, `OwnerNotFoundException`, `OwnerSearchException`,
`OwnerUpdateException`, `OwnerValidationException`) all extend
`ElanRegistryException` directly, and the newly-added `OwnerDatabaseException`
(#1505 PR B) does too, only because no Owner base existed yet — its docblock
explicitly defers to this issue.

Separately, `OwnerNotFoundException` is dead: confirmed via repo-wide grep,
nothing throws it in production code (only referenced in test files).
`Owner::find()` returns `bool` for genuinely-not-found (and, since #1505,
throws `OwnerDatabaseException` only for actual DB failures) — never
`OwnerNotFoundException`. Its two catch sites (`app/admin/includes/
load-owner-info.php:212`, `load-owner-profile.php:196`) are unreachable dead
code, each immediately followed by a generic `catch (ElanRegistryException
$e)` block that would actually handle the case if it ever occurred.

**Confirmed with user:** remove `OwnerNotFoundException` rather than wire it
up — matches the issue's stated preference, and `Owner::find()` has no
natural throw site for it without a larger behavioral change across every
caller.

## Database & Security Considerations

None — pure exception-hierarchy refactor, no schema/security-relevant code
touched.

## Architecture & Design

**1. New `OwnerException` abstract base** —
`usersc/classes/Exceptions/OwnerException.php`, mirroring `CarException`'s
exact shape (confirmed via Explore): `abstract class OwnerException extends
ElanRegistryException`, no constructor override, two protected static
template methods:

- `getDefaultUserMessage(): string` → a generic Owner-domain message
  (`CarException` uses `"A car operation error occurred."` — Owner's
  equivalent: `"An owner operation error occurred."`)
- `getDefaultLogCategory(): string` → `LogCategories::LOG_CATEGORY_OWNER_ACTIONS`
  (the category already shared by 4 of the 5 existing Owner exceptions)

No `getDefaultHttpStatusCode()` override, same as `CarException` — inherits
the base default, and subclasses that need a different code
(`OwnerValidationException`: 422) keep their own override.

**2. Retarget existing exceptions to extend `OwnerException`:**

- `OwnerCreationException` — `extends OwnerException` (was
  `ElanRegistryException`); keeps its own `getDefaultUserMessage()` override,
  drops nothing (its log category already matches the new base's default,
  but keeping the explicit override is harmless and matches how
  `CarNotFoundException` etc. also keep their own overrides despite matching
  `CarException`'s default in some cases — no bespoke logic to remove).
- `OwnerSearchException` — same treatment.
- `OwnerUpdateException` — same treatment.
- `OwnerValidationException` — `extends OwnerException`; keeps its own
  `getDefaultLogCategory()` (→ `LOG_CATEGORY_VALIDATION_ERROR`, diverges from
  the new base's default) and `getDefaultHttpStatusCode()` (422) overrides —
  both still needed since they differ from `OwnerException`'s defaults.
- `OwnerDatabaseException` — `extends OwnerException` (was
  `ElanRegistryException`, per its own docblock note deferring to this
  issue); keeps its own `getDefaultLogCategory()` override (→
  `LOG_CATEGORY_DATABASE_ERROR`, diverges from the new base's default). Update
  its docblock to remove the "no OwnerException abstract base exists yet"
  note, since this issue adds it.

**3. Remove `OwnerNotFoundException` and its dead catches:**

- Delete `usersc/classes/Exceptions/OwnerNotFoundException.php`.
- Remove the two dead `catch (OwnerNotFoundException $e) { ... }` blocks in
  `app/admin/includes/load-owner-info.php:212-216` and
  `load-owner-profile.php:196-200` — the subsequent `catch
  (ElanRegistryException $e)` block in each file already handles anything
  that could reach that point, so removing the dead-specific catch changes
  no runtime behavior (nothing throws `OwnerNotFoundException` today, and
  after this change nothing can, since the class no longer exists).
- Remove the two test references: `tests/unit/system/AutoloaderTest.php`
  (lines ~119, ~208-209 per Explore) and `tests/unit/exceptions/
  ExceptionHierarchyTest.php` (lines 21, 54, 289, 397, 427 per Explore —
  remove its entries from `EXCEPTION_CLASSES`, the category/status data
  providers, and its `use` import).

**4. `ExceptionHierarchyTest.php` hierarchy assertions** — confirmed via
Explore: the ancestry check is `is_subclass_of($className,
ElanRegistryException::class)` (indirect ancestry), not a direct-parent
equality check, so retargeting the 5 remaining Owner exceptions to extend
`OwnerException` instead of `ElanRegistryException` directly requires no
test-logic changes — only the `OwnerNotFoundException` removal (item 3)
touches this file.

**5. No caller-side changes needed** — confirmed via Explore: every existing
`catch (OwnerXxxException $e)` site (`process-owner-search.php:65`,
`process-owner-update.php:74,86`, `account.php:34`, `user_settings.php:301`,
`Owner.php:243,340`) is a plain type-matched catch with no `get_class()`/
`::class ===` fragile check that inserting an intermediate abstract parent
would break. PHP catch matching is inheritance-based, so all continue to
work unchanged.

## Implementation Checklist

- [x] Create `usersc/classes/Exceptions/OwnerException.php` (abstract,
      mirrors `CarException`'s shape) — parallel-safe
- [x] Change `OwnerCreationException` to `extends OwnerException` —
      `usersc/classes/Exceptions/OwnerCreationException.php` (depends on:
      `OwnerException` created)
- [x] Change `OwnerSearchException` to `extends OwnerException` —
      `usersc/classes/Exceptions/OwnerSearchException.php` (depends on:
      `OwnerException` created)
- [x] Change `OwnerUpdateException` to `extends OwnerException` —
      `usersc/classes/Exceptions/OwnerUpdateException.php` (depends on:
      `OwnerException` created)
- [x] Change `OwnerValidationException` to `extends OwnerException`, keep its
      log-category/status overrides — `usersc/classes/Exceptions/
      OwnerValidationException.php` (depends on: `OwnerException` created)
- [x] Change `OwnerDatabaseException` to `extends OwnerException`, keep its
      log-category override, update its docblock — `usersc/classes/
      Exceptions/OwnerDatabaseException.php` (depends on: `OwnerException`
      created)
- [x] Delete `usersc/classes/Exceptions/OwnerNotFoundException.php`
      (parallel-safe with the extends-changes above)
- [x] Remove the dead `catch (OwnerNotFoundException $e)` block —
      `app/admin/includes/load-owner-info.php` (depends on:
      `OwnerNotFoundException` deleted) — also removed its now-unused `use`
      import
- [x] Remove the dead `catch (OwnerNotFoundException $e)` block —
      `app/admin/includes/load-owner-profile.php` (depends on:
      `OwnerNotFoundException` deleted) — also removed its now-unused `use`
      import
- [x] Remove `OwnerNotFoundException` references from `tests/unit/system/
      AutoloaderTest.php` (depends on: `OwnerNotFoundException` deleted) —
      swapped the "test owner exception" functional-throw assertion to
      `OwnerCreationException` rather than deleting owner coverage entirely
- [x] Remove `OwnerNotFoundException` from `EXCEPTION_CLASSES` and its data
      providers in `tests/unit/exceptions/ExceptionHierarchyTest.php`
      (depends on: `OwnerNotFoundException` deleted). Deviation from plan:
      no existing "`OwnerException` verification alongside the existing
      `CarException` pattern" existed in this file to extend — `CarException`
      itself has no presence here either. Found the actual established
      pattern instead: a dedicated `CarExceptionHierarchyTest.php` sibling
      file. Created `tests/unit/exceptions/OwnerExceptionHierarchyTest.php`
      mirroring it exactly (abstract check, extends-ElanRegistryException
      check, per-class extends-OwnerException check, instanceof check,
      catch-block check) — includes `OwnerSearchException`, which was never
      in `ExceptionHierarchyTest.php`'s list at all (pre-existing gap,
      unrelated to this issue, not fixed there but now covered here)
- [x] Run `composer test:quick`, verify pass (issue's explicit acceptance
      criterion)
- [x] Run `composer test:full`, verify pass
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries — all pre-existing production/test
      files touched are clean; the new `OwnerExceptionHierarchyTest.php`
      required 2 new baseline entries (`is_subclass_of()`/`assertTrue()`
      always-true, since PHPStan's static type-checker already knows the
      declared `extends` relationship) — these exactly mirror
      `CarExceptionHierarchyTest.php`'s existing baselined entries for the
      identical test-structure pattern, confirmed via diff before/after
      `composer phpstan:baseline` (only 2 additions, 0 removals elsewhere)
- [x] Run `senior-architect` review of the diff, address findings. Found 1
      Important (stale `OwnerNotFoundException` references left in
      `ERROR_HANDLING.md` and `PAGE_LOADING_FLOW.md`) and 2 Minor/optional
      (redundant `getDefaultLogCategory()` overrides on 3 Owner exceptions
      now byte-identical to the new base default; new hierarchy test's
      PHPStan-flagged "always true" assertions confirmed non-vacuous,
      matching `CarExceptionHierarchyTest.php`'s accepted precedent — no
      action needed). Fixed the Important finding (updated both docs, plus
      corrected `ERROR_HANDLING.md`'s exception table which was also
      missing `OwnerException`/`OwnerDatabaseException` rows). Also took
      the optional Minor cleanup (removed the 3 dead overrides) since the
      diff was fresh and the fix was small. Re-ran PHPStan (clean) and
      `composer test:quick` (1713 tests, 4723 assertions, unchanged) and
      `composer check:docs` (clean) after the fixes.

(No `/security-review` needed — pure exception-hierarchy refactor, no forms/
SQL/auth/user-input handling touched.)

## Test Plan

- `ExceptionHierarchyTest.php` already exercises every Owner exception's
  ancestry (`is_subclass_of(..., ElanRegistryException::class)`), default
  log category, and default HTTP status via its existing data providers —
  no new test *methods* needed, just updating the provider entries to drop
  `OwnerNotFoundException` and (optionally) add a direct assertion that the
  5 remaining Owner exceptions are `instanceof OwnerException`, mirroring
  however `CarException`'s subclasses are asserted there today (check the
  file for the existing pattern before adding).
- No behavioral test changes needed elsewhere — this is a supertype-only
  change; nothing observable at runtime differs for any exception that still
  exists.
- `composer test:quick` (issue's explicit acceptance criterion) and
  `composer test:full` both run as part of the checklist.

## Documentation Plan

Check `docs/development/CLASSES.md`'s Owner exception-handling section
(added by #1505 PR B) for accuracy — if it lists the five/six Owner
exceptions or describes them as extending `ElanRegistryException` directly,
update it to reflect the new `OwnerException` base and the removal of
`OwnerNotFoundException`, in the same PR.
