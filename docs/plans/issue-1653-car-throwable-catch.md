# Issue #1653: fix: Car.php catches Exception instead of Throwable at 3 call sites

**Branch:** `bug/1448-car-update-clear-fields` (continuing — combined PR per
sprint plan, see `Plans/sprints/v2.29.5.md`: #1448 → #1653 → #1519 land as
one PR)
**Milestone:** `milestone/v2.29.5`
**Status:** Implemented — pending commit/PR

## Context

`Car.php` catches `Exception` rather than `\Throwable` at three call sites.
`CarAdministrationService` (same package) already uses `\Throwable`
consistently — confirmed at `CarAdministrationService.php:89,213,300`, all
written as `catch (\Throwable $e)`. A `\TypeError`/`\Error` at any of the
three `Car.php` sites currently escapes uncaught instead of being logged and
wrapped into the project's typed exception contract
(`docs/development/ERROR_HANDLING.md`).

Current line numbers (shifted slightly from the issue's #164/#225/#598 due
to #1448's edits earlier on this same branch — re-verified directly, not
assumed):

- `Car.php:173` — inside `create()`, wraps `CarImageProcessor::encodeImages()`
- `Car.php:234` — inside `update()`, same call, inside `update()`
- `Car.php:610` — inside `findByVerificationCode()`, wraps the repository
  lookup + `Car` construction

## Bug Escape Analysis

- **Root cause:** `Car.php` predates `CarAdministrationService`'s extraction
  (per `CarAdministrationService.php`'s own docblock, `@since v2.15.0`,
  "Extracted from Car.php") and was never updated to match the `\Throwable`
  convention adopted in the newer file.
- **Testing gap:** No existing test constructs a `\TypeError`/`\Error`
  (rather than an `\Exception` subclass) inside these three try blocks, so
  the narrower catch's failure mode — an uncaught fatal instead of a typed
  `ElanRegistryException` — has never been exercised.
- **Preventive measure — deliberately NOT a behavioral test, confirmed with
  user:** `Car.php` constructs `CarImageProcessor` and `CarRepository`
  internally with no injection point (`getImageProcessor()` at line 100 is
  private, `new CarRepository(dbi())` inline at `findByVerificationCode()`),
  so forcing a genuine `\TypeError`/`\Error` inside any of the three guarded
  blocks isn't achievable without adding test-only dependency injection —
  disproportionate scope for a 3-line catch-type widening. Verified the one
  existing adjacent test, `testCreateCarFailsWithImageEncodingError`
  (`CarDatabaseOperationsTest.php`), forces `encodeImages()` to fail via
  malformed UTF-8 causing `json_encode()` to return `false` — that already
  throws `ImageProcessingException` internally (an `Exception` subclass), so
  it does not exercise the `\Throwable` widening either way and needs no
  change. Prevention here is static: PHPStan + code review confirm the
  catch-type actually changed at all three sites, and the existing test
  suite (unit + integration) passing unchanged confirms no regression to
  the `Exception`-subclass paths those three catches already handled.

## UserSpice Integration

None — this is a PHP language-level catch-type change with no UserSpice
touchpoint.

## Database & Security Considerations

None. No schema, query, or auth changes. Widening a catch clause from
`Exception` to `\Throwable` only affects which error classes get logged and
wrapped versus escaping uncaught — it does not change what triggers the
catch's existing typed-exception-and-log behavior for `Exception` subclasses,
and does not introduce any new code path reachable by user input.

## Architecture & Design

Change all three call sites from `catch (Exception $e)` to
`catch (\Throwable $e)`, matching `CarAdministrationService`'s exact style
(fully-qualified `\Throwable`, no `use Throwable;` import — consistent with
how that file already does it).

**`use Exception;` import stays.** Confirmed via grep: `Exception` still
appears in multiple `@throws Exception` PHPDoc tags elsewhere in the file
(lines 149, 201, 412, 442, 511, 543, 559, 576, 593) beyond the three catch
blocks being changed. Those PHPDoc tags are arguably imprecise on their own
(the methods actually throw typed `ElanRegistryException` subclasses, not
raw `Exception`), but that's a separate, out-of-scope observation — not
touching them here; the import is not orphaned by this change regardless.

No behavioral change to the catch bodies themselves — only the caught type
widens. The existing `logger()` + typed-exception-throw logic in each
`catch` block is unchanged.

## Implementation Checklist

- [x] Change `catch (Exception $e)` → `catch (\Throwable $e)` at
      `Car.php:173` (inside `create()`) — `usersc/classes/Car/Car.php`
- [x] Change `catch (Exception $e)` → `catch (\Throwable $e)` at
      `Car.php:234` (inside `update()`) — `usersc/classes/Car/Car.php`
- [x] Change `catch (Exception $e)` → `catch (\Throwable $e)` at
      `Car.php:610` (inside `findByVerificationCode()`) —
      `usersc/classes/Car/Car.php`
- [x] Run `composer test:medium`, verify pass — 1688 unit + 92 integration,
      all passing unchanged
- [x] PHPStan baseline hygiene: confirm `Car.php` carries no pre-existing
      `phpstan-baseline.neon` entries introduced/exposed by this change —
      none found
- [x] Run `senior-architect` review of the diff, address findings — **Go**,
      0 blocking findings

(No `/security-review` needed — no forms/SQL/auth touched, per Database &
Security Considerations above.)

## Test Plan

No new tests — confirmed with user this is a mechanical, low-risk catch-type
widening not practically unit-testable without disproportionate DI scaffolding
(see Bug Escape Analysis above). Verification is:

1. Static: grep confirms all three sites read `catch (\Throwable $e)`.
2. PHPStan: clean on `Car.php`, no new baseline entries.
3. Existing suite: `composer test:medium` passes unchanged, including
   `testCreateCarFailsWithImageEncodingError` and
   `testUpdateCarFailsWithInvalidCsrfToken`-adjacent tests that exercise
   these code paths' `Exception`-subclass behavior today.

## Documentation Plan

None — internal error-handling consistency fix, no public API/schema/user-facing change.
