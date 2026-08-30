# Issue #1452: Compensating cleanup for non-atomic car-create image moves

**Branch:** `bug/1452-non-atomic-car-create-image-cleanup`
**Milestone:** `milestone/v2.29.6`
**Status:** Implemented — pending commit/PR

## Context

`app/api/cars/save.php`'s `addCar` flow calls `addCar()` (writes `cars.image`
JSON to the DB) then `mvTmpImages()` (moves the uploaded files out of
`userimages/temp/`). If a move fails — `mkdir()` or `rename()` returns false —
`mvTmpImages()` appends to `$errors` but never touches `cardetails['image']`
or the DB row. The endpoint responds with a server error, but the already-committed
`cars` row still lists filenames that were never moved into place. This is
assessment finding **P8** and the probable root cause of tracked #1403 (6 cars
with missing image files on disk).

Reordering (move files before writing `cars.image`) is deliberately deferred to
issue #1143/v2.30.0, where it will be bundled with the planned `CarSaveService`
extraction. This issue's scope is strictly compensating cleanup: after a move
failure, strip the unmoved filenames from `cardetails['image']` and update the
DB row so it never references files that don't exist on disk.

## Bug Escape Analysis

- **Root cause:** `mvTmpImages()` was written to report file-move failures via
  `$errors[]` for the HTTP response, but never reconciles the already-committed
  `cars.image` column against what actually landed on disk. The write-then-move
  ordering combined with a fire-and-forget move step is the gap.
- **Testing gap:** no integration test exercises the move-failure path at all.
  `CarImageLifecycleTest.php` covers `CarImageProcessor::removeImage()`'s CAS
  behavior and known gaps (#1629), but nothing simulates a `rename()` failure
  inside `mvTmpImages()` or asserts what ends up in `cars.image` afterward.
  `CarActionsSaveWiringTest.php` explicitly excludes `addCar`/`mvTmpImages`
  database-backed behavior from its scope.
- **Preventive measures:** new integration test simulating a move failure
  (e.g. making the destination directory unwritable via `chmod`), asserting
  the DB row's `image` column no longer lists the unmoved filename after the
  request completes.

## UserSpice Integration

None — this is project-owned application code (`ElanRegistry\Car\*`), not
UserSpice framework surface.

## Database & Security Considerations

- No schema changes.
- No new auth/CSRF surface — same authenticated `addCar` action.
- Uses the existing CAS-protected `CarRepository::updateImage()` — no new SQL,
  no new injection surface.
- Per user decision: on a CAS conflict during this cleanup write (a car row
  edited concurrently within the same request window — near-impossible in
  practice, since the row was just created earlier in this same request), log
  it (`LOG_CATEGORY_FILE_ERROR`) and leave it in `$errors[]`; no retry loop.
  The response already reports failure via the existing "images could not be
  moved" path.

## Architecture & Design

Existing pattern: `CarImageProcessor` owns all `cars.image` JSON
read/modify/write; `CarRepository::updateImage()` is the sole CAS-protected
column writer; `Car` exposes caller-facing wrappers (e.g. `removeImage()`)
that delegate to `CarImageProcessor`. This fix follows the same shape rather
than hand-rolling JSON surgery in `save.php`.

**New method: `CarImageProcessor::removeImages(object $carData, array $filenames): array`**
(plural — distinct from the existing singular `removeImage()`), modeled directly
on `removeImage()` (`usersc/classes/Car/CarImageProcessor.php:315-352`):

- Decode `$carData->image` using the same dual JSON/CSV-fallback logic already
  used by `removeImage()` and `mvTmpImages()` (`json_decode(...) ?? explode(',', ...)`).
- Remove every filename in `$filenames` that's present (`array_diff`), re-index.
- Re-encode with the same empty-list-becomes-`''` convention `removeImage()`
  uses (`empty($currentImages) ? '' : json_encode($currentImages)`).
- If the resulting JSON differs from `$carData->image`, call
  `$this->repo->updateImage((int) $carData->id, $imageJson, $carData->image)`.
- **Differs from `removeImage()` on CAS failure**: return a result array
  (e.g. `['updated' => bool, 'casConflict' => bool]`) instead of throwing
  `CarConcurrentModificationException` — this path must not blow up the
  in-flight `addCar` response; the caller already has an `$errors[]` array to
  append to. On success, update `$carData->image` in place same as
  `removeImage()` does.
- If `$filenames` is empty or none match, short-circuit and return
  `['updated' => false, 'casConflict' => false]` — no-op, matches
  `removeImage()`'s not-found `return false` spirit.

**New method: `Car::removeImages(array $filenames): array`**
(`usersc/classes/Car/Car.php`, mirroring `removeImage()` at line 418):

- `if (!$this->exists()) throw new CarNotFoundException(...)`
- Delegate to `$this->getImageProcessor()->removeImages($this->_data, $filenames)`
- On `['updated' => true, ...]`, clear `$this->_images = null` (cache
  invalidation, same as `removeImage()`)
- Return the result array to the caller

**`mvTmpImages()` changes** (`app/api/cars/save.php:849-894`):

- Track which filenames from the decoded `$carImages` list failed to move —
  either the `rename()` failure at line 888, or (for consistency/defense) the
  legacy-format skip at lines 873-883, which also never lands a temp file.
  Collect these into a local `$unmovedFilenames = []` array as the existing
  loop runs (append inside both failure branches).
- After the loop, if `$unmovedFilenames` is non-empty:

  ```php
  if (!empty($unmovedFilenames)) {
      $car = new Car((int) $cardetails['id']);
      $result = $car->removeImages($unmovedFilenames);
      if ($result['updated']) {
          $cardetails['image'] = $car->data()->image;
      } elseif ($result['casConflict']) {
          logger($userId, LogCategories::LOG_CATEGORY_FILE_ERROR,
              "mvTmpImages: CAS conflict cleaning up unmoved images for car ID {$cardetails['id']}");
      }
  }
  ```

  (Exact accessor for the refreshed `image` value depends on what `Car::data()`
  exposes post-update — confirm during implementation that `$carData->image`
  set inside `removeImages()` is reachable via `$car->data()->image` after the
  call, since `Car::create()` and `removeImages()` operate on the same
  `$this->_data` object reference. If not directly reachable, thread the
  updated JSON back through the result array instead, e.g.
  `['updated' => bool, 'casConflict' => bool, 'image' => string]`.)
- `$errors[]` is already non-empty at this point (from the original `rename()`/
  `mkdir()` failure reporting) — no change needed there; the existing
  `serverError('Car saved but images could not be moved from temp storage')`
  response already fires. This step only prevents the **DB row** from lying
  about what's on disk; it doesn't change the HTTP response contract.

**Why not touch the `mkdir()` failure branch (line 856-860):** that branch
`return`s immediately before any images are processed, meaning `$carImages`
is never decoded and no per-file cleanup list can be built there. If `mkdir()`
fails, every image referenced by `cardetails['image']` is unmoved — handle
this by clearing the entire `image` column via the same `removeImages()` call,
passing the full decoded filename list. To keep this uniform, decode
`$carImages` **before** the `mkdir()` check instead of after, so both failure
branches share one `$carImages` list and one cleanup call at the end of the
function (single exit path instead of an early `return`).

## Implementation Checklist

- [x] Add `CarImageProcessor::removeImages(object $carData, array $filenames): array`
      — `usersc/classes/Car/CarImageProcessor.php:355-401` (parallel-safe)
- [x] Add `Car::removeImages(array $filenames): array` — `usersc/classes/Car/Car.php:434-459`
      (depends on: CarImageProcessor::removeImages)
- [x] Refactor `mvTmpImages()`: decode `$carImages` before the `mkdir()` check,
      track unmoved filenames across both the `mkdir()`-failure and per-file
      `rename()`-failure/legacy-skip branches, call `Car::removeImages()` once
      at the end when any filenames are unmoved, log CAS conflicts —
      `app/api/cars/save.php:849-903` (depends on: Car::removeImages) — traced
      `Car::data()` returns the same `$this->_data` object reference mutated by
      `CarImageProcessor::removeImages()`, so `$car->data()->image` correctly
      reflects the post-cleanup value; no fallback `'image'` result key needed.
- [x] Add integration test: simulate a `rename()` failure (e.g. chmod the
      destination directory read-only) during `addCar`, assert the resulting
      `cars.image` DB value no longer lists the unmoved filename —
      `tests/integration/CarImageLifecycleTest.php::testRemoveImagesStripsUnmovedFilenameAfterSimulatedRenameFailure`
      (tests at the `CarImageProcessor::removeImages()` level — `mvTmpImages()`
      needs a full `users/init.php` bootstrap this harness doesn't load; docblock
      is explicit about the substitution)
- [x] Add integration test: simulate an `mkdir()` failure for the destination
      directory, assert `cars.image` ends up empty (`''`) rather than
      referencing files that were never moved —
      `::testRemoveImagesClearsImageColumnWhenAllFilenamesUnmovedAfterSimulatedMkdirFailure`
- [x] Add integration test: CAS-conflict path during cleanup (mirroring
      `testRemoveImageCasConflictThrowsConcurrentModificationException`'s
      setup) — assert `removeImages()` returns `casConflict: true` and does
      not throw — `::testRemoveImagesReportsCasConflictWithoutThrowing`. All 9
      tests in the file pass (`OK (9 tests, 90 assertions)`), PHPStan clean.
- [x] PHPStan baseline hygiene: confirm no touched file carries pre-existing
      `phpstan-baseline.neon` entries (fix or explicitly defer per
      `/execute-plan` Step 6.5) — clean, no overrides on touched files
- [x] Run `/security-review` (touches file-move/DB-write error path), address
      Critical/High — 0 Critical/High/Medium; 1 cosmetic Low (`array_diff` vs
      strict comparison in `removeImages()`, confirmed not exploitable, not fixed)
- [x] Run `senior-architect` review of the diff, address findings — 1 High
      (docs/development/CLASSES.md not updated per plan's Documentation Plan)
      fixed by adding `removeImage`/`removeImages` rows and correcting the
      stale `decode()` method-name reference. 1 Medium advisory (no test drives
      real `mvTmpImages()` wiring, only `CarImageProcessor::removeImages()`
      directly — accepted trade-off per plan, noted for PR description) and
      2 Low/informational findings (unvalidated filenames from mkdir-failure
      branch flow into a safe sink; CAS-conflict-with-empty-$errors edge case
      in the legacy-skip sub-case) — both advisory, not fixed, noted for PR
      description per plan's accepted scope.

## Test Plan

- New/extended `tests/integration/CarImageLifecycleTest.php` coverage:
  - `rename()` failure → unmoved filename stripped from `cars.image`
  - `mkdir()` failure → `cars.image` ends up empty, not left referencing
    unmoved files
  - CAS conflict during cleanup → logged, no exception, `$errors[]`-compatible
    return
- Existing `CarImageLifecycleTest.php` tests (`testUploadWritesVariantsAndUpdatesImageJson`,
  `testRemoveImage*`) continue passing unchanged — this fix adds a sibling
  method (`removeImages`, plural) rather than modifying `removeImage`.
- `tests/unit/security/ImageFilenameAllowlistTest.php`'s existing coverage of
  the legacy-filename-skip branch in `mvTmpImages()` continues passing — the
  refactor only adds bookkeeping around collected unmoved filenames, not
  behavior changes to the allowlist check itself.

## Documentation Plan

- Update `docs/development/CLASSES.md`'s `CarImageProcessor` quick-reference
  table to add `removeImages()` alongside the existing `removeImage()` entry.
- No wiki or ADR impact — this is an internal reliability fix, not a new
  user-facing or architectural concept.
