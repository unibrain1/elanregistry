# Issue #1867: car merge never moves `userimages/` files to the surviving car

**Branch:** `bug/1867-merge-relocate-car-images`
**Milestone:** `milestone/v2.30.1`
**Status:** Implemented — pending commit/PR

## Bug Escape Analysis

**Root cause.** `CarAdministrationService::merge()`
(`usersc/classes/Car/CarAdministrationService.php:281-352`) contains no
filesystem code at all — a grep for `unlink|rename(|is_dir|mkdir|rmdir|glob(`
over the file returns nothing. It transfers `cars_hist` rows, deletes the
source `cars` row, and writes a `MERGE` audit entry, then commits. Files under
`userimages/{sourceId}/` are simply never considered. It also never calls
`CarRepository::updateImage()`, so the surviving car's `cars.image` is left
unchanged.

**Why it reached production.** The merge feature was built as a DB-row
operation (#1311), and the follow-up hardening (#1349, commit `c9d3a901`)
tightened row locking and TOCTOU guards — reinforcing the DB-only framing
rather than questioning it. Image files were never in scope at any point.

**Testing gap — structural, not a mocking gap.**

- `tests/unit/cars/services/CarAdministrationServiceTest.php` mocks
  `DatabaseInterface` and wraps a real `CarRepository`. Its five `merge()`
  tests assert transaction/call behavior. A DB-double test cannot catch a
  *missing* filesystem operation, because there is no call to assert on.
- `tests/integration/CarMergeTest.php` runs against a real DB, but
  `IntegrationTestCase::createTestCar()` inserts a `cars` row and nothing else
  — it never creates `userimages/{id}/`. With no files on disk, there was no
  state whose absence could be observed.
- `tests/integration/CarImageLifecycleTest.php` is the only test that writes
  real image files, and it already documents this **same class of bug** for the
  sibling code paths as explicit `KNOWN GAP (#1629)` baseline assertions
  (`testDeleteCarRemovesDbRowButLeavesFilesOnDisk`,
  `testRemoveImageSucceedsButLeavesFilesOnDisk`). The merge path was never
  given equivalent coverage.

**Preventive measures.** Integration tests in `CarMergeTest.php` that create
real image files for both cars and assert relocation, ordering, collision
handling, and rollback; plus unit tests for the new relocator class against
real temp directories. See Test Plan.

## Product & Design Decisions (confirmed with user)

1. **`cars.image` ordering when both cars have images:** target's existing
   entries first, source's appended. Preserves the surviving car's primary
   (first) image, which is what renders as the card thumbnail.
2. **Failure sequencing:** move files *inside* the open DB transaction, then
   update `cars.image`, then commit. On any throw, run a compensating
   move-back before `rollback()`. (Saga/compensation — the filesystem is the
   non-transactional participant.)
3. **Placement:** a new `CarImageRelocator` class rather than inlining in
   `CarAdministrationService` or extending `CarImageProcessor` (which
   deliberately has zero filesystem code today). Keeps the service DB-focused
   and gives #1629 something to reuse.
4. **Target row locking:** re-read the target car with `findByIdForUpdate()`
   inside the transaction and use its live `image` value as the CAS
   expectation, rather than trusting the pre-transaction `$targetCarData`.
   Lock both rows in **ascending ID order** to avoid deadlock.
5. **Collision renaming:** regenerate via
   `CarImageProcessor::generateSecureFilename()`, keeping one naming scheme
   codebase-wide. All `-resized-{size}` variants of a renamed base get the
   same new base name.

## UserSpice Integration

No UserSpice framework functionality is duplicated. UserSpice provides no
file-relocation or image-management helper; `USERSPICE_FUNCTIONS.md` has no
equivalent. The existing project primitives are reused rather than
re-implemented:

- `ElanRegistry\UploadPathGuard::isWithinTarget()`
  (`usersc/classes/UploadPathGuard.php:39`) — the only existing traversal
  guard, currently used solely by `app/api/cars/save.php`. Reused here.
- `CarImageProcessor::generateSecureFilename()` / `isResizedVariant()` /
  `encodeImages()` / `decodeAndProcessImages()` — reused for naming, variant
  classification, and JSON encoding.
- `CarRepository::updateImage()` — the existing CAS-guarded writer
  (`WHERE id = ? AND image = ?`), reused rather than adding a new write path.

No new exception type is introduced: `ImageProcessingException` and
`CarMergeException` already exist and cover the failure modes.

## Database & Security Considerations

- **No schema change.** `cars.image` (JSON array of *base* filenames only) and
  `cars_hist` are untouched structurally.
- **Prepared statements:** all DB access goes through existing
  `CarRepository` methods, which already parameterize.
- **Row locking:** `findByIdForUpdate()` on both source and target, acquired in
  ascending ID order. Two concurrent merges touching the same pair therefore
  lock in the same order and cannot deadlock.
- **CAS guard:** `updateImage()` returns `false` (does not throw) on conflict;
  merge must treat `false` as a failure and throw, triggering compensation.
- **Path traversal:** every source and destination path is validated with
  `UploadPathGuard::isWithinTarget()` against the `ELAN_IMAGE_DIR` base
  *before* any move. Filenames read from `cars.image` are not trusted — a
  corrupted or legacy JSON value could carry traversal segments, so each is
  validated with `CarImageProcessor::isSafeFilename()` before use.
- **No overwrite:** a collision never overwrites an existing target file; the
  incoming file is renamed.
- **Permissions:** the merge action is gated by page-level `securePage()` on
  `app/admin/index.php` and the pre-switch `Token::check()` CSRF check. No new
  entry point is added, so no new permission surface.
- **GDPR:** no personal data is newly collected or exposed; image files move
  between two car directories within the same registry.

## Architecture & Design

### New class: `usersc/classes/Car/CarImageRelocator`

Namespace `ElanRegistry\Car`. Owns, against real directories:

- `relocate(int $sourceCarId, int $targetCarId, array $sourceBaseFilenames): array`
  — moves every file (base + `-resized-{size}` variants) from
  `{base}/{sourceCarId}/` to `{base}/{targetCarId}/`, renaming on collision,
  removing the emptied source directory. Returns an old→new base-filename map
  so the caller can build `cars.image`.
- A compensating inverse that moves relocated files back and restores the
  source directory, for use on failure.
- Guards every path with `UploadPathGuard::isWithinTarget()` and every
  filename with `CarImageProcessor::isSafeFilename()`.
- A missing source directory is a **no-op, not an error** (AC: "a source car
  with no image directory merges exactly as today").

The image base directory is currently re-derived by string concatenation of
`ELAN_IMAGE_DIR` in ~7 places (`Car.php:75,178`, `app/api/cars/save.php:40`,
`app/admin/scripts/maintenance/24-Regenerate-Optimized-Thumbnails.php:381`,
and three view files). The relocator takes its base directory as a constructor
argument rather than reaching for the global — this keeps it unit-testable
against a temp dir and avoids adding an eighth ad-hoc concatenation site.
Factoring out a shared path helper for all existing sites is **out of scope**
here and is now tracked as **#1943** (v2.33.0), which names this relocator as
its first natural consumer.

### Changes to `CarAdministrationService::merge()`

The service has no constructor today (it receives `CarRepository` per method).
Add an optional constructor-injected `?CarImageRelocator` defaulting to a real
instance, so every existing call site keeps working and tests can inject a
temp-dir-backed relocator.

Revised sequence, all inside the existing transaction:

1. `beginTransaction()`
2. Lock source and target rows with `findByIdForUpdate()` in **ascending ID
   order** (currently only the source is locked)
3. `transferHistory($oldCarId, $newCarId)`
4. `deleteCar($oldCarId)`
5. **`$relocator->relocate(...)`** — move files
6. **`$repo->updateImage($newCarId, $newJson, $liveTargetJson)`** — target's
   existing base filenames first, source's (post-rename) appended; a `false`
   return is a conflict and throws
7. `insertHistory($historyFields)`
8. `commit()`

On any throw in the catch block: run the relocator's compensating move-back
*before* `repo->rollback()`, then preserve the existing exception-mapping
behavior (`CarException` subtypes re-thrown as-is; anything else logged under
`LogCategories::LOG_CATEGORY_CAR_MERGE` and wrapped in `CarMergeException`).

**Audit-row correction.** `$historyFields['image']` currently records
`$targetCarData->image` — the *pre-merge* value read before the transaction.
Since merge now changes that column, the audit row must record the
**post-merge** JSON, or the trail will misstate what the surviving car held
after the operation. This is a behavior change the AC does not mention but
which follows directly from the fix; called out here for explicit approval.

### Alternatives considered

- *Copy-then-delete-after-commit:* safer against loss (a crash leaves a
  duplicate, not a gap) but doubles I/O and leaves cleanup outside the
  transaction. Rejected in favor of move-with-compensation.
- *Move before the transaction opens:* same compensation logic, but widens the
  window in which files sit relocated while the DB is untouched.
- *Extending `CarImageProcessor`:* it deliberately holds zero filesystem code;
  adding moves would change its character.

## Scope Notes

- The issue states a fix script
  `app/admin/scripts/fix/13-Recover-Or-Clear-Lost-Car-Images.php` is "landing
  via #1800". **It does not exist** — `app/admin/scripts/fix/` contains only
  `_TEMPLATE_Fix-Script.php` and `README.md`, and #1800 is closed. The
  historical cars 1738/1739 recovery is therefore still unaddressed. This plan
  is the forward-looking fix only, as the issue's own scope note intends; the
  1738/1739 data recovery is **not** in scope here.
- **Thumbnail regeneration is out of scope.** The relocator moves whatever
  `-resized-{size}` variants exist and does not generate missing ones.
  Regenerating on merge would give the relocator a GD dependency, make the
  compensating move-back responsible for cleaning up newly generated files,
  and let a resize failure roll back an otherwise-valid merge. It is also the
  wrong trigger — a missing variant is a pre-existing defect of that car's
  images, unrelated to merging, and a car that is never merged would stay
  broken. **#1870** (Backlog) already scopes verify/regenerate-all-sizes into
  `24-Regenerate-Optimized-Thumbnails.php`, which sweeps every car rather than
  only merged ones. A variant absent on disk moves without throwing (see Test
  Plan).

- **Pre-existing issue (triaged, no action here):** `delete()` and
  `CarImageProcessor::removeImage()` leak image files the same way. Already
  tracked as **#1629** (open, milestone v2.33.0), and already documented
  in-repo as `KNOWN GAP (#1629)` baseline assertions. Out of scope for this PR;
  the new `CarImageRelocator` is deliberately shaped so #1629 can reuse it.

## Implementation Checklist

- [x] Create `usersc/classes/Car/CarImageRelocator` with `relocate()`, its
      compensating inverse, path/filename guards, and no-op-on-missing-source
      behavior — `usersc/classes/Car/CarImageRelocator.php` (parallel-safe)
- [x] Extract the image fixture helpers (`makeTestJpeg`, `uploadOneTestImage`,
      `variantPath(s)`, `assertUploadedFilesExist`,
      `assertVariantsAreActuallyResized`, `recursiveRemoveDirectory`,
      thumbnail-size init) out of `CarImageLifecycleTest` into a shared trait,
      parameterized by directory — `tests/integration/CarImageFixtureTrait.php`
      (parallel-safe)
- [x] Point `CarImageLifecycleTest` at the extracted trait, verifying its
      existing tests still pass unchanged —
      `tests/integration/CarImageLifecycleTest.php`
      (depends on: fixture trait)
- [x] Add unit tests for `CarImageRelocator` against real temp dirs, incl.
      wrong-typed-input cases —
      `tests/unit/cars/services/CarImageRelocatorTest.php`
      (depends on: CarImageRelocator)
- [x] Add optional constructor-injected `CarImageRelocator` to
      `CarAdministrationService` (default real instance, existing call sites
      unchanged) — `usersc/classes/Car/CarAdministrationService.php`
      (depends on: CarImageRelocator)
- [x] Lock target row via `findByIdForUpdate()` alongside source, in ascending
      ID order — `usersc/classes/Car/CarAdministrationService.php`
      (depends on: constructor injection)
- [x] Call `relocate()` and `updateImage()` in the merge sequence; treat a
      `false` CAS return as failure —
      `usersc/classes/Car/CarAdministrationService.php`
      (depends on: target row locking)
- [x] Run the compensating move-back before `rollback()` in the catch block,
      preserving existing exception mapping —
      `usersc/classes/Car/CarAdministrationService.php`
      (depends on: relocate call)
- [x] Record the **post-merge** image JSON in `$historyFields['image']` —
      `usersc/classes/Car/CarAdministrationService.php`
      (depends on: updateImage call)
- [x] Update existing `merge()` unit tests for the new call sequence —
      `tests/unit/cars/services/CarAdministrationServiceTest.php`
      (depends on: merge changes)
- [x] Add integration tests to `CarMergeTest.php` covering source-only,
      both-have-images (exact order), collision, variants, no-source-dir, and
      move-failure rollback — `tests/integration/CarMergeTest.php`
      (depends on: fixture trait, merge changes)
- [x] Add the direct `updateImage()` CAS live-DB verification test —
      `tests/integration/CarMergeTest.php` (depends on: merge changes)
- [x] Update the Car Image Storage bullet to state that merge relocates files
      — `CLAUDE.md` (parallel-safe)
- [x] Add a `CarImageRelocator` entry following the `CarRepository` format —
      `docs/development/CLASSES.md` (parallel-safe)
- [x] Document merge relocation in the Car Image Storage section —
      wiki `File-Storage-and-Image-Handling.md` (parallel-safe)
- [x] Finalize the #1867 entry (drop the `WIP:` prefix) —
      `docs/releases/RELEASE_NOTES_v2.30.1.md` (parallel-safe)
- [x] Run `composer test:medium` and the integration suite; verify pass
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries (fix or explicitly defer)
- [x] Run `/security-review` — path traversal, overwrite, and CAS handling
- [x] Run `senior-architect` review of the diff, address findings

## Test Plan

### Unit — `tests/unit/cars/services/CarImageRelocatorTest.php`

Real temp directories (no filesystem mocking — moves are the point of the
class, and this codebase has no vfs abstraction). "Unit" here means no DB,
per project convention.

- Source has base + N variants, target empty → all files land in target with
  identical basenames; source dir removed; returned map is identity
- Collision → incoming file renamed; existing target file untouched (assert
  differing *contents* survive under distinct names); base and **all its
  variants** get the same new base name consistently
- A variant missing on disk (partial earlier write) → base still moves; no
  exception
- Source dir absent → no-op, no throw
- Source dir present but empty → dir still removed
- `UploadPathGuard` rejects the destination → throws; nothing written outside
  the base; source untouched
- Compensating move-back after a partial move → files return to source, source
  dir recreated, target left as before
- Target dir unwritable (`chmod 0500`) → throws; move-back fully recovers

**Wrong-typed input** (`array` type hints do not enforce shape):

- Empty list → no-op, not an error
- A `-resized-*` name passed in the base-filename list → asserted explicit
  behavior (reject or ignore), never double-processed as its own base
- Traversal segments / null byte in a filename → rejected before any move
- Associative (non-list) array → iterates values correctly, ignores keys
- Source dir == target dir → safe no-op or throw; never deletes the directory
  after "moving" files into themselves

### Integration — additions to `tests/integration/CarMergeTest.php`

Chosen over `CarImageLifecycleTest.php` because `CarMergeTest` already owns the
two-car fixture, the merge DB assertions, and the transaction-rollback pattern
this issue extends. Image fixtures arrive via the shared trait.

- **Source-only images** → target dir holds source's files; source dir gone;
  `cars.image` = source's base filenames in original order
- **Both have images** → assert **exact order** with `assertSame` (target's
  first, then source's), not set equality
- **Collision** → pre-seed both dirs with an identical hand-picked filename
  (do not rely on two real `random_bytes(16)` calls colliding); assert no
  overwrite and that `cars.image` *read back from the DB* lists the renamed file
- **Variants move with base** → every `-resized-{size}` file exists at the new
  path; `cars.image` still lists **only** base filenames
- **No source image directory** → merge succeeds; target's images untouched;
  `cars_hist` MERGE row written normally
- **Move failure rolls back** → `chmod($targetImageDir, 0500)`, then assert
  merge throws, the source `cars` row still exists, no MERGE row in
  `cars_hist`, and the source's files are still in place

### Live-DB verification for the new `updateImage()` CAS call

Mocked unit tests can only assert the arguments passed. Add a direct-call
integration test: invoke `CarRepository::updateImage()` against a real target
row with a deliberately wrong `expectedJson`, and assert it returns `false`
without throwing and without mutating the row. Mirrors the existing
`testRemoveImageCasConflictThrowsConcurrentModificationException` style. A
true two-connection race test is out of scope — `processIsolation="false"`
gives the suite a single DB connection.

### Fixture sharing

Extract a **trait** (`tests/integration/CarImageFixtureTrait.php`), not an
intermediate base class: both test classes already extend
`IntegrationTestCase` but need different `setUp()` shapes (one car vs. two,
`loginAsTestUser()` vs. not). Trait helpers must be parameterized by directory
— they currently close over `$this->imageDir`, and merge tests need two.

### Hazards under `processIsolation="false"`

- Wrap any transactional test section in try/finally, as
  `testCarRepositoryTransactionRollbackPreservesCarAndOwnerAssignment` already
  does — all tests share one DB connection.
- Restore `chmod(..., 0700)` unconditionally in `tearDown()` (guarded by
  `is_dir()`), before `parent::tearDown()`. `recursiveRemoveDirectory()` only
  writes to STDERR on failure, so a permission-locked temp dir leaks silently
  across runs otherwise.
- Skip the chmod-based failure test when running as root
  (`posix_getuid() === 0`, guarded by `function_exists`) — root bypasses
  permission bits, turning the test into a silent false pass. CI
  (`ubuntu-latest`, no `container:`) runs as the non-root `runner` user, so the
  test is meaningful there.
- After a successful `merge()`, the source car row is already gone — untrack it
  so `tearDown()` doesn't log a spurious cleanup `NOTE:`, following the
  existing `deleteTestCar()` pattern.
- Name the two temp image dirs by car ID under one random `tempRoot` per test
  method, as the existing single-car test does.

## Documentation Plan

| File | Change |
| --- | --- |
| `CLAUDE.md` | Extend the Car Image Storage bullet (Key Integration Points) to state that a car merge relocates files from `userimages/{sourceId}/` to `userimages/{targetId}/` and appends the moved filenames to the target's `cars.image` |
| `docs/development/CLASSES.md` | New `CarImageRelocator` entry following the `CarRepository` format (Location / Namespace / Purpose / methods) |
| wiki `File-Storage-and-Image-Handling.md` | Add merge relocation to the "Car Image Storage" section: when it happens, what happens to the source files, collision handling, and the compensation-on-failure guarantee. (Note: the AC named the "Architecture Guide"; the image-storage content actually lives in this page.) |
| `docs/releases/RELEASE_NOTES_v2.30.1.md` | Finalize the existing #1867 entry under "Issues Resolved" — drop the `WIP:` prefix |

`DATABASE.md` needs no change (no schema change). `ERROR_HANDLING.md` needs no
change — no new exception type is introduced; `ImageProcessingException` and
`CarMergeException` already exist and are listed. No ADR is required: this
changes behavior within the established image-storage design rather than
altering an architectural decision.
