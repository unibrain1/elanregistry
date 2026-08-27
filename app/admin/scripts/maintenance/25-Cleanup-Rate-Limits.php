<?php

declare(strict_types=1);

use ElanRegistry\LogCategories;

/**
 * Rate Limit Log Cleanup Script (v2.29.2)
 *
 * Manual, admin-triggered maintenance script (no cron). Removes `us_rate_limits`
 * rows older than 24 hours via `RateLimit::cleanup()` — the same call already
 * available from the "Cleanup Logs" modal on the Rate Limiting Control Center
 * (`users/views/_admin_rate_limits.php`). This script exposes that same cleanup
 * as a standalone, repeatable maintenance script so it can be run on demand
 * without visiting the rate-limiting dashboard, per project convention for
 * repeatable maintenance scripts (see CLAUDE.md).
 *
 * Run periodically — e.g. whenever `us_rate_limits` table growth warrants it.
 * Issue #1582 (originally reported as tech-debt in #1583, consolidated into #1582).
 */
require_once '../../../../users/init.php';
require_once $abs_us_root . $us_url_root . 'app/admin/includes/fix-script-core.php';
require_once $abs_us_root . $us_url_root . 'users/includes/template/prep.php';

if (!securePage($php_self)) {
    die();
}

if (!isAdmin()) {
    logger($user->data()->id, LogCategories::LOG_CATEGORY_SECURITY,
        'Non-admin attempted rate limit cleanup script');
    echo '<div class="alert alert-danger mt-3">Administrator access required.</div>';
    exit;
}

?>

<div id="page-wrapper">
    <div class="container-fluid">
        <div class="well">

            <?php $is_exec = admin_script_exec_requested(); ?>

            <!-- Initial Description Card -->
            <div class="row" id="descriptionSection"<?= $is_exec ? ' style="display:none;"' : '' ?>>
                <div class="col-lg-12 mb-4">
                    <div class="card registry-card">
                        <div class="card-header">
                            <h2 class="mb-0">
                                <i class="fa fa-broom"></i> Rate Limit Log Cleanup
                            </h2>
                        </div>
                        <div class="card-body">
                            <p class="mb-3">Removes rate limit tracking records older than 24 hours from the <code>us_rate_limits</code> table.</p>

                            <div class="alert alert-info">
                                <h5><i class="fa fa-info-circle"></i> What this script does:</h5>
                                <ul class="mb-0">
                                    <li>Deletes <code>us_rate_limits</code> rows with an <code>attempt_time</code> older than 24 hours</li>
                                    <li>Same operation as the "Cleanup Logs" action on the Rate Limiting Control Center</li>
                                    <li>Safe to run repeatedly — it only ever removes already-expired tracking data</li>
                                </ul>
                            </div>

                            <div class="text-center">
                                <?= admin_script_start_form('Run Cleanup') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($is_exec): ?>
                <div class="row mb-4">
                    <div class="col-lg-12">
                        <div class="card registry-card">
                            <div class="card-header">
                                <h2 class="mb-0">
                                    <i class="fa fa-check-circle"></i> Cleanup Result
                                </h2>
                            </div>
                            <div class="card-body">
                                <?php
                                try {
                                    $removed = (new \RateLimit())->cleanup(24);
                                    logger($user->data()->id, LogCategories::LOG_CATEGORY_DATABASE_MAINTENANCE,
                                        "Rate limit cleanup removed {$removed} record(s) older than 24 hours");
                                    $recordingWarning = null;
                                    admin_script_record_completion(__FILE__, (int) $user->data()->id, function (string $msg) use (&$recordingWarning) {
                                        $recordingWarning = $msg;
                                    });
                                    ?>
                                    <div class="alert alert-success mb-0">
                                        <i class="fa fa-check-circle"></i> Removed <strong><?= (int) $removed ?></strong> rate limit record(s) older than 24 hours.
                                    </div>
                                    <?php if ($recordingWarning !== null): ?>
                                    <div class="alert alert-warning mt-2 mb-0">
                                        <?= htmlspecialchars($recordingWarning, ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php
                                } catch (\Throwable $e) {
                                    logger($user->data()->id, LogCategories::LOG_CATEGORY_FIX_SCRIPT_ERROR,
                                        'Rate limit cleanup failed: ' . $e->getMessage());
                                    ?>
                                    <div class="alert alert-danger mb-0">
                                        <i class="fa fa-exclamation-triangle"></i> Cleanup failed: <?= htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <?php
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        </div> <!-- well -->
    </div><!-- Container -->
</div> <!-- page-wrapper -->

<!-- Return to Admin Console button -->
<div style="margin-top: 20px; text-align: center;">
    <?= admin_script_close_button() ?>
</div>

<!-- footers -->
<?php require_once $abs_us_root . $us_url_root . 'usersc/templates/' . $settings->template . '/footer.php'; //custom template footer ?>
