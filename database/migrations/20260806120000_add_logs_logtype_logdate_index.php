<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddLogsLogtypeLogdateIndex extends AbstractMigration
{
    // BackupManager::hasRecentBackupFailures() runs
    // "SELECT COUNT(*) FROM logs WHERE logtype = ? AND logdate >= ?" on every render
    // of the admin Health and Maintenance tabs. The logs table has no index beyond
    // its primary key, so that query is a full scan of a table that grows without
    // bound. The composite (logtype, logdate) index matches the equality-then-range
    // predicate order and lets MySQL answer the count from the index alone.
    //
    // up() + down() are used instead of change() because raw SQL is not
    // auto-reversible. Both directions check information_schema first so the
    // migration is safe to run against environments where the index was added by
    // hand — matching the existence-guard pattern in 20260719120000_drop_cars_user_id_fk.
    //
    // DDL note: ALTER TABLE issues an implicit commit in MySQL. This migration
    // cannot be wrapped in a transaction.

    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        $this->execute(
            "ALTER TABLE `logs` ADD INDEX `idx_logs_logtype_logdate` (`logtype`, `logdate`)"
        );
    }

    public function down(): void
    {
        if (!$this->indexExists()) {
            return;
        }

        $this->execute("ALTER TABLE `logs` DROP INDEX `idx_logs_logtype_logdate`");
    }

    private function indexExists(): bool
    {
        return (bool) $this->fetchRow(
            "SELECT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'logs'
               AND INDEX_NAME = 'idx_logs_logtype_logdate'"
        );
    }
}
