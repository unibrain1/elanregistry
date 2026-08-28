<?php
declare(strict_types=1);

use ElanRegistry\Admin\BackupManager;
use ElanRegistry\Admin\MaintenanceStatusLabels;
use ElanRegistry\Exceptions\BackupException;
use ElanRegistry\LogCategories;

/**
 * maintenance.php
 * Registry Maintenance Interface
 *
 * Admin-only management interface focused on system maintenance and
 * registry-wide configuration. Split out from index.php to
 * separate operational maintenance from day-to-day car/owner administration.
 *
 * Single page, no tabs (#1225): backups, one-time migration scripts, and
 * recurring maintenance scripts are all immediately visible. Live health
 * signals (backup attention, pending migrations) surface as header chips
 * and a conditional alert instead of a separate read-only Health screen.
 *
 * (The former "Configuration" tab — image/email/expiry settings — was
 * removed in #1067; those values are now config.php constants / .env vars,
 * not a web-editable DB-backed settings tab. The former "Health" tab was
 * merged into this page in #1225.)
 *
 * Access control is enforced by PageManager (admin-only). All state-changing
 * operations on this page are performed via AJAX endpoints
 * (backup-operations.php), so this page does not process any direct form
 * submissions.
 *
 * @author Elan Registry Development Team
 * @copyright 2025
 */

$pageTitle = 'Registry Maintenance';
$pageDescription = 'Admin tools for registry maintenance, health checks, and system configuration.';

require_once '../../users/init.php';
require_once $abs_us_root . $us_url_root . 'usersc/includes/elanregistry_prep.php';
require_once $abs_us_root . $us_url_root . 'app/admin/includes/system/script-enumeration.php';

// securePage() enforces access via the UserSpice permission_page_matches table.
// This page must be registered with admin-only (permission 2) in the UserSpice
// pages table — the restriction is a DB configuration, not a code-level hard gate.
if (!securePage($php_self)) {
    die();
}

$db = DB::getInstance();

// securePage() handles auth/permission. This guard covers the narrow edge case
// where the session passes securePage() but is corrupt — ensuring the audit trail
// always records a real user ID.
try {
    $currentUserId = currentUserId();
} catch (RuntimeException $e) {
    logger(0, LogCategories::LOG_CATEGORY_SECURITY,
        "Admin page accessed with invalid user session on $php_self");
    Redirect::to($us_url_root . 'users/login.php');
    die();
}

// Generate CSRF token for AJAX requests
$csrfToken = Token::generate();

// Get system status for header (cars + active users only — other counts not
// relevant to maintenance/settings view)
$systemStatus = [
    'total_cars'   => 0,
    'total_users'  => 0,
    'last_updated' => date('Y-m-d H:i:s'),
];

try {
    $systemStatus = getAdminSystemStatus(dbi());
} catch (\Throwable $e) {
    // Header stats are cosmetic — log and degrade gracefully.
    logger($currentUserId, LogCategories::LOG_CATEGORY_DATABASE_ERROR,
           "Error getting system status for maintenance page: " . $e->getMessage());
}

// ============================================================================
// Single computation pass (#1225) — consolidates what tab-health.php and
// tab-maintenance.php each computed separately (near-duplicate try/catch
// around getEnhancedBackupStatistics()). All of it runs once, before any
// markup, and is shared by the header chips, the alert, and all three
// partials below via normal PHP include variable scope.
// ============================================================================

$backupManager = new BackupManager(dbi(), $abs_us_root . $us_url_root . BACKUP_BASE_DIR, (int)$user->data()->id);

$fixDirectory = $abs_us_root . $us_url_root . 'app/admin/scripts/fix/';
$fixScripts   = enumerateScriptFiles($fixDirectory);
rsort($fixScripts, SORT_NATURAL);

$maintenanceDirectory = $abs_us_root . $us_url_root . 'app/admin/scripts/maintenance/';
$maintenanceScripts   = enumerateScriptFiles($maintenanceDirectory);
sort($maintenanceScripts, SORT_NATURAL);

$scriptRunStatus = array_fill_keys($fixScripts, ['has_run' => false, 'last_run' => null]);
$maintenanceRunStatus = array_fill_keys($maintenanceScripts, ['has_run' => false, 'last_run' => null]);

$allScriptNames = array_merge($fixScripts, $maintenanceScripts);
$scriptRunStatusError = false;
if (!empty($allScriptNames)) {
    try {
        $placeholders = implode(',', array_fill(0, count($allScriptNames), '?'));
        $sql = "SELECT script_name, MAX(completed_at) as last_run FROM fix_script_runs WHERE script_name IN (" . $placeholders . ") GROUP BY script_name";
        $runs = $db->query($sql, $allScriptNames)->results();
        foreach ($runs as $row) {
            if (isset($scriptRunStatus[$row->script_name])) {
                $scriptRunStatus[$row->script_name] = ['has_run' => true, 'last_run' => $row->last_run];
            }
            if (isset($maintenanceRunStatus[$row->script_name])) {
                $maintenanceRunStatus[$row->script_name] = ['has_run' => true, 'last_run' => $row->last_run];
            }
        }
    } catch (\Exception $e) {
        $scriptRunStatusError = true;
        logger($user->data()->id, LogCategories::LOG_CATEGORY_DATABASE_MAINTENANCE, 'Failed to batch-check script run status: ' . $e->getMessage());
    }
}

$pendingFixScripts   = array_filter($fixScripts, fn($s) => !$scriptRunStatus[$s]['has_run']);
$completedFixScripts = array_filter($fixScripts, fn($s) =>  $scriptRunStatus[$s]['has_run']);
$pendingMigrations   = count($pendingFixScripts);

/**
 * Human-readable label for a script filename, used by the migrations and
 * maintenance-tasks partials. Strips the leading numeric prefix and file
 * extension, then replaces separators with spaces
 * (e.g. "21-Fix-Page-Permissions.php" -> "Fix Page Permissions").
 */
function scriptDisplayName(string $filename): string
{
    return str_replace(['-', '_'], ' ', preg_replace('/^\d+-/', '', pathinfo($filename, PATHINFO_FILENAME)));
}

$backupStatsFallback = false;

try {
    $backupStats = $backupManager->getEnhancedBackupStatistics();
    $oldBackupsCount = 0;

    if (isset($backupStats['retention_analysis'])) {
        foreach ($backupStats['retention_analysis'] as $typeData) {
            $oldBackupsCount += $typeData['expired'];
        }
    }

    $showCleanupPrompt = $oldBackupsCount > 0;
} catch (\Throwable $e) {
    $category = ($e instanceof BackupException) ? $e->getLogCategory() : LogCategories::LOG_CATEGORY_BACKUP_ERROR;
    logger($user->data()->id, $category, 'Backup stats unavailable: ' . $e->getMessage());
    $backupStatsFallback = true;
    $backupStats = [
        'automated' => ['count' => 0, 'total_size' => 0],
        'manual'    => ['count' => 0, 'total_size' => 0],
        'rollback'  => ['count' => 0, 'total_size' => 0],
        'health_score' => 50,
        'recommendations' => ['Backup system check needed'],
    ];
    $showCleanupPrompt = false;
    $oldBackupsCount = 0;
}

// The fallback path above cannot report on failures it never managed to read, so it
// gets its own degraded status rather than defaulting to a reassuring "Healthy".
$backupStatusUnknown = $backupStatsFallback;
$backupFailureDetected = $backupStats['recent_failures'] ?? false;
$backupNeedsAttention = $backupStatusUnknown || $showCleanupPrompt || $backupFailureDetected;

?>

<div class="page-wrapper">
    <!-- Hidden CSRF token for AJAX requests -->
    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />

    <div class="container-fluid">
        <div class="page-container">

            <!-- Page Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-sm-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <h1 class="h2 mb-2 text-gray-800">
                                <i class="fas fa-tools"></i> Registry Maintenance
                            </h1>
                            <p class="text-muted mb-0">System maintenance, database operations, and registry settings</p>
                        </div>
                        <div>
                            <div class="d-flex gap-3 flex-wrap mb-2">
                                <div class="er-stat-tile">
                                    <div class="er-stat-number"><?= number_format($systemStatus['total_cars']) ?></div>
                                    <div class="er-stat-label">Total Cars</div>
                                </div>
                                <div class="er-stat-tile">
                                    <div class="er-stat-number"><?= number_format($systemStatus['total_users']) ?></div>
                                    <div class="er-stat-label">Total Users</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="d-flex gap-2 flex-wrap justify-content-end mb-2">
                                    <?php if ($backupNeedsAttention): ?>
                                        <a href="#backups-card" class="badge text-bg-warning badge-lg text-decoration-none">
                                            <i class="fas fa-exclamation-triangle"></i>
                                            <?= MaintenanceStatusLabels::chipLabel($backupStatusUnknown, $backupFailureDetected) ?>
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($pendingMigrations > 0): ?>
                                        <a href="#migrations-card" class="badge text-bg-warning badge-lg text-decoration-none">
                                            <i class="fas fa-wrench"></i>
                                            <?= $pendingMigrations ?> Pending Migration<?= $pendingMigrations === 1 ? '' : 's' ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> <?= date('M j, Y g:i A', strtotime($systemStatus['last_updated'])) ?>
                                    &nbsp;<i class="fas fa-code-branch"></i> <?= htmlspecialchars(ApplicationVersion::get()) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($backupNeedsAttention): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-warning cleanup-prompt-alert">
                        <h5><i class="fas fa-exclamation-triangle"></i> <?= MaintenanceStatusLabels::alertHeading($backupStatusUnknown, $backupFailureDetected) ?></h5>
                        <?php if ($backupStatusUnknown): ?>
                            <p class="mb-2">The backup health check itself failed, so the figures below are placeholders rather than real counts. Check the system logs for <strong>BackupError</strong> entries.</p>
                        <?php endif; ?>
                        <?php if ($backupFailureDetected): ?>
                            <p class="mb-2">A backup attempt failed within the last <?= BACKUP_FAILURE_LOOKBACK_DAYS ?> days. Check the system logs for <strong>BackupFailed</strong> entries before relying on the most recent backup.</p>
                        <?php endif; ?>
                        <?php if ($showCleanupPrompt): ?>
                            <p class="mb-2">Found <strong><?= $oldBackupsCount ?></strong> backup files older than 30 days that can be cleaned up.</p>
                        <?php endif; ?>
                        <p class="mb-2"><strong>Current backup storage:</strong></p>
                        <ul class="mb-3">
                            <li>Automated: <?= $backupStats['automated']['count'] ?> files (<?= round($backupStats['automated']['total_size'] / 1024 / 1024, 2) ?> MB)</li>
                            <li>Manual: <?= $backupStats['manual']['count'] ?> files (<?= round($backupStats['manual']['total_size'] / 1024 / 1024, 2) ?> MB)</li>
                            <li>Rollback: <?= $backupStats['rollback']['count'] ?> files (<?= round($backupStats['rollback']['total_size'] / 1024 / 1024, 2) ?> MB)</li>
                        </ul>
                        <?php if (!empty($backupStats['recommendations'])): ?>
                            <div class="mt-2">
                                <strong>Recommendations:</strong>
                                <ul class="mb-2">
                                    <?php foreach ($backupStats['recommendations'] as $recommendation): ?>
                                        <li class="small"><?= htmlspecialchars($recommendation) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <a href="#backups-card" class="btn btn-warning btn-sm">
                            <i class="fas fa-shield-alt"></i> View Backups
                        </a>
                        <button type="button" class="btn btn-secondary btn-sm ms-2" data-dismiss-target=".cleanup-prompt-alert">
                            <i class="fas fa-times"></i> Dismiss
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php include 'includes/partials/js-data-island.php'; ?>

            <?php if ($scriptRunStatusError): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Script run history unavailable.</strong>
                        Could not load completion records from the database — both the One-time
                        Migrations and Maintenance Tasks sections below show every script as
                        pending. Do not re-run a script you know has already completed. Check
                        server logs for details.
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php include 'includes/partials/maintenance-backups.php'; ?>
            <?php include 'includes/partials/maintenance-migrations.php'; ?>
            <?php include 'includes/partials/maintenance-scripts.php'; ?>

        </div>
    </div>
</div>

<?php include 'includes/confirmation-modal.php'; ?>
<?php include 'includes/input-modal.php'; ?>

<?php require_once $abs_us_root . $us_url_root . 'users/includes/html_footer.php'; ?>

<link rel="stylesheet" href="assets/admin-core.min.css?v=<?= ASSET_VERSION ?>">
<script src="assets/admin-core.min.js?v=<?= ASSET_VERSION ?>"></script>
<script src="assets/backup-operations.min.js?v=<?= ASSET_VERSION ?>"></script>
