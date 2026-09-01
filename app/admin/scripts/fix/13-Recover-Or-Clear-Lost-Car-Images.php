<?php

declare(strict_types=1);

use ElanRegistry\Admin\BackupManager;
use ElanRegistry\Exceptions\BackupException;
use ElanRegistry\Car\CarImageProcessor;
use ElanRegistry\Car\CarRepository;
use ElanRegistry\LogCategories;

/**
 * Recover Or Clear Lost Car Images
 *
 * One-time data migration for the four cars identified in #1800 as having
 * unrecoverable photos (root cause: #1452 — cars.image stored the raw
 * device filename instead of the pipeline-processed one, so the file was
 * never actually written to userimages/{carid}/).
 *
 * Issue #1800: chore: contact the 4 owners whose car photos were lost and
 * invite re-upload.
 *
 * Car 1739's photos are NOT actually lost. On 2026-09-01 an admin ran the
 * "duplicate" merge action (app/admin/index.php's Manage Cars tab) on cars
 * 1738 and 1739 — confirmed via cars_hist row id 10782, whose comment text
 * is verbatim the $mergeComment template that action produces.
 * CarAdministrationService::merge() correctly transferred car_hist rows and
 * deleted car 1738, but it never touches userimages/ on disk (see #1867,
 * filed to fix that gap for future merges) — so car 1738's photos were left
 * behind under the deleted car's directory instead of following the merge
 * to 1739. All 5 originals plus all 5 resized derivatives
 * (100/300/768/1024/2048) are intact under the old ID.
 *
 * Cars 1049, 1455, and 1670 have no matching files anywhere in
 * production userimages (verified by filename search across the full
 * tree, including userimages/orphan/), and no merge/duplicate history of
 * their own — genuinely unrecoverable, matches #1800 as filed. This script
 * clears their `cars.image` reference to `[]` so the car page renders its
 * placeholder instead of a 404, per #1800's acceptance criteria for the
 * "owner has no originals to give us" / no-response path. The
 * owner-outreach emails (see the Plans repo — not tracked in this repo,
 * see CLAUDE.md's "Working documents... live outside this repo" rule) still
 * go out for 1049/1455/1670 — this script does not skip contacting those
 * owners, it only pre-clears the reference so their entry isn't broken
 * while they decide whether to re-upload. If an owner does re-upload, the
 * normal upload flow overwrites this `[]` value.
 *
 * ROLLBACK: Restore `cars` from the BackupManager snapshot created at
 * script start. The file copy (STEP 2) is also reversible — this script
 * copies rather than deletes the source files under userimages/1738/, so
 * the originals remain there until manually removed in a later, separate
 * cleanup pass (out of scope here — see #1222, the general orphan/
 * unreferenced-file repair tool).
 *
 * DEPLOYMENT INSTRUCTIONS:
 * Run once on test (if a test-environment copy of userimages/1738/ exists
 * — otherwise STEP 2 will report 0 files found and skip cleanly, which is
 * expected and fine for a test-only dry run of STEP 3/4), verify, then run
 * once on prod via Admin → Maintenance tab. This script is expected to run
 * exactly once; it is idempotent for STEP 3/4 (the optimistic-concurrency
 * WHERE clause in CarRepository::updateImage() means a second run simply
 * reports 0 rows updated once cars.image no longer matches its
 * pre-migration value) but STEP 2's file copy will re-copy (harmless,
 * same bytes) if re-run before the manual cleanup pass removes the
 * source directory. STEP 3 (car 1739's image-column update) is gated on
 * STEP 2 completing with zero copy errors — see the $stepTwoErrors guard
 * below — so a partial file copy can never be paired with a DB update that
 * asserts files exist which didn't actually get copied.
 */

require_once '../../../../users/init.php';
require_once $abs_us_root . $us_url_root . 'app/admin/includes/fix-script-core.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($php_self)) {
    die();
}

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline) use ($user): bool {
    if ($errno !== E_DEPRECATED) {
        logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT, "Error [$errno]: $errstr in $errfile:$errline");
    }
    return true;
});

$db = DB::getInstance();
$backupManager = new BackupManager(dbi(), $abs_us_root . $us_url_root . BACKUP_BASE_DIR, (int) $user->data()->id);
$carRepository = new CarRepository(dbi());
$carImageProcessor = new CarImageProcessor($carRepository);

// Car 1739's recovered image set — pipeline-processed filenames, confirmed
// present (original + all 5 resized derivatives) under the merged-away
// car ID 1738. Order matches the car's own cars_hist INSERT row (id 10781).
const RECOVERED_CAR_ID = 1739;
const RECOVERED_FROM_CAR_ID = 1738;
const RECOVERED_FILENAMES = [
    'img_6825232a88b665.46473630.jpg',
    'img_6825232aec6530.87846391.jpg',
    'img_6825232b588081.25095953.jpg',
    'img_6825232bba5509.91496603.jpg',
    'img_6825232c2458d5.38006963.jpg',
];
// Matches ELAN_IMAGE_THUMBNAIL_SIZES (usersc/includes/config.php) — 600 was
// retired in favor of 768 (see app/admin/scripts/maintenance/24-Regenerate-
// Optimized-Thumbnails.php).
const RESIZED_SUFFIXES = ['-resized-100', '-resized-300', '-resized-768', '-resized-1024', '-resized-2048'];

// Cars confirmed to have no matching files anywhere in production
// userimages (including userimages/orphan/) — clear the reference so the
// placeholder renders instead of a 404.
const UNRECOVERABLE_CAR_IDS = [1049, 1455, 1670];

?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">

            <?php if (!admin_script_exec_requested()): ?>
            <!-- Initial Description -->
            <div class="row">
                <div class="col-lg-12 mb-4">
                    <div class="card registry-card">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fa fa-image"></i> Recover Or Clear Lost Car Images
                            </h2>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">
                                One-time data migration for the four cars identified in
                                #1800 as having unrecoverable photos. Recovers car 1739's
                                photos (orphaned under a merged-away car ID by an admin
                                merge action that predates #1867's fix) and clears the
                                broken image reference on the three cars whose files are
                                genuinely gone.
                            </p>

                            <div class="alert alert-info">
                                <h5><i class="fa fa-info-circle"></i> What this script does:</h5>
                                <ul class="mb-0">
                                    <li>Copies 5 original images + 25 resized derivatives from
                                        <code>userimages/1738/</code> to
                                        <code>userimages/1739/</code> — the source files predate
                                        the 768px thumbnail migration, so the retired
                                        <code>-resized-600</code> file is used as a stand-in for
                                        the missing <code>-resized-768</code> (see Post-Processing
                                        Steps for generating a genuine 768px derivative afterward)</li>
                                    <li>Updates car 1739's <code>cars.image</code> column to the
                                        recovered filenames — but only if every file copied
                                        successfully; skips the DB update entirely otherwise so
                                        <code>cars.image</code> never references a file that
                                        isn't actually on disk</li>
                                    <li>Clears <code>cars.image</code> to <code>[]</code> for cars
                                        1049, 1455, and 1670 (optimistic-concurrency guarded — a
                                        mismatch is treated as a concurrent modification worth
                                        investigating, not a routine no-op) so their pages render
                                        the placeholder instead of a 404</li>
                                    <li>Creates a <code>cars</code> table backup before any write</li>
                                    <li>Logs a summary of files copied and rows updated, always
                                        printed even if a step fails partway through</li>
                                </ul>
                            </div>

                            <div class="alert alert-warning">
                                <h5><i class="fa fa-exclamation-triangle"></i> Important Notes:</h5>
                                <ul class="mb-0">
                                    <li>This is a one-time migration for four specific, named car
                                        IDs — it is not a general-purpose repair tool (see #1222
                                        for that)</li>
                                    <li>Source files under <code>userimages/1738/</code> are
                                        copied, not moved — they are left in place for manual
                                        cleanup in a later pass, not deleted by this script</li>
                                    <li>Owners of 1049/1455/1670 still need the outreach email
                                        sent per #1800 — this script only pre-clears the broken
                                        reference, it does not substitute for owner contact</li>
                                    <li>If the summary reports any errors, review them before
                                        sending outreach emails — an error may mean a car's
                                        <code>cars.image</code> changed since this script was
                                        written (e.g. the owner already re-uploaded)</li>
                                </ul>
                            </div>

                            <div class="text-center">
                                <?= admin_script_start_form('Continue - Start Recovery') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php else:
                // Processing mode - simple text output
                ob_end_clean(); // Clear template buffering
                header('Content-Type: text/html; charset=utf-8');
                echo str_repeat(' ', 1024); // Pad to force initial flush
                flush();
            ?>

            <div class="card registry-card">
                <div class="card-header">
                    <h2 class="mb-0">
                        <i class="fa fa-cogs"></i> Recovering / Clearing Lost Car Images
                    </h2>
                    <small class="text-muted">
                        <i class="fa fa-clock-o"></i> Started: <?php echo date('Y-m-d H:i:s'); ?>
                    </small>
                </div>
                <div class="card-body">
                    <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; max-height: 600px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 13px;"><?php

                    $results = [
                        'files_copied' => 0,
                        'files_missing' => 0,
                        'fallback_used' => 0,
                        'rows_updated' => 0,
                        'rows_skipped' => 0,
                        'errors' => 0,
                    ];

                    try {
                        // STEP 1: Backup
                        logProgress(SECTION_SEPARATOR, 'step');
                        logProgress('STEP 1: Backup cars table', 'step');
                        logProgress(SECTION_SEPARATOR, 'step');

                        $backupPath = $backupManager->createSchemaBackup('recover-or-clear-lost-car-images', ['cars']);
                        logProgress('Backup created: ' . basename($backupPath), 'success');
                        logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT, "Backup created: {$backupPath}");

                        // STEP 2: Copy car 1739's recovered files from userimages/1738/
                        logProgress('', 'info');
                        logProgress(SECTION_SEPARATOR, 'step');
                        logProgress('STEP 2: Copy recovered images for car ' . RECOVERED_CAR_ID . ' from car ' . RECOVERED_FROM_CAR_ID . "'s directory", 'step');
                        logProgress(SECTION_SEPARATOR, 'step');

                        // Matches the path construction every other userimages/ writer in this
                        // codebase uses (app/api/cars/save.php, Car.php, thumbnail regen script)
                        // — $abs_us_root alone is DOCUMENT_ROOT, which under MAMP's multi-project
                        // layout is the shared Web/ parent, not this app's own root; $us_url_root
                        // supplies the app's subpath segment (empty on prod, where this app IS
                        // the site root).
                        $sourceDir = $abs_us_root . $us_url_root . ELAN_IMAGE_DIR . RECOVERED_FROM_CAR_ID . '/';
                        $destDir = $abs_us_root . $us_url_root . ELAN_IMAGE_DIR . RECOVERED_CAR_ID . '/';
                        // Gates STEP 3: if any expected file fails to copy, cars.image must
                        // NOT be updated to reference it — a DB write asserting a file exists
                        // that isn't actually on disk would trade a missing-photo placeholder
                        // for a silent 404, which is strictly worse.
                        $stepTwoErrors = 0;

                        if (!is_dir($sourceDir)) {
                            logProgress("Source directory not found: {$sourceDir} — skipping copy (expected on an environment without a copy of production userimages, e.g. test)", 'warning');
                            $results['files_missing'] += count(RECOVERED_FILENAMES) * (1 + count(RESIZED_SUFFIXES));
                            $stepTwoErrors++; // no source dir on prod would also be a real problem — treat as blocking
                        } else {
                            if (!is_dir($destDir)) {
                                if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                                    throw new RuntimeException("Failed to create destination directory: {$destDir}");
                                }
                                logProgress("Created destination directory: {$destDir}", 'info');
                            }

                            foreach (RECOVERED_FILENAMES as $filename) {
                                $variants = [$filename];
                                $pathInfo = pathinfo($filename);
                                $ext = $pathInfo['extension'] ?? 'jpg';
                                $base = $pathInfo['filename'] ?? $filename;
                                foreach (RESIZED_SUFFIXES as $suffix) {
                                    $variants[] = "{$base}{$suffix}.{$ext}";
                                }

                                foreach ($variants as $variant) {
                                    $src = $sourceDir . $variant;
                                    $dest = $destDir . $variant;

                                    if (!file_exists($src) && str_contains($variant, '-resized-768')) {
                                        // These source files predate the 100/300/768/1024/2048
                                        // thumbnail-size migration (app/admin/scripts/maintenance/
                                        // 24-Regenerate-Optimized-Thumbnails.php) and only have the
                                        // retired -resized-600 size. Fall back to copying -resized-600
                                        // as a stand-in -resized-768 rather than leaving the car
                                        // without a 768px derivative at all; script 24 is the correct
                                        // place to later generate a genuine 768px derivative from the
                                        // original once these files are restored.
                                        $fallbackSrc = $sourceDir . str_replace('-resized-768', '-resized-600', $variant);
                                        if (file_exists($fallbackSrc)) {
                                            logProgress("True 768px derivative missing, using -resized-600 as fallback for: {$variant}", 'warning');
                                            $src = $fallbackSrc;
                                            // Tracked separately from files_copied so the summary can
                                            // distinguish "N genuine copies" from "N copies, M of which
                                            // are lower-resolution stand-ins" — otherwise this fact is
                                            // only visible in scrollback at the same log level as
                                            // routine non-prod noise.
                                            $results['fallback_used']++;
                                        }
                                    }

                                    if (!file_exists($src)) {
                                        logProgress("Missing (expected on non-prod): {$variant}", 'warning');
                                        $results['files_missing']++;
                                        $stepTwoErrors++;
                                        continue;
                                    }

                                    if (file_exists($dest)) {
                                        // Size-compared, not just existence-checked: a prior run
                                        // killed mid-copy could leave a truncated file that
                                        // file_exists() alone can't distinguish from a complete
                                        // one.
                                        if (filesize($dest) === filesize($src)) {
                                            logProgress("Already present at destination, skipping: {$variant}", 'info');
                                            continue;
                                        }
                                        logProgress("Destination exists but size differs from source ({$variant}) — re-copying to repair a likely partial prior copy", 'warning');
                                    }

                                    if (!copy($src, $dest)) {
                                        $lastError = error_get_last();
                                        $reason = $lastError['message'] ?? 'unknown reason';
                                        logProgress("FAILED to copy: {$variant} — {$reason}", 'error');
                                        $results['errors']++;
                                        $stepTwoErrors++;
                                        continue;
                                    }

                                    logProgress("Copied: {$variant}", 'success');
                                    $results['files_copied']++;
                                }
                            }
                        }

                        // STEP 3: Update car 1739's cars.image to the recovered filenames
                        logProgress('', 'info');
                        logProgress(SECTION_SEPARATOR, 'step');
                        logProgress('STEP 3: Update cars.image for car ' . RECOVERED_CAR_ID, 'step');
                        logProgress(SECTION_SEPARATOR, 'step');

                        if ($stepTwoErrors > 0) {
                            logProgress('STEP 3 SKIPPED: ' . $stepTwoErrors . ' file(s) failed to copy or were missing in STEP 2 — updating cars.image now would reference files that are not actually on disk.', 'error');
                            $results['errors']++;
                        } else {
                            $car1739Row = $db->query('SELECT image FROM cars WHERE id = ?', [RECOVERED_CAR_ID])->first();
                            if (empty($car1739Row)) {
                                logProgress('Car ' . RECOVERED_CAR_ID . ' not found — skipping image update', 'warning');
                                $results['rows_skipped']++;
                            } else {
                                $expectedJson = (string) $car1739Row->image;
                                $newJson = $carImageProcessor->encodeImages(RECOVERED_FILENAMES);

                                if ($expectedJson === $newJson) {
                                    logProgress('Car ' . RECOVERED_CAR_ID . ' already has the recovered filenames — nothing to update', 'info');
                                } else {
                                    $updated = $carRepository->updateImage(RECOVERED_CAR_ID, $newJson, $expectedJson);
                                    if ($updated) {
                                        logProgress('Car ' . RECOVERED_CAR_ID . " image column updated to recovered filenames", 'success');
                                        $results['rows_updated']++;
                                    } else {
                                        // updateImage() returning false means the optimistic-concurrency
                                        // WHERE clause matched 0 rows. Because this script is intended
                                        // to run exactly once and $expectedJson was read moments ago in
                                        // this same run, a mismatch here means the row changed between
                                        // that read and this write, not routine idempotency — flagged as
                                        // an error, not a shrug-worthy warning, so it isn't missed before
                                        // sending outreach emails. (This reasoning does not generalize to
                                        // a script designed to be re-run.)
                                        logProgress('Car ' . RECOVERED_CAR_ID . " image column changed since this script read it (expected: {$expectedJson}) — another process modified this row during this run. Investigate before proceeding; do not assume this is routine.", 'error');
                                        $results['errors']++;
                                    }
                                }
                            }
                        }

                        // STEP 4: Clear cars.image for the unrecoverable cars
                        logProgress('', 'info');
                        logProgress(SECTION_SEPARATOR, 'step');
                        logProgress('STEP 4: Clear cars.image for unrecoverable cars (' . implode(', ', UNRECOVERABLE_CAR_IDS) . ')', 'step');
                        logProgress(SECTION_SEPARATOR, 'step');

                        foreach (UNRECOVERABLE_CAR_IDS as $carId) {
                            try {
                                $row = $db->query('SELECT image FROM cars WHERE id = ?', [$carId])->first();
                                if (empty($row)) {
                                    logProgress("Car {$carId} not found — skipping", 'warning');
                                    $results['rows_skipped']++;
                                    continue;
                                }

                                $expectedJson = (string) $row->image;
                                $newJson = '[]';

                                if ($expectedJson === $newJson) {
                                    logProgress("Car {$carId} image column already cleared — nothing to update", 'info');
                                    continue;
                                }

                                $updated = $carRepository->updateImage($carId, $newJson, $expectedJson);
                                if ($updated) {
                                    logProgress("Car {$carId} image column cleared (was: {$expectedJson})", 'success');
                                    $results['rows_updated']++;
                                } else {
                                    // Same reasoning as STEP 3: this row was just read in this same
                                    // run, so a mismatch here means a genuine concurrent modification,
                                    // not routine idempotency — e.g. the owner re-uploaded a photo in
                                    // the window between #1800's investigation and this script running.
                                    // If that happened, sending the "your photos are lost" outreach
                                    // email for this car would be actively wrong.
                                    logProgress("Car {$carId} image column changed since this script read it (expected: {$expectedJson}) — another process modified this row during this run. Do NOT send the outreach email for this car until investigated.", 'error');
                                    $results['errors']++;
                                }
                            } catch (\Throwable $e) {
                                // A single car's failure must not abort the remaining cars in this
                                // loop — without this, one transient DB error could leave the
                                // operator unable to tell which of the 3 cars were actually cleared.
                                logProgress("FAILED to process car {$carId}: " . $e->getMessage(), 'error');
                                $results['errors']++;
                                logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT, "Car {$carId} clear failed: " . $e->getMessage());
                            }
                        }

                        logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT,
                            "Recover-or-clear lost car images completed - Files copied: {$results['files_copied']} ({$results['fallback_used']} via -resized-600 fallback), Files missing: {$results['files_missing']}, Rows updated: {$results['rows_updated']}, Rows skipped: {$results['rows_skipped']}, Errors: {$results['errors']}");

                        $fatalError = null;
                    } catch (BackupException $e) {
                        $fatalError = 'FATAL ERROR creating backup: ' . $e->getMessage();
                        logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT, 'Fatal backup error: ' . $e->getMessage());
                    } catch (Exception $e) {
                        $fatalError = 'FATAL ERROR: ' . $e->getMessage();
                        logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT, 'Fatal error: ' . $e->getMessage());
                    }

                    // Uses the shared helper (not a raw $db->insert()) so a failure recording
                    // completion is logged and surfaced as a warning without being misreported
                    // as a failure of the actual recovery work above, which may have already
                    // succeeded.
                    admin_script_record_completion(
                        __FILE__,
                        (int) $user->data()->id,
                        function (string $msg) use (&$results): void {
                            $results['errors']++;
                            logProgress($msg, 'warning');
                        }
                    );

                    // Summary is printed regardless of whether a fatal error occurred above,
                    // so a mid-run failure (e.g. STEP 4's second or third car) doesn't leave the
                    // operator to manually reconstruct which cars were actually processed from
                    // scrollback alone.
                    logProgress('', 'info');
                    logProgress(SECTION_SEPARATOR, 'step');
                    logProgress($fatalError !== null ? 'MIGRATION STOPPED EARLY' : 'MIGRATION COMPLETE', 'step');
                    logProgress(SECTION_SEPARATOR, 'step');
                    if ($fatalError !== null) {
                        logProgress($fatalError, 'error');
                    }
                    logProgress("Files copied: {$results['files_copied']}", 'success');
                    if ($results['fallback_used'] > 0) {
                        // Distinct from the routine "files missing" warning below — this specifically
                        // means N of the copied files are lower-resolution -resized-600 bytes sitting
                        // at a -resized-768 destination path, not a true 768px derivative. Surfaced
                        // here (not just in scrollback) so it can't be missed by reading the summary
                        // alone.
                        logProgress("⚠ Of the above, copied via -resized-600 fallback (NOT true 768px — run script 24 after confirming stable): {$results['fallback_used']}", 'warning');
                    }
                    if ($results['files_missing'] > 0) {
                        logProgress("Files missing (expected on non-prod): {$results['files_missing']}", 'warning');
                    }
                    logProgress("Rows updated: {$results['rows_updated']}", 'success');
                    if ($results['rows_skipped'] > 0) {
                        logProgress("Rows skipped: {$results['rows_skipped']}", 'warning');
                    }
                    if ($results['errors'] > 0) {
                        logProgress("Errors: {$results['errors']}", 'error');
                    }
                    logProgress('', 'info');
                    logProgress('Post-Processing Steps:', 'info');
                    logProgress('  • Verify car ' . RECOVERED_CAR_ID . "'s page renders its 5 recovered photos with no 404s", 'info');
                    logProgress('  • Verify cars ' . implode(', ', UNRECOVERABLE_CAR_IDS) . ' render the placeholder image, not a 404', 'info');
                    logProgress('  • Send the #1800 outreach emails for cars ' . implode(', ', UNRECOVERABLE_CAR_IDS) . ' (car ' . RECOVERED_CAR_ID . ' no longer needs one)', 'info');
                    logProgress('  • Once confirmed stable, manually remove userimages/' . RECOVERED_FROM_CAR_ID . '/ in a separate cleanup pass (tracked under #1222, not this script)', 'info');
                    if ($results['fallback_used'] > 0) {
                        logProgress('  • Run app/admin/scripts/maintenance/24-Regenerate-Optimized-Thumbnails.php to generate a genuine 768px derivative for car ' . RECOVERED_CAR_ID . "'s photos — {$results['fallback_used']} of them are currently the retired -resized-600 file standing in for -resized-768", 'info');
                    }
                    if ($results['errors'] > 0) {
                        logProgress('  • ⚠ Errors were reported above — review each before sending outreach emails or considering this migration complete', 'warning');
                    }

                    ?></pre>

                    <div class="text-center mt-3">
                        <?= admin_script_close_button() ?>
                    </div>
                </div>
            </div>

            <?php endif; ?>

        </div>
    </div>
</div>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>
