<?php

declare(strict_types=1);

use ElanRegistry\Admin\BackupManager;
use ElanRegistry\Exceptions\BackupException;
use ElanRegistry\LogCategories;

/**
 * Trim Cars Column Whitespace
 *
 * One-time data migration that trims leading/trailing whitespace from nine
 * string columns on the cars table.
 * Issue #1491: tech-debt: legacy trailing/leading whitespace in cars table
 * string columns
 *
 * ROLLBACK: Restore from the BackupManager snapshot created at script start.
 *
 * DEPLOYMENT INSTRUCTIONS:
 * Run once on test, verify, then run once on prod via Admin → Maintenance tab.
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

const CARS_TRIM_COLUMNS = ['color', 'comments', 'variant', 'series', 'chassis', 'city', 'state', 'fname', 'lname'];

/**
 * Count rows in cars where $column has leading/trailing whitespace.
 *
 * @throws \InvalidArgumentException If $column is not in the CARS_TRIM_COLUMNS allowlist
 * @throws \RuntimeException         If the count query fails
 */
function countColumnAffected(object $db, string $column): int
{
    if (!in_array($column, CARS_TRIM_COLUMNS, true)) {
        throw new \InvalidArgumentException("Disallowed column: {$column}");
    }
    $sql    = "SELECT COUNT(*) AS cnt FROM cars WHERE LENGTH(`{$column}`) != LENGTH(TRIM(`{$column}`))";
    $result = $db->query($sql);
    if ($db->error()) {
        throw new \RuntimeException("DB error counting affected rows in cars.{$column}: " . $db->errorString());
    }
    return (int) ($result->first()->cnt ?? 0);
}

/**
 * Trim leading/trailing whitespace from $column on all affected cars rows.
 *
 * @throws \InvalidArgumentException If $column is not in the CARS_TRIM_COLUMNS allowlist
 * @throws \RuntimeException         If the update query fails
 */
function trimColumn(object $db, string $column): int
{
    if (!in_array($column, CARS_TRIM_COLUMNS, true)) {
        throw new \InvalidArgumentException("Disallowed column: {$column}");
    }
    $sql = "UPDATE cars SET `{$column}` = TRIM(`{$column}`) WHERE LENGTH(`{$column}`) != LENGTH(TRIM(`{$column}`))";
    $db->query($sql);
    if ($db->error()) {
        throw new \RuntimeException("DB error trimming cars.{$column}: " . $db->errorString());
    }
    return $db->count();
}

$isProcessing = admin_script_exec_requested();

?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">

            <?php if ($method === 'POST' && !$isProcessing): ?>
            <!-- CSRF token mismatch or non-admin -->
            <div class="row">
                <div class="col-lg-12">
                    <div class="alert alert-danger">
                        <h5><i class="fa fa-exclamation-circle"></i> Security Token Error</h5>
                        <p>The request token was invalid or expired. Please return to the script and try again.</p>
                        <a href="<?php echo htmlspecialchars($php_self); ?>" class="btn btn-primary">
                            <i class="fa fa-arrow-left"></i> Return to Script
                        </a>
                    </div>
                </div>
            </div>

            <?php elseif (!$isProcessing): ?>
            <!-- Pre-flight: description and affected row counts -->
            <div class="row">
                <div class="col-lg-12 mb-4">
                    <div class="card registry-card">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fa fa-text-width"></i> Trim Cars Column Whitespace
                            </h2>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">
                                One-time data migration that trims leading/trailing whitespace from nine
                                string columns on the <code>cars</code> table.
                            </p>

                            <div class="alert alert-info">
                                <h5><i class="fa fa-info-circle"></i> What this script does:</h5>
                                <ul class="mb-0">
                                    <li>Backs up <code>cars</code> before any writes.</li>
                                    <li>
                                        Trims whitespace from <code>cars</code>:
                                        <code><?php echo implode('</code>, <code>', CARS_TRIM_COLUMNS); ?></code>.
                                    </li>
                                    <li>Uses <code>TRIM()</code> — only leading/trailing whitespace is removed, internal content is untouched.</li>
                                </ul>
                            </div>

                            <?php
                            $preflightCounts = [];
                            $totalAffected   = 0;

                            foreach (CARS_TRIM_COLUMNS as $column) {
                                $n = countColumnAffected($db, $column);
                                $preflightCounts[$column] = $n;
                                $totalAffected += $n;
                            }
                            ?>

                            <div class="alert alert-<?php echo $totalAffected > 0 ? 'warning' : 'success'; ?>">
                                <h5>
                                    <i class="fa fa-<?php echo $totalAffected > 0 ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                                    Pre-flight: Affected Rows
                                </h5>
                                <?php if ($totalAffected === 0): ?>
                                    <p class="mb-0">
                                        All columns are already clean — no leading/trailing whitespace found.
                                        Running the script again will report zero rows changed.
                                    </p>
                                <?php else: ?>
                                    <table class="table table-sm table-bordered mb-0 mt-2" style="max-width: 400px;">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>Column</th>
                                                <th class="text-right">Affected Rows</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($preflightCounts as $column => $cnt): ?>
                                                <?php if ($cnt > 0): ?>
                                                <tr>
                                                    <td><code><?php echo htmlspecialchars($column); ?></code></td>
                                                    <td class="text-right"><?php echo $cnt; ?></td>
                                                </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <tr class="font-weight-bold">
                                                <td>Total column-rows affected</td>
                                                <td class="text-right"><?php echo $totalAffected; ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>

                            <div class="alert alert-warning">
                                <h5><i class="fa fa-exclamation-triangle"></i> Important Notes:</h5>
                                <ul class="mb-0">
                                    <li>Run <strong>once</strong> on test, verify, then run once on prod.</li>
                                    <li>A backup of the <code>cars</code> table is created automatically before any writes.</li>
                                    <li>The script is idempotent — a second run will report zero rows changed.</li>
                                    <li><code>cars_hist</code> audit history is intentionally left unchanged.</li>
                                </ul>
                            </div>

                            <div class="text-center">
                                <?= admin_script_start_form('Continue - Start Trim Migration') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php else:
                // CSRF validated — begin migration output
                ob_end_clean();
                header('Content-Type: text/html; charset=utf-8');
                echo str_repeat(' ', 1024);
                flush();
            ?>

            <div class="card registry-card">
                <div class="card-header">
                    <h2 class="mb-0">
                        <i class="fa fa-cogs"></i> Trimming Cars Column Whitespace
                    </h2>
                    <small class="text-muted">
                        <i class="fa fa-clock-o"></i> Started: <?php echo date('Y-m-d H:i:s'); ?>
                    </small>
                </div>
                <div class="card-body">
                    <pre style="background: #f5f5f5; padding: 15px; border-radius: 4px; max-height: 600px; overflow-y: auto; font-family: 'Courier New', monospace; font-size: 13px;"><?php

                    $results = [
                        'processed' => 0,
                        'errors'    => 0,
                        'warnings'  => 0,
                    ];

                    $changeCounts = [];
                    $backupPath   = null;

                    logProgress(SECTION_SEPARATOR, 'step');
                    logProgress('STEP 1: Create backup of cars', 'step');
                    logProgress(SECTION_SEPARATOR, 'step');

                    try {
                        $backupPath = $backupManager->createManualBackup(
                            'Trim cars column whitespace — issue #1491',
                            ['cars']
                        );
                        logProgress('Backup created: ' . basename($backupPath), 'success');
                        logger(
                            $user->data()->id,
                            LogCategories::LOG_CATEGORY_FIX_SCRIPT,
                            '12 trim: backup created at ' . $backupPath
                        );
                    } catch (BackupException $e) {
                        $results['errors']++;
                        logProgress('FATAL: Backup failed — aborting migration. No data was modified.', 'error');
                        logProgress('Error: ' . $e->getMessage(), 'error');
                        logger(
                            $user->data()->id,
                            LogCategories::LOG_CATEGORY_FIX_SCRIPT,
                            '12 trim: backup failed, migration aborted — ' . $e->getMessage()
                        );
                        $backupPath = null;
                    }

                    if ($backupPath !== null) {
                        try {
                            logProgress('', 'info');
                            logProgress(SECTION_SEPARATOR, 'step');
                            logProgress('STEP 2: Trim whitespace from cars columns', 'step');
                            logProgress(SECTION_SEPARATOR, 'step');

                            // Suppress cars_update's audit-trail trigger for this maintenance
                            // pass — cars_hist is intentionally left unchanged (whitespace-only
                            // correction, not a meaningful edit worth auditing), and without this
                            // every trimmed row would otherwise generate a redundant cars_hist row
                            // per column touched. See the trigger's own docblock in
                            // database/migrations/20260709000000_add_elanregistry_baseline.php.
                            $db->query('SET @disable_triggers = 1');

                            foreach (CARS_TRIM_COLUMNS as $column) {
                                $changed = trimColumn($db, $column);
                                logProgress("cars.{$column}: {$changed} row(s) updated", $changed > 0 ? 'success' : 'info');
                                $changeCounts[$column] = $changed;
                                $results['processed'] += $changed;
                            }

                            $db->query('SET @disable_triggers = NULL');

                            admin_script_record_completion(
                                __FILE__,
                                (int) $user->data()->id,
                                function (string $msg) use (&$results): void {
                                    $results['warnings']++;
                                    logProgress($msg, 'warning');
                                }
                            );

                            logger(
                                $user->data()->id,
                                LogCategories::LOG_CATEGORY_FIX_SCRIPT,
                                '12 trim: completed — ' . json_encode($changeCounts)
                            );

                            logProgress('', 'info');
                            logProgress(SECTION_SEPARATOR, 'step');
                            logProgress('POST-RUN REPORT', 'step');
                            logProgress(SECTION_SEPARATOR, 'step');

                            logProgress('Rows changed per column:', 'info');
                            foreach ($changeCounts as $column => $n) {
                                logProgress("  cars.{$column}: {$n}", $n > 0 ? 'success' : 'info');
                            }

                            logProgress('Backup file: ' . basename($backupPath), 'info');

                            logProgress('', 'info');
                            logProgress(SECTION_SEPARATOR, 'step');
                            logProgress('Migration complete.', 'step');
                            logProgress(SECTION_SEPARATOR, 'step');
                            logProgress("Total rows updated: {$results['processed']}", 'success');

                            if ($results['warnings'] > 0) {
                                logProgress("Warnings: {$results['warnings']}", 'warning');
                            }

                        } catch (\Exception $e) {
                            $results['errors']++;
                            logProgress('FATAL ERROR: ' . $e->getMessage(), 'error');
                            logger(
                                $user->data()->id,
                                LogCategories::LOG_CATEGORY_FIX_SCRIPT,
                                '12 trim: fatal error — ' . $e->getMessage()
                            );
                            // Always clear the trigger-suppression flag, even on a mid-loop
                            // failure, so a later query on this connection doesn't silently
                            // skip audit-trail writes.
                            $db->query('SET @disable_triggers = NULL');
                        }
                    }

                    if ($results['errors'] > 0) {
                        logProgress("Errors: {$results['errors']}", 'error');
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
