<?php
declare(strict_types=1);

/**
 * maintenance-scripts.php
 * Maintenance Tasks card — recurring maintenance scripts.
 *
 * Plain include sharing maintenance.php's variable scope (same pattern as
 * js-data-island.php). $maintenanceScripts/$maintenanceRunStatus and
 * scriptDisplayName() are computed/defined once in maintenance.php.
 */
?>

<div class="card border-primary mb-4">
    <div class="card-header card-header-er-primary">
        <h2 class="mb-0 card-header-er-primary-text"><i class="fas fa-sync-alt"></i> Maintenance Tasks</h2>
        <small class="card-header-er-primary-text">Scripts safe to run anytime to refresh data or fix common issues.</small>
    </div>
    <div class="card-body">
        <?php if (empty($maintenanceScripts)): ?>
            <p class="text-muted mb-0">No maintenance scripts available.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>Script Name</th>
                            <th>Description</th>
                            <th>Last Run</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($maintenanceScripts as $script): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($script) ?></strong></td>
                                <td><small class="text-muted"><?= htmlspecialchars(scriptDisplayName($script)) ?></small></td>
                                <td>
                                    <?php if ($maintenanceRunStatus[$script]['has_run']): ?>
                                        <small class="text-muted">
                                            <?= date('M j, Y g:i A', strtotime($maintenanceRunStatus[$script]['last_run'])) ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">Never</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="scripts/maintenance/<?= urlencode($script) ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       target="_blank"
                                       data-feedback-link
                                       title="Run <?= htmlspecialchars($script) ?>">
                                        <i class="fas fa-play"></i> Run Script
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
