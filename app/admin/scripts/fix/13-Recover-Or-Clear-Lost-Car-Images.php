<?php

declare(strict_types=1);

use ElanRegistry\Admin\BackupManager;
use ElanRegistry\Exceptions\BackupException;
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
 * FINDING (2026-09-01, during outreach-email drafting): car 1739's photos
 * are NOT actually lost. History shows car 1738 was deleted and
 * immediately recreated as car 1739 at the identical timestamp
 * (2025-05-14 16:11:40) — a duplicate/re-save in the same request. The
 * pipeline-processed image files from that upload were written to
 * userimages/1738/ (the pre-recreation car ID) and never moved to
 * userimages/1739/ when the ID changed. All 5 originals plus all 5
 * resized derivatives (100/300/600/1024/2048) are intact under the old
 * ID. Confirmed via a production userimages rsync + a same-day production
 * DB dump import into a local scratch schema — not guessed from the
 * dump alone.
 *
 * Cars 1049, 1455, and 1670 have no matching files anywhere in
 * production userimages (verified by filename search across the full
 * tree, including userimages/orphan/) — genuinely unrecoverable, matches
 * #1800 as filed. This script clears their `cars.image` reference to
 * `[]` so the car page renders its placeholder instead of a 404, per
 * #1800's acceptance criteria for the "owner has no originals to give us"
 * / no-response path. The owner-outreach emails (see
 * docs/plans/issue-1800-owner-emails.md on milestone/v2.29.6) still go out
 * for 1049/1455/1670 — this script does not skip contacting those owners,
 * it only pre-clears the reference so their entry isn't broken while they
 * decide whether to re-upload. If an owner does re-upload, the normal
 * upload flow overwrites this `[]` value.
 *
 * ROLLBACK: Restore `cars` from the BackupManager snapshot created at
 * script start. The file move (STEP 1) is also reversible — this script
 * copies rather than deletes the source files under userimages/1738/, so
 * the originals remain there until manually removed in a later, separate
 * cleanup pass (out of scope here — see #1222, the general orphan/
 * unreferenced-file repair tool).
 *
 * DEPLOYMENT INSTRUCTIONS:
 * Run once on test (if a test-environment copy of userimages/1738/ exists
 * — otherwise STEP 1 will report 0 files found and skip cleanly, which is
 * expected and fine for a test-only dry run of STEP 2/3), verify, then run
 * once on prod via Admin → Maintenance tab. This script is expected to run
 * exactly once; it is idempotent for STEP 2/3 (the optimistic-concurrency
 * WHERE clause in CarRepository::updateImage() means a second run simply
 * reports 0 rows updated once cars.image no longer matches its
 * pre-migration value) but STEP 1's file copy will re-copy (harmless,
 * same bytes) if re-run before the manual cleanup pass removes the
 * source directory.
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

// Car 1739's recovered image set — pipeline-processed filenames, confirmed
// present (original + all 5 resized derivatives) under the pre-recreation
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
const RESIZED_SUFFIXES = ['-resized-100', '-resized-300', '-resized-600', '-resized-1024', '-resized-2048'];

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
                                photos (found intact under a stale pre-recreation car ID)
                                and clears the broken image reference on the three cars
                                whose files are genuinely gone.
                            </p>

                            <div class="alert alert-info">
                                <h5><i class="fa fa-info-circle"></i> What this script does:</h5>
                                <ul class="mb-0">
                                    <li>Copies 5 original images + 25 resized derivatives from
                                        <code>userimages/1738/</code> to
                                        <code>userimages/1739/</code></li>
                                    <li>Updates car 1739's <code>cars.image</code> column to the
                                        recovered filenames (optimistic-concurrency guarded —
                                        no-ops if the column no longer matches its known
                                        pre-migration value)</li>
                                    <li>Clears <code>cars.image</code> to <code>[]</code> for cars
                                        1049, 1455, and 1670 (same guard) so their pages render
                                        the placeholder instead of a 404</li>
                                    <li>Creates a <code>cars</code> table backup before any write</li>
                                    <li>Logs a summary of files copied and rows updated</li>
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

                        $sourceDir = $abs_us_root . 'userimages/' . RECOVERED_FROM_CAR_ID . '/';
                        $destDir = $abs_us_root . 'userimages/' . RECOVERED_CAR_ID . '/';

                        if (!is_dir($sourceDir)) {
                            logProgress("Source directory not found: {$sourceDir} — skipping copy (expected on an environment without a copy of production userimages, e.g. test)", 'warning');
                            $results['files_missing'] += count(RECOVERED_FILENAMES) * (1 + count(RESIZED_SUFFIXES));
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

                                    if (!file_exists($src)) {
                                        logProgress("Missing (expected on non-prod): {$variant}", 'warning');
                                        $results['files_missing']++;
                                        continue;
                                    }

                                    if (file_exists($dest)) {
                                        logProgress("Already present at destination, skipping: {$variant}", 'info');
                                        continue;
                                    }

                                    if (!copy($src, $dest)) {
                                        logProgress("FAILED to copy: {$variant}", 'error');
                                        $results['errors']++;
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

                        $car1739Row = $db->query('SELECT image FROM cars WHERE id = ?', [RECOVERED_CAR_ID])->first();
                        if ($car1739Row === null) {
                            logProgress('Car ' . RECOVERED_CAR_ID . ' not found — skipping image update', 'warning');
                            $results['rows_skipped']++;
                        } else {
                            $expectedJson = (string) $car1739Row->image;
                            $newJson = json_encode(RECOVERED_FILENAMES, JSON_UNESCAPED_SLASHES);

                            if ($expectedJson === $newJson) {
                                logProgress('Car ' . RECOVERED_CAR_ID . ' already has the recovered filenames — nothing to update', 'info');
                            } else {
                                $updated = $carRepository->updateImage(RECOVERED_CAR_ID, $newJson, $expectedJson);
                                if ($updated) {
                                    logProgress('Car ' . RECOVERED_CAR_ID . " image column updated to recovered filenames", 'success');
                                    $results['rows_updated']++;
                                } else {
                                    logProgress('Car ' . RECOVERED_CAR_ID . " image column did not match expected pre-migration value ({$expectedJson}) — not updated (likely already handled or changed since this script was written)", 'warning');
                                    $results['rows_skipped']++;
                                }
                            }
                        }

                        // STEP 4: Clear cars.image for the unrecoverable cars
                        logProgress('', 'info');
                        logProgress(SECTION_SEPARATOR, 'step');
                        logProgress('STEP 4: Clear cars.image for unrecoverable cars (' . implode(', ', UNRECOVERABLE_CAR_IDS) . ')', 'step');
                        logProgress(SECTION_SEPARATOR, 'step');

                        foreach (UNRECOVERABLE_CAR_IDS as $carId) {
                            $row = $db->query('SELECT image FROM cars WHERE id = ?', [$carId])->first();
                            if ($row === null) {
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
                                logProgress("Car {$carId} image column did not match expected pre-migration value ({$expectedJson}) — not updated (likely already handled or changed since this script was written)", 'warning');
                                $results['rows_skipped']++;
                            }
                        }

                        // Log script completion
                        $db->insert('fix_script_runs', [
                            'script_name' => '13-Recover-Or-Clear-Lost-Car-Images.php',
                            'completed_at' => date('Y-m-d H:i:s'),
                        ]);

                        logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT,
                            "Recover-or-clear lost car images completed - Files copied: {$results['files_copied']}, Files missing: {$results['files_missing']}, Rows updated: {$results['rows_updated']}, Rows skipped: {$results['rows_skipped']}, Errors: {$results['errors']}");

                        // Display summary
                        logProgress('', 'info');
                        logProgress(SECTION_SEPARATOR, 'step');
                        logProgress('MIGRATION COMPLETE', 'step');
                        logProgress(SECTION_SEPARATOR, 'step');
                        logProgress("Files copied: {$results['files_copied']}", 'success');
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
                        logProgress('  • Send the #1800 outreach emails for cars ' . implode(', ', UNRECOVERABLE_CAR_IDS) . ' (Martin Boysen / car ' . RECOVERED_CAR_ID . ' no longer needs one)', 'info');
                        logProgress('  • Once confirmed stable, manually remove userimages/' . RECOVERED_FROM_CAR_ID . '/ in a separate cleanup pass (tracked under #1222, not this script)', 'info');

                    } catch (BackupException $e) {
                        logProgress('FATAL ERROR creating backup: ' . $e->getMessage(), 'error');
                        logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT, 'Fatal backup error: ' . $e->getMessage());
                    } catch (Exception $e) {
                        logProgress('FATAL ERROR: ' . $e->getMessage(), 'error');
                        logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT, 'Fatal error: ' . $e->getMessage());
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
