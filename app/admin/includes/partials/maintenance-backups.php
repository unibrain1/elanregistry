<?php
declare(strict_types=1);

/**
 * maintenance-backups.php
 * Backups card — automated/manual/rollback stats, action buttons, backup-list modal.
 *
 * Plain include sharing maintenance.php's variable scope (same pattern as
 * js-data-island.php). All computation ($backupStats, $backupStatsFallback,
 * $showCleanupPrompt) happens once in maintenance.php before this include.
 */
?>

<div class="card border-primary mb-4" id="backups-card">
    <div class="card-header card-header-er-primary">
        <h2 class="mb-0 card-header-er-primary-text"><i class="fas fa-shield-alt"></i> Backups</h2>
    </div>
    <div class="card-body">
        <?php if ($backupStatsFallback): ?>
            <div class="alert alert-warning py-2 small">
                <i class="fas fa-exclamation-triangle"></i>
                Backup statistics are currently unavailable &mdash; the counts below may not be accurate.
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-4">
                <div class="text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-cog" style="font-size: 2rem;"></i>
                    </div>
                    <h6>Automated Backups</h6>
                    <p class="small text-muted mb-2">Created before FIX script execution</p>
                    <div class="badge text-bg-primary"><?= $backupStats['automated']['count'] ?> files</div>
                    <div class="small text-muted"><?= round($backupStats['automated']['total_size'] / 1024 / 1024, 1) ?>MB</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <div class="text-primary mb-2">
                        <i class="fas fa-user" style="font-size: 2rem;"></i>
                    </div>
                    <h6>Manual Backups</h6>
                    <p class="small text-muted mb-2">Administrator-initiated backups</p>
                    <div class="badge text-bg-primary"><?= $backupStats['manual']['count'] ?> files</div>
                    <div class="small text-muted"><?= round($backupStats['manual']['total_size'] / 1024 / 1024, 1) ?>MB</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <div class="text-warning mb-2">
                        <i class="fas fa-undo" style="font-size: 2rem;"></i>
                    </div>
                    <h6>Rollback Files</h6>
                    <p class="small text-muted mb-2">Emergency recovery backups</p>
                    <div class="badge text-bg-warning"><?= $backupStats['rollback']['count'] ?> files</div>
                    <div class="small text-muted"><?= round($backupStats['rollback']['total_size'] / 1024 / 1024, 1) ?>MB</div>
                </div>
            </div>
        </div>

        <div class="text-center mt-3">
            <button type="button" class="btn btn-primary" data-action="createManualBackup">
                <i class="fas fa-save"></i> Create Manual Backup
            </button>
            <button type="button" class="btn btn-outline-primary ms-2" data-action="listBackupFiles">
                <i class="fas fa-list"></i> List Backup Files
            </button>
            <?php if ($showCleanupPrompt): ?>
                <button type="button" class="btn btn-outline-warning btn-sm ms-2" data-action="performBackupCleanup">
                    <i class="fas fa-broom"></i> Cleanup Old Backups
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" disabled title="No old backups to clean up">
                    <i class="fas fa-broom"></i> Cleanup Old Backups
                </button>
            <?php endif; ?>
        </div>

        <!-- Backup List Modal -->
        <div class="modal fade" id="backupListModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-database"></i> Backup Files</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body" id="backupListContent">
                        <div class="text-center">
                            <i class="fas fa-spinner fa-spin"></i> Loading backups...
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
