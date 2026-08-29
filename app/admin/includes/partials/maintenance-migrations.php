<?php
declare(strict_types=1);

/**
 * maintenance-migrations.php
 * One-time Migrations card — pending/completed fix scripts.
 *
 * Plain include sharing maintenance.php's variable scope (same pattern as
 * js-data-island.php). $pendingFixScripts/$completedFixScripts/$scriptRunStatus
 * and scriptDisplayName() are computed/defined once in maintenance.php.
 */
?>

<div class="card border-primary mb-4" id="migrations-card">
    <div class="card-header card-header-er-primary">
        <h2 class="mb-0 card-header-er-primary-text"><i class="fas fa-file-code"></i> One-time Migrations</h2>
        <small class="card-header-er-primary-text">Scripts that apply structural changes once. Run when needed; archived when done.</small>
    </div>
    <div class="card-body">
        <?php if (empty($pendingFixScripts) && empty($completedFixScripts)): ?>
            <p class="text-muted mb-0">No migration scripts available.</p>
        <?php else: ?>
            <?php if (empty($pendingFixScripts)): ?>
                <div class="alert alert-success mb-3">
                    <i class="fas fa-check-circle"></i> <strong>No pending migrations</strong> &mdash; all migration scripts have been completed.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="thead-light">
                            <tr>
                                <th>Status</th>
                                <th>Script Name</th>
                                <th>Last Run</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingFixScripts as $script): ?>
                                <tr>
                                    <td>
                                        <span class="badge text-bg-secondary" title="Script has not been run">
                                            <i class="fas fa-minus"></i> Not Run
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($script) ?></strong>
                                        <br><small class="text-muted"><?= htmlspecialchars(scriptDisplayName($script)) ?></small>
                                    </td>
                                    <td>
                                        <small class="text-muted">Never</small>
                                    </td>
                                    <td>
                                        <a href="scripts/fix/<?= urlencode($script) ?>"
                                           class="btn btn-sm btn-warning text-dark"
                                           target="_blank"
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

            <?php if (count($completedFixScripts) > 0): ?>
                <div class="mt-2">
                    <button class="btn btn-sm btn-link text-muted" data-bs-toggle="collapse" data-bs-target="#completedMigrations">
                        Show <?= count($completedFixScripts) ?> completed migrations
                    </button>
                    <div class="collapse" id="completedMigrations">
                        <div class="table-responsive">
                            <table class="table table-sm table-hover text-muted">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Status</th>
                                        <th>Script Name</th>
                                        <th>Last Run</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completedFixScripts as $script): ?>
                                        <tr>
                                            <td>
                                                <span class="badge text-bg-primary" title="Script completed successfully">
                                                    <i class="fas fa-check"></i> Completed
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($script) ?></strong>
                                                <br><small><?= htmlspecialchars(scriptDisplayName($script)) ?></small>
                                            </td>
                                            <td>
                                                <small>
                                                    <?= date('M j, Y g:i A', strtotime($scriptRunStatus[$script]['last_run'])) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <a href="scripts/fix/<?= urlencode($script) ?>"
                                                   class="btn btn-sm btn-outline-secondary"
                                                   target="_blank"
                                                   title="Re-run <?= htmlspecialchars($script) ?>">
                                                    <i class="fas fa-play"></i> Run Script
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-3">
                <div class="alert alert-primary">
                    <h6><i class="fas fa-info-circle"></i> Script Execution Notes</h6>
                    <ul class="mb-0 small">
                        <li>Scripts open in a new window/tab for execution</li>
                        <li>Automatic backups are created before script execution when required</li>
                        <li>Progress and results are displayed in real-time during execution</li>
                        <li>Completed scripts are logged in the system for tracking</li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
