<?php
declare(strict_types=1);

namespace ElanRegistry\Admin;

use ElanRegistry\Exceptions\BackupException;
use ElanRegistry\LogCategories;

/**
 * BackupManager.php
 * Enhanced Backup Management for Admin Interface
 *
 * Integrates with existing FIX backup system while providing enhanced
 * retention management and schema operation integration
 * Part of Phase 1D: Integrated Backup System
 */

class BackupManager {
    private object $db;
    private \Closure $logger;
    private string $backupBaseDir;


    /**
     * Constructor
     *
     * Note: PHP does not allow return type declarations on constructors per language specification
     *
     * @param object $database Database connection object
     * @param string $backupDirectory Base directory for backups
     * @param int|null $userId User ID for logging (optional)
     */
    // phpcs:disable PSR2.Methods.MethodDeclaration.MissingReturnType
    public function __construct(object $database, string $backupDirectory, ?int $userId = null) {
        // phpcs:enable PSR2.Methods.MethodDeclaration.MissingReturnType
        $this->db = $database;
        $this->backupBaseDir = rtrim($backupDirectory, '/') . '/';
        $this->logger = function($level, $category, $message) use ($userId) {
            if (function_exists('logger')) {
                logger($userId ?? 0, $category, $message);
            }
        };
    }

    /**
     * Create backup before schema operations
     *
     * @param string $operation Operation name (e.g., "Schema Maintenance")
     * @param array $tables Array of table names to backup (defaults to critical tables)
     * @return string Path to the created backup file
     * @throws BackupException If backup creation fails
     */
    public function createSchemaBackup(string $operation, array $tables = []): string {
        try {
            // Default tables for schema operations
            if (empty($tables)) {
                $tables = ['settings', 'users', 'cars', 'profiles'];
            }

            $scriptName = 'schema-' . strtolower(str_replace([' ', '_'], '-', $operation));
            $backupPath = $this->createStandardizedBackup($scriptName, $tables, 'automated', 'development');

            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_MANAGER, "Schema backup created for operation '{$operation}': {$backupPath}");

            return $backupPath;

        } catch (BackupException $e) {
            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR, "Schema backup failed for operation '{$operation}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create manual backup with enhanced metadata
     *
     * @param string $reason Reason for backup (becomes part of filename)
     * @param array $tables Array of table names to backup (defaults to critical tables)
     * @param array $metadata Optional metadata for enhanced logging
     * @return string Path to the created backup file
     * @throws BackupException If backup creation fails
     */
    public function createManualBackup(string $reason, array $tables = [], array $metadata = []): string {
        try {
            // Default to all critical tables if none specified
            if (empty($tables)) {
                $tables = $this->getCriticalTables();
            }

            $scriptName = 'manual-' . strtolower(str_replace([' ', '_'], '-', $reason));
            $backupPath = $this->createStandardizedBackup($scriptName, $tables, 'manual', 'development');

            // Log with enhanced metadata
            $logMessage = "Manual backup created: {$reason}";
            if (!empty($metadata)) {
                $logMessage .= ' | Metadata: ' . json_encode($metadata);
            }

            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_MANAGER, $logMessage);

            return $backupPath;

        } catch (BackupException $e) {
            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR, "Manual backup failed for '{$reason}': " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get backup statistics with enhanced details
     *
     * @return array Array containing backup statistics including:
     *               - Basic statistics (count, total_size per type)
     *               - Retention analysis (within_policy, approaching_expiry, expired)
     *               - Recent failures (bool) - whether a backup failed in the lookback window
     *               - Health score (0-100)
     *               - Recommendations array
     * @throws BackupException If statistics calculation fails
     */
    public function getEnhancedBackupStatistics(): array {
        try {
            // Use internal statistics calculation
            $basicStats = $this->calculateBasicStatistics();

            // Enhance with retention analysis
            $stats = $basicStats;
            $stats['retention_analysis'] = $this->analyzeRetention();
            $stats['recent_failures'] = $this->hasRecentBackupFailures();
            $stats['health_score'] = $this->calculateHealthScore($stats);
            $stats['recommendations'] = $this->generateRecommendations($stats);

            return $stats;

        } catch (BackupException $e) {
            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR, 'Failed to get enhanced backup statistics: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Analyze backup retention status across all backup types.
     *
     * Examines automated, manual, and rollback backups to determine retention compliance.
     * Categorizes backups as within policy, approaching expiry, or expired based on
     * configured retention policies and current timestamp.
     *
     * @return array Retention analysis with keys 'automated', 'manual', 'rollback' containing
     *              status counts and oldest/newest file timestamps
     */
    private function analyzeRetention(): array {
        $analysis = [];
        $now = time();

        foreach (['automated', 'manual', 'rollback'] as $type) {
            $typeDir = $this->backupBaseDir . $type . '/';
            $analysis[$type] = [
                'within_policy' => 0,
                'approaching_expiry' => 0, // Within 7 days of expiry
                'expired' => 0,
                'oldest_file' => null,
                'newest_file' => null
            ];

            if (is_dir($typeDir)) {
                $files = glob($typeDir . '*.sql');
                $retentionDays = $this->getRetentionDays($type);
                $expiryTime = $now - ($retentionDays * 24 * 60 * 60);
                $warningWindow = max(0, $retentionDays - BACKUP_WARNING_THRESHOLD_DAYS);
                $warningTime = $now - ($warningWindow * 24 * 60 * 60);

                foreach ($files as $file) {
                    $fileTime = filemtime($file);

                    if ($fileTime < $expiryTime) {
                        $analysis[$type]['expired']++;
                    } elseif ($warningWindow > 0 && $fileTime < $warningTime) {
                        $analysis[$type]['approaching_expiry']++;
                    } else {
                        $analysis[$type]['within_policy']++;
                    }

                    // Track oldest and newest
                    if ($analysis[$type]['oldest_file'] === null || $fileTime < filemtime($analysis[$type]['oldest_file'])) {
                        $analysis[$type]['oldest_file'] = $file;
                    }
                    if ($analysis[$type]['newest_file'] === null || $fileTime > filemtime($analysis[$type]['newest_file'])) {
                        $analysis[$type]['newest_file'] = $file;
                    }
                }
            }
        }

        return $analysis;
    }

    /**
     * Determine whether any backup failure was logged in the recent lookback window.
     *
     * Backups are triggered on demand rather than on a fixed schedule, so staleness of
     * the newest backup file is not a reliable failure signal. Instead this reads the
     * logs table for BackupFailed entries within BACKUP_FAILURE_LOOKBACK_DAYS. Only
     * createStandardizedBackup() writes that category, and only when a backup attempt
     * was aborted, so unrelated BackupError entries (invalid delete requests, missing
     * tables) cannot raise a false alarm.
     *
     * Fails open: if the logs query itself errors, this returns false rather than
     * throwing, so a logs table hiccup cannot block performEnhancedCleanup() — which
     * 21-Fix-Page-Permissions.php calls as a safety step before every permission-fix run.
     *
     * @return bool True if at least one backup failure was logged in the window
     */
    private function hasRecentBackupFailures(): bool {
        $cutoff = date('Y-m-d H:i:s', time() - (BACKUP_FAILURE_LOOKBACK_DAYS * 24 * 60 * 60));

        $result = $this->db->query(
            "SELECT COUNT(*) as cnt FROM logs WHERE logtype = ? AND logdate >= ?",
            [LogCategories::LOG_CATEGORY_BACKUP_FAILED, $cutoff]
        );

        if ($this->db->error()) {
            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR, 'Failed to check for recent backup failures: ' . $this->db->errorString());
            return false;
        }

        // COUNT(*) always returns exactly one row, so a successful query always has a
        // first() result here.
        return (int)$result->first()->cnt > 0;
    }

    /**
     * Calculate overall backup system health score.
     *
     * Evaluates backup system health based on recent failures, retention compliance,
     * and storage metrics. Deducts points for failed backups, expired backups,
     * approaching expiry dates, and excessive storage usage.
     *
     * @param array $stats Statistics array containing recent_failures, retention_analysis,
     *                     and storage data
     *
     * @return int Health score from 0-100, where 100 indicates optimal health
     */
    private function calculateHealthScore(array $stats): int {
        $score = 100;

        // Deduct heavily for a recent backup failure - a backup that did not complete is a
        // data-loss risk, which outweighs retention and storage housekeeping issues
        if (!empty($stats['recent_failures'])) {
            $score -= 30;
        }

        // Deduct points for retention issues
        foreach (['automated', 'manual', 'rollback'] as $type) {
            if (isset($stats['retention_analysis'][$type])) {
                $expired = $stats['retention_analysis'][$type]['expired'];
                $approaching = $stats['retention_analysis'][$type]['approaching_expiry'];

                // Deduct 10 points per expired backup type, 5 points for approaching expiry
                if ($expired > 0) {
                    $score -= 10;
                }
                if ($approaching > 0) {
                    $score -= 5;
                }
            }
        }

        // Deduct points for excessive storage usage (over 1GB)
        $totalSize = ($stats['automated']['total_size'] ?? 0) +
                    ($stats['manual']['total_size'] ?? 0) +
                    ($stats['rollback']['total_size'] ?? 0);

        if ($totalSize > (1024 * 1024 * 1024)) { // 1GB
            $score -= 15;
        }

        // Minimum score of 0
        return max(0, $score);
    }

    /**
     * Generate backup system recommendations.
     *
     * Analyzes backup statistics and produces actionable recommendations for system
     * administrators. Covers recent failures, retention cleanup, and storage
     * optimization based on current conditions.
     *
     * @param array $stats Statistics array containing recent_failures, retention_analysis,
     *                     storage data, and health score
     *
     * @return array Array of recommendation strings describing actions to improve
     *              backup system health
     */
    private function generateRecommendations(array $stats): array {
        $recommendations = [];

        // Check for a recent backup failure
        if (!empty($stats['recent_failures'])) {
            $recommendations[] = "Investigate the cause of the recent backup failure before relying on this backup type";
        }

        // Check retention analysis
        if (isset($stats['retention_analysis'])) {
            $totalExpired = array_sum(array_column($stats['retention_analysis'], 'expired'));
            if ($totalExpired > 0) {
                $recommendations[] = "Run backup cleanup to remove {$totalExpired} expired backup files";
            }

            $totalApproaching = array_sum(array_column($stats['retention_analysis'], 'approaching_expiry'));
            if ($totalApproaching > 0) {
                $recommendations[] = "{$totalApproaching} backup files will expire within " . BACKUP_WARNING_THRESHOLD_DAYS . " days";
            }
        }

        // Check storage usage
        $totalSize = ($stats['automated']['total_size'] ?? 0) +
                    ($stats['manual']['total_size'] ?? 0) +
                    ($stats['rollback']['total_size'] ?? 0);

        if ($totalSize > (500 * 1024 * 1024)) { // 500MB
            $sizeGB = round($totalSize / (1024 * 1024 * 1024), 2);
            $recommendations[] = "Consider cleanup - backup storage is {$sizeGB}GB";
        }

        // Check backup frequency
        if (isset($stats['automated']['count']) && $stats['automated']['count'] === 0) {
            $recommendations[] = "No automated backups found - consider running FIX scripts to generate backups";
        }

        return $recommendations;
    }

    /**
     * Get list of critical tables for backup
     */
    private function getCriticalTables(): array {
        return [
            'users',
            'cars',
            'profiles',
            'settings',
            'car_history',
            'fix_script_runs'
        ];
    }

    /**
     * Calculate basic statistics if getBackupStatistics function not available
     */
    private function calculateBasicStatistics(): array {
        $stats = [];

        foreach (['automated', 'manual', 'rollback'] as $type) {
            $typeDir = $this->backupBaseDir . $type . '/';
            $stats[$type] = [
                'count' => 0,
                'total_size' => 0
            ];

            if (is_dir($typeDir)) {
                $files = glob($typeDir . '*.sql');
                if ($files === false) {
                    throw new BackupException("Could not enumerate backup files in: {$typeDir}");
                }
                $stats[$type]['count'] = count($files);

                foreach ($files as $file) {
                    $stats[$type]['total_size'] += filesize($file);
                }
            }
        }

        return $stats;
    }

    /**
     * Enhanced cleanup with detailed reporting
     *
     * @return array Array with cleanup statistics including:
     *               - automated/manual/rollback: {scanned, deleted} counts
     *               - health_score_before: Health score before cleanup
     *               - health_score_after: Health score after cleanup
     *               - health_improvement: Points of improvement
     * @throws BackupException If cleanup fails
     */
    public function performEnhancedCleanup(): array {
        try {
            // Get before statistics
            $beforeStats = $this->getEnhancedBackupStatistics();

            // Perform cleanup
            $cleanupResult = $this->cleanupOldBackups();

            // Get after statistics
            $afterStats = $this->getEnhancedBackupStatistics();

            // Calculate improvements
            $result = $cleanupResult;
            $result['health_score_before'] = $beforeStats['health_score'];
            $result['health_score_after'] = $afterStats['health_score'];
            $result['health_improvement'] = $afterStats['health_score'] - $beforeStats['health_score'];

            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_MANAGER, 'Enhanced cleanup completed - Health score improved by ' . $result['health_improvement'] . ' points');

            return $result;

        } catch (BackupException $e) {
            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR, 'Enhanced cleanup failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verify backup integrity
     *
     * @param string $backupPath Full path to backup file
     * @return array Array with verification results:
     *               - valid (bool): Whether backup is valid
     *               - error (string): Error message if invalid
     *               - file_size (int): Size in bytes (if valid)
     *               - created_at (string): Creation timestamp (if valid)
     *               - age_hours (float): Age in hours (if valid)
     */
    public function verifyBackupIntegrity(string $backupPath): array {
        if (!file_exists($backupPath)) {
            return ['valid' => false, 'error' => 'Backup file not found'];
        }

        // Basic file checks
        $fileSize = filesize($backupPath);
        if ($fileSize === 0) {
            return ['valid' => false, 'error' => 'Backup file is empty'];
        }

        // Scan the dump for structure and data statements. INSERT rows normally fall
        // well past the header, so the file is streamed line by line rather than
        // sampled — and the loop stops at the first INSERT it finds, so most dumps are
        // only read as far as their first table that has data. Only a dump with no data
        // at all is read to the end.
        $handle = fopen($backupPath, 'r');
        if ($handle === false) {
            return ['valid' => false, 'error' => 'Backup file could not be opened'];
        }

        $hasStructure = false;
        $hasData = false;

        while (($line = fgets($handle)) !== false) {
            if (preg_match('/^\s*INSERT\b/i', $line)) {
                $hasData = true;
                break;
            }
            if (preg_match('/^\s*(CREATE|DROP|ALTER)\b/i', $line)) {
                $hasStructure = true;
            }
        }
        fclose($handle);

        if (!$hasStructure && !$hasData) {
            return ['valid' => false, 'error' => 'File does not appear to contain valid SQL'];
        }

        // Structure without a single INSERT is the signature of a data dump that failed
        // silently, so it is reported invalid rather than rubber-stamped as good.
        if (!$hasData) {
            return ['valid' => false, 'error' => 'Backup contains table structure but no data'];
        }

        return [
            'valid' => true,
            'file_size' => $fileSize,
            'created_at' => date('Y-m-d H:i:s', filemtime($backupPath)),
            'age_hours' => round((time() - filemtime($backupPath)) / 3600, 1)
        ];
    }

    /**
     * Create a standardized backup file (PRIVATE - moved from FIX/backup-functions.php)
     *
     * @param string $scriptName Script identifier (kebab-case)
     * @param array $tables Tables to backup
     * @param string $type Type of backup: 'automated', 'manual', 'rollback'
     * @param string $environment Environment: 'development', 'test', 'production'
     * @return string Path to created backup file
     * @throws BackupException If backup creation fails
     * @throws \Throwable Any other failure during the backup attempt (e.g. a DB
     *                     connection fault) is logged as a failed attempt and rethrown as-is
     */
    private function createStandardizedBackup(string $scriptName, array $tables = [], string $type = 'automated', string $environment = 'development'): string {
        // Validate parameters before any work starts. These are caller mistakes rather
        // than a backup attempt that failed, so they are deliberately thrown outside the
        // try block below and never reach the BackupFailed alarm.
        $validTypes = ['automated', 'manual', 'rollback'];
        $validEnvironments = ['development', 'test', 'production'];

        if (!in_array($type, $validTypes)) {
            throw new BackupException("Invalid backup type: $type. Must be one of: " . implode(', ', $validTypes));
        }

        if (!in_array($environment, $validEnvironments)) {
            throw new BackupException("Invalid environment: $environment. Must be one of: " . implode(', ', $validEnvironments));
        }

        try {
            // Generate standardized filename
            $timestamp = date('Ymd_His');
            $filename = "{$type}_{$scriptName}_{$environment}_{$timestamp}.sql";

            // Determine backup directory
            $backupDir = $this->backupBaseDir . "{$type}/";

            // Ensure backup directory exists
            if (!is_dir($backupDir)) {
                if (!mkdir($backupDir, 0755, true)) {
                    throw new BackupException("Failed to create backup directory: $backupDir");
                }
            }

            $backupPath = $backupDir . $filename;

            // Create backup content with metadata
            $backupContent = $this->generateBackupMetadata($scriptName, $type, $environment, $tables);

            // Add table dumps
            if (!empty($tables)) {
                foreach ($tables as $table) {
                    $backupContent .= $this->generateTableDump($table);
                }
            }

            // Write backup file
            if (!file_put_contents($backupPath, $backupContent)) {
                throw new BackupException("Failed to write backup file: $backupPath");
            }

            $this->logBackupEvent('created', $scriptName, $type, $environment, $backupPath);

            return $backupPath;

        } catch (\Throwable $e) {
            // Single choke point for "a backup was attempted and did not complete" —
            // unwritable directory, failed table dump, and failed file write alike.
            // Catches \Throwable, not just BackupException: a connection-level fault
            // (e.g. the DB going away mid-dump) surfaces as a raw PDOException out of
            // $this->db->query(), and that must reach this alarm too, or the health
            // badge stays falsely "Healthy" for a backup that never wrote a file.
            // Logged under BackupFailed rather than the generic BackupError so
            // hasRecentBackupFailures() sees every aborted attempt and routine
            // BackupError entries cannot raise a false alarm.
            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_FAILED,
                "Backup aborted for '{$scriptName}' ({$type}/{$environment}): " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Generate backup metadata header
     *
     * @param string $scriptName Script identifier
     * @param string $type Backup type
     * @param string $environment Environment
     * @param array $tables Tables being backed up
     * @return string SQL comment block with metadata
     */
    private function generateBackupMetadata(string $scriptName, string $type, string $environment, array $tables): string {
        $timestamp = date('Y-m-d H:i:s');
        $tableList = implode(', ', $tables);

        $retentionDays = $this->getRetentionDays($type);
        $rollbackReady = !empty($tables) ? 'yes' : 'no';

        return "-- BACKUP METADATA\n" .
               "-- Type: {$type}\n" .
               "-- Script: {$scriptName}\n" .
               "-- Environment: {$environment}\n" .
               "-- Created: {$timestamp}\n" .
               "-- Tables: {$tableList}\n" .
               "-- Retention: {$retentionDays} days\n" .
               "-- Rollback-Ready: {$rollbackReady}\n" .
               "-- Generator: BackupManager v2.0\n" .
               "\n";
    }

    /**
     * Generate SQL dump for a table.
     *
     * Creates a complete SQL dump including table structure (CREATE TABLE)
     * and data (INSERT statements). Validates table names to prevent SQL
     * injection. A table that does not exist (MySQL 1146 / SQLSTATE 42S02) is
     * degraded to a warning comment so the rest of the backup still completes.
     *
     * @param string $tableName Table name to dump (validated against injection)
     * @return string Complete SQL dump with CREATE and INSERT statements, or a warning
     *                comment if the table does not exist
     * @throws BackupException If the table name contains invalid characters or either the
     *                         structure or data query fails for any reason other than a
     *                         missing table, aborting the whole backup
     */
    private function generateTableDump(string $tableName): string {
        try {
            // Validate table name to prevent SQL injection
            if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
                throw new BackupException("Invalid table name: {$tableName}");
            }

            // Get table structure
            // UserSpice swallows DB errors into a flag rather than throwing, so a failed
            // query is indistinguishable from an empty result without this check.
            // SHOW CREATE TABLE either errors or returns exactly one row, so a missing
            // table always surfaces here as 1146/42S02 rather than as an empty result.
            $createResult = $this->db->query("SHOW CREATE TABLE `{$tableName}`");
            if ($this->db->error()) {
                if ($this->isTableNotFoundError()) {
                    ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR, "Table {$tableName} not found during backup");
                    return "-- Warning: Table {$tableName} not found\n\n";
                }
                throw new BackupException("Failed to read structure for table {$tableName}: " . $this->db->errorString());
            }

            $createStatement = $createResult->first()->{'Create Table'};

            // Get table data
            // A failed SELECT reports count() === 0 exactly like an empty table, so the
            // error flag is the only way to avoid silently writing a dump with no rows.
            $dataResult = $this->db->query("SELECT * FROM `{$tableName}`");
            if ($this->db->error()) {
                throw new BackupException("Failed to read data for table {$tableName}: " . $this->db->errorString());
            }

            $dump = "-- Dump for table: {$tableName}\n";
            $dump .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
            $dump .= $createStatement . ";\n\n";

            if ($dataResult->count() > 0) {
                $dump .= "-- Data for table: {$tableName}\n";

                foreach ($dataResult->results() as $row) {
                    $values = [];
                    foreach ($row as $columnName => $value) {
                        if (is_null($value)) {
                            $values[] = 'NULL';
                        } else {
                            // Convert to string and escape
                            $stringValue = (string)$value;
                            $escapedValue = addslashes($stringValue);
                            $values[] = "'{$escapedValue}'";
                        }
                    }
                    $dump .= "INSERT INTO `{$tableName}` VALUES (" . implode(', ', $values) . ");\n";
                }
            }

            return $dump . "\n";

        } catch (BackupException $e) {
            // Always abort: a partial or empty dump written as if it succeeded is a
            // silent data-loss risk. createStandardizedBackup() only writes the file
            // after every table dumps successfully, so throwing here means no file.
            //
            // Logged with the per-table detail for diagnosis; createStandardizedBackup()
            // raises the BackupFailed alarm once the exception reaches it.
            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR, "Error backing up table {$tableName}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Determine whether the last query failed because the table does not exist.
     *
     * MySQL reports a missing table as error 1146 / SQLSTATE 42S02. Every other
     * error is a genuine database problem that must abort the backup rather than
     * degrade to a warning comment.
     *
     * @return bool True if the last query error was "table not found"
     */
    private function isTableNotFoundError(): bool {
        $errorInfo = $this->db->errorInfo();

        return ($errorInfo[0] ?? null) === '42S02' || (int)($errorInfo[1] ?? 0) === 1146;
    }

    /**
     * Get retention days for a backup type from config.php constants.
     *
     * @param string $type Backup type ('automated', 'manual', 'rollback')
     * @return int Number of retention days
     */
    private function getRetentionDays(string $type): int {
        return match($type) {
            'automated' => BACKUP_RETENTION_AUTOMATED,
            'manual'    => BACKUP_RETENTION_MANUAL,
            'rollback'  => BACKUP_RETENTION_ROLLBACK,
            default     => throw new BackupException("Unknown backup type: {$type}"),
        };
    }

    /**
     * Clean up old backup files based on retention policy
     *
     * @param int|null $retentionDays Override retention days (optional)
     * @return array Summary of cleanup actions
     */
    private function cleanupOldBackups(?int $retentionDays = null): array {
        $cleanupSummary = [
            'automated' => ['scanned' => 0, 'deleted' => 0],
            'manual' => ['scanned' => 0, 'deleted' => 0],
            'rollback' => ['scanned' => 0, 'deleted' => 0]
        ];

        $types = ['automated', 'manual', 'rollback'];
        $realBackupBase = realpath($this->backupBaseDir);

        if ($realBackupBase === false) {
            ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR,
                "cleanupOldBackups: realpath() failed for backup base dir '{$this->backupBaseDir}' — cleanup aborted");
            return $cleanupSummary;
        }

        foreach ($types as $type) {
            $typeDir = $this->backupBaseDir . $type . '/';

            if (!is_dir($typeDir)) {
                continue;
            }

            $files = glob($typeDir . '*');
            $cleanupSummary[$type]['scanned'] = count($files);

            foreach ($files as $file) {
                if (!is_file($file)) {
                    continue;
                }

                $filename = basename($file);
                $environment = $this->extractEnvironmentFromFilename($filename);

                $fileRetentionDays = $retentionDays ?? $this->getRetentionDays($type);
                $cutoffTime = time() - ($fileRetentionDays * 24 * 60 * 60);

                if (filemtime($file) < $cutoffTime) {
                    $realpath = realpath($file);
                    if ($realpath === false) {
                        ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_MANAGER,
                            "cleanupOldBackups: realpath() returned false for '{$file}' — skipping (possibly deleted concurrently)");
                        continue;
                    }
                    if (!str_starts_with($realpath, $realBackupBase . '/')) {
                        ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR,
                            "cleanupOldBackups: path traversal blocked — '{$realpath}' is outside backup base '{$realBackupBase}'");
                        continue;
                    }
                    if (unlink($realpath)) { // nosemgrep: php.lang.security.unlink-use.unlink-use -- path verified within backup directory
                        $cleanupSummary[$type]['deleted']++;
                        $this->logBackupEvent('deleted', $filename, $type, $environment, $realpath);
                    } else {
                        ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_ERROR,
                            "cleanupOldBackups: unlink() failed for '{$realpath}' — check permissions");
                    }
                }
            }
        }

        return $cleanupSummary;
    }

    /**
     * Extract environment from standardized filename
     *
     * @param string $filename Backup filename
     * @return string Environment (defaults to 'development')
     */
    private function extractEnvironmentFromFilename(string $filename): string {
        if (preg_match('/_(development|test|production)_/', $filename, $matches)) {
            return $matches[1];
        }
        return 'development';
    }

    /**
     * Log backup event (creation or deletion)
     *
     * @param string $action 'created' or 'deleted'
     * @param string $scriptName Script identifier
     * @param string $type Backup type
     * @param string $environment Environment
     * @param string $backupPath Path to backup file
     */
    private function logBackupEvent(string $action, string $scriptName, string $type, string $environment, string $backupPath): void {
        $logMessage = sprintf(
            "[%s] Backup %s - Script: %s, Type: %s, Environment: %s, File: %s",
            date('Y-m-d H:i:s'),
            $action,
            $scriptName,
            $type,
            $environment,
            basename($backupPath)
        );

        // Log via UserSpice logger
        ($this->logger)(1, LogCategories::LOG_CATEGORY_BACKUP_MANAGER, $logMessage);
    }
}