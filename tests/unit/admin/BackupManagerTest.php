<?php

declare(strict_types=1);

use ElanRegistry\Admin\BackupManager;
use ElanRegistry\Exceptions\BackupException;
use ElanRegistry\LogCategories;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeDatabase;

use PHPUnit\Framework\Attributes\Group;
// BackupManager and BackupException auto-loaded via custom autoloader
// (BackupManager now in usersc/classes/admin/, BackupException in usersc/classes/exceptions/)

/**
 * Unit tests for BackupManager class
 *
 * Tests backup creation, validation, statistics, and cleanup operations
 * for the database backup management system.
 */
#[Group('fast')]
#[Group('unit')]
#[Group('admin')]
final class BackupManagerTest extends TestCase
{
    private string $testBackupDir;
    private BackupManagerFakeDatabase $mockDb;
    private BackupManager $backupManager;

    /**
     * Set up test environment before each test
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary backup directory for tests
        $this->testBackupDir = sys_get_temp_dir() . '/backup_test_' . uniqid() . '/';
        mkdir($this->testBackupDir);
        mkdir($this->testBackupDir . 'automated/');
        mkdir($this->testBackupDir . 'manual/');
        mkdir($this->testBackupDir . 'rollback/');

        // Create mock database
        $this->mockDb = $this->createMockDatabase();

        // Initialize BackupManager
        $this->backupManager = new BackupManager($this->mockDb, $this->testBackupDir, 1);
    }

    /**
     * Clean up test environment after each test
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clean up test directory
        if (is_dir($this->testBackupDir)) {
            $this->recursiveRemoveDirectory($this->testBackupDir);
        }

        parent::tearDown();
    }

    /**
     * Test createSchemaBackup creates a backup file
     *
     * @return void
     */
    #[Group('fast')]
    public function testCreateSchemaBackup(): void
    {
        $backupPath = $this->backupManager->createSchemaBackup('Test Operation', ['settings']);

        $this->assertFileExists($backupPath);
        $this->assertStringContainsString('automated_schema-test-operation', $backupPath);
        $this->assertStringEndsWith('.sql', $backupPath);
    }

    /**
     * Test createSchemaBackup uses default tables when none specified
     *
     * Drives the real getAllTables() path: the fake database is configured to answer
     * the information_schema.TABLES discovery query with a controllable set of table
     * names, and this asserts those discovered names (not a hardcoded list — that
     * hardcoded default no longer exists, see BackupManager::getAllTables()) appear
     * in the dump's metadata header.
     *
     * @return void
     */
    #[Group('fast')]
    public function testCreateSchemaBackupWithDefaultTables(): void
    {
        $discoveredTables = ['cars', 'profiles', 'settings', 'users'];
        $mockDb = $this->createMockDatabase(tableNames: $discoveredTables);
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        $backupPath = $backupManager->createSchemaBackup('Default Tables Test');

        $this->assertFileExists($backupPath);

        $content = file_get_contents($backupPath);
        $this->assertStringContainsString(
            '-- Tables: ' . implode(', ', $discoveredTables),
            $content
        );
    }

    /**
     * Verify getAllTables() fails loudly when the information_schema.TABLES query
     * itself errors: hasRecentBackupFailures() and generateTableDump() both check
     * $this->db->error() the same way, but getAllTables() is new and had zero
     * coverage of this path before this test.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testGetAllTablesThrowsWhenSchemaQueryErrors(): void
    {
        $mockDb = $this->createMockDatabase(failOnSqlSubstring: 'information_schema.TABLES');
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('Cannot determine which tables to back up');

        $backupManager->createManualBackup('Schema Query Failure Test');
    }

    /**
     * Verify getAllTables() fails loudly when the information_schema.TABLES query
     * succeeds but returns zero rows — a schema with no base tables means the
     * connection is pointed somewhere unexpected, and a backup of nothing must not
     * report success.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testGetAllTablesThrowsWhenSchemaQueryReturnsNoTables(): void
    {
        $mockDb = $this->createMockDatabase(emptyResultOnSqlSubstring: 'information_schema.TABLES');
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage('the connected schema reports no tables');

        $backupManager->createManualBackup('Schema Query Empty Test');
    }

    /**
     * Test createManualBackup creates a backup file
     *
     * @return void
     */
    #[Group('fast')]
    public function testCreateManualBackup(): void
    {
        $backupPath = $this->backupManager->createManualBackup('Pre-Migration', ['users', 'cars']);

        $this->assertFileExists($backupPath);
        $this->assertStringContainsString('manual_manual-pre-migration', $backupPath);
        $this->assertStringEndsWith('.sql', $backupPath);
    }

    /**
     * Test createManualBackup includes metadata in backup
     *
     * @return void
     */
    #[Group('fast')]
    public function testCreateManualBackupWithMetadata(): void
    {
        $metadata = [
            'migration_version' => '2.9.2',
            'performed_by' => 'test_user'
        ];

        $backupPath = $this->backupManager->createManualBackup('Test Backup', ['users'], $metadata);

        $this->assertFileExists($backupPath);
        $content = file_get_contents($backupPath);
        $this->assertStringContainsString('-- Type: manual', $content);
    }

    /**
     * Test verifyBackupIntegrity validates a good backup
     *
     * @return void
     */
    #[Group('fast')]
    public function testVerifyBackupIntegrityValidBackup(): void
    {
        // Create a test backup
        $backupPath = $this->backupManager->createSchemaBackup('Test Validation', ['settings']);

        $result = $this->backupManager->verifyBackupIntegrity($backupPath);

        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('file_size', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('age_hours', $result);
        $this->assertGreaterThan(0, $result['file_size']);
    }

    /**
     * Test verifyBackupIntegrity detects non-existent file
     *
     * @return void
     */
    #[Group('fast')]
    public function testVerifyBackupIntegrityNonExistentFile(): void
    {
        $result = $this->backupManager->verifyBackupIntegrity('/nonexistent/backup.sql');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('not found', strtolower($result['error']));
    }

    /**
     * Test verifyBackupIntegrity detects empty file
     *
     * @return void
     */
    #[Group('fast')]
    public function testVerifyBackupIntegrityEmptyFile(): void
    {
        // Create an empty file
        $emptyFile = $this->testBackupDir . 'empty_backup.sql';
        touch($emptyFile);

        $result = $this->backupManager->verifyBackupIntegrity($emptyFile);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('empty', $result['error']);
    }

    /**
     * Test verifyBackupIntegrity flags a dump that has table structure (CREATE/DROP/
     * ALTER statements) but no INSERT statements as invalid — the signature of a data
     * dump that failed silently, per the comment above the check in
     * verifyBackupIntegrity().
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testVerifyBackupIntegrityStructureOnlyNoData(): void
    {
        $structureOnlyFile = $this->testBackupDir . 'structure_only_backup.sql';
        file_put_contents(
            $structureOnlyFile,
            "DROP TABLE IF EXISTS `settings`;\nCREATE TABLE `settings` (`id` int) ENGINE=InnoDB;\n"
        );

        $result = $this->backupManager->verifyBackupIntegrity($structureOnlyFile);

        $this->assertFalse($result['valid']);
        $this->assertSame('Backup contains table structure but no data', $result['error']);
    }

    /**
     * Test verifyBackupIntegrity's fopen() failure guard: an existing, non-empty file
     * that cannot be opened for reading (e.g. permissions) is reported invalid rather
     * than throwing or crashing.
     *
     * fopen() emits a PHP E_WARNING on failure that verifyBackupIntegrity() does not
     * suppress; a temporary error handler captures it here so PHPUnit does not flag it
     * as an unexpected warning, matching the pattern used in LocationServiceCacheTest.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testVerifyBackupIntegrityFopenFailureGuard(): void
    {
        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test fopen() failure as root — chmod 0000 has no effect.');
        }

        $unreadableFile = $this->testBackupDir . 'unreadable_backup.sql';
        file_put_contents(
            $unreadableFile,
            "CREATE TABLE `settings` (`id` int) ENGINE=InnoDB;\nINSERT INTO `settings` VALUES (1);\n"
        );
        chmod($unreadableFile, 0000);

        $capturedWarning = null;
        set_error_handler(
            static function (int $errno, string $errstr) use (&$capturedWarning): bool {
                if ($errno === E_WARNING) {
                    $capturedWarning = $errstr;
                    return true;
                }
                return false;
            },
            E_WARNING
        );
        try {
            $result = $this->backupManager->verifyBackupIntegrity($unreadableFile);
        } finally {
            restore_error_handler();
            chmod($unreadableFile, 0644);
        }

        $this->assertFalse($result['valid']);
        $this->assertSame('Backup file could not be opened', $result['error']);
        $this->assertNotNull(
            $capturedWarning,
            'A PHP E_WARNING should be emitted when fopen() fails on an unreadable file.'
        );
    }

    /**
     * Test getEnhancedBackupStatistics returns statistics structure
     *
     * @return void
     */
    #[Group('fast')]
    public function testGetEnhancedBackupStatistics(): void
    {
        // Create some test backups
        $this->backupManager->createSchemaBackup('Test Stats 1', ['settings']);
        $this->backupManager->createManualBackup('Test Stats 2', ['users']);

        $stats = $this->backupManager->getEnhancedBackupStatistics();

        // Verify structure
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('automated', $stats);
        $this->assertArrayHasKey('manual', $stats);
        $this->assertArrayHasKey('rollback', $stats);
        $this->assertArrayHasKey('retention_analysis', $stats);
        $this->assertArrayHasKey('health_score', $stats);
        $this->assertArrayHasKey('recommendations', $stats);

        // Verify automated stats
        $this->assertArrayHasKey('count', $stats['automated']);
        $this->assertArrayHasKey('total_size', $stats['automated']);
        $this->assertEquals(1, $stats['automated']['count']);

        // Verify manual stats
        $this->assertEquals(1, $stats['manual']['count']);
    }

    /**
     * Test getEnhancedBackupStatistics calculates health score
     *
     * @return void
     */
    #[Group('fast')]
    public function testGetEnhancedBackupStatisticsHealthScore(): void
    {
        $this->backupManager->createSchemaBackup('Health Test', ['settings']);

        $stats = $this->backupManager->getEnhancedBackupStatistics();

        $this->assertIsInt($stats['health_score']);
        $this->assertGreaterThanOrEqual(0, $stats['health_score']);
        $this->assertLessThanOrEqual(100, $stats['health_score']);
    }

    /**
     * Test performEnhancedCleanup returns cleanup statistics
     *
     * @return void
     */
    #[Group('fast')]
    public function testPerformEnhancedCleanup(): void
    {
        // Create some test backups
        $this->backupManager->createSchemaBackup('Cleanup Test 1', ['settings']);
        $this->backupManager->createManualBackup('Cleanup Test 2', ['users']);

        $result = $this->backupManager->performEnhancedCleanup();

        // Verify structure
        $this->assertIsArray($result);
        $this->assertArrayHasKey('automated', $result);
        $this->assertArrayHasKey('manual', $result);
        $this->assertArrayHasKey('rollback', $result);
        $this->assertArrayHasKey('health_score_before', $result);
        $this->assertArrayHasKey('health_score_after', $result);
        $this->assertArrayHasKey('health_improvement', $result);

        // Verify scanned and deleted counts
        $this->assertArrayHasKey('scanned', $result['automated']);
        $this->assertArrayHasKey('deleted', $result['automated']);
        $this->assertGreaterThanOrEqual($result['automated']['deleted'], $result['automated']['scanned']);
    }

    /**
     * Test performEnhancedCleanup does not delete recent backups
     *
     * @return void
     */
    #[Group('fast')]
    public function testPerformEnhancedCleanupPreservesRecentBackups(): void
    {
        // Create a recent backup
        $backupPath = $this->backupManager->createSchemaBackup('Recent Backup', ['settings']);

        $result = $this->backupManager->performEnhancedCleanup();

        // Recent backup should not be deleted
        $this->assertFileExists($backupPath);
        $this->assertEquals(0, $result['automated']['deleted']);
    }

    /**
     * Test backup file naming convention
     *
     * @return void
     */
    #[Group('fast')]
    public function testBackupFileNamingConvention(): void
    {
        $backupPath = $this->backupManager->createSchemaBackup('Test Naming', ['settings']);

        $filename = basename($backupPath);

        // Should match pattern: automated_schema-test-naming_development_YYYYmmdd_HHiiss.sql
        $this->assertMatchesRegularExpression(
            '/^automated_schema-test-naming_development_\d{8}_\d{6}\.sql$/',
            $filename
        );
    }

    /**
     * Test backup contains metadata header
     *
     * @return void
     */
    #[Group('fast')]
    public function testBackupContainsMetadata(): void
    {
        $backupPath = $this->backupManager->createSchemaBackup('Metadata Test', ['settings']);
        $content = file_get_contents($backupPath);

        $this->assertStringContainsString('-- BACKUP METADATA', $content);
        $this->assertStringContainsString('-- Type: automated', $content);
        $this->assertStringContainsString('-- Script: schema-metadata-test', $content);
        $this->assertStringContainsString('-- Environment: development', $content);
        $this->assertStringContainsString('-- Generator: BackupManager', $content);
    }

    /**
     * Test backup contains SQL statements
     *
     * @return void
     */
    #[Group('fast')]
    public function testBackupContainsSqlStatements(): void
    {
        $backupPath = $this->backupManager->createSchemaBackup('SQL Test', ['settings']);
        $content = file_get_contents($backupPath);

        $this->assertStringContainsString('CREATE TABLE', $content);
        $this->assertStringContainsString('INSERT INTO', $content);
    }

    /**
     * Test that cleanupOldBackups blocks symlink path traversal.
     *
     * Creates a symlink inside the backup directory pointing to a file
     * OUTSIDE the backup base. After ageing the symlink past the retention
     * cutoff, performEnhancedCleanup() must NOT delete the target file —
     * the realpath guard detects the traversal and skips the entry.
     *
     * The filename embeds `_development_` so that extractEnvironmentFromFilename()
     * resolves a real retention tier rather than falling through to its default,
     * keeping the test sensitive to changes in the environment-extraction regex.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testCleanupBlocksSymlinkTraversal(): void
    {
        if (!function_exists('symlink') || (PHP_OS_FAMILY === 'Windows' && !extension_loaded('com_dotnet'))) {
            $this->markTestSkipped('symlink() unavailable on this platform');
        }

        // Create a directory and target file OUTSIDE the backup base
        $outsideDir = sys_get_temp_dir() . '/outside_' . uniqid() . '/';
        mkdir($outsideDir);
        $secretPath = $outsideDir . 'secret.txt';
        file_put_contents($secretPath, 'sensitive data that must not be deleted');

        try {
            $symlinkPath = $this->testBackupDir
                . 'automated/elanregistry_automated_development_2024-01-01T000000_schema.sql';

            if (!@symlink($secretPath, $symlinkPath)) {
                $this->markTestSkipped('symlink() call failed (insufficient privileges or unsupported filesystem)');
            }

            // Age the symlink past any retention cutoff (100 days old)
            touch($symlinkPath, time() - 100 * 86400);

            $result = $this->backupManager->performEnhancedCleanup();

            // Target file outside backup base must still exist
            $this->assertFileExists($secretPath, 'Path-traversal target was deleted');

            // The realpath guard blocked deletion; deleted count for automated must be 0
            $this->assertSame(0, $result['automated']['deleted']);
        } finally {
            // $symlinkPath is assigned as the very first statement in the try block
            // (a plain string concatenation that can't itself throw), so it's always
            // set by the time finally runs.
            if (is_link($symlinkPath)) {
                unlink($symlinkPath);
            }
            $this->recursiveRemoveDirectory($outsideDir);
        }
    }

    /**
     * Test that cleanupOldBackups deletes a real old file inside the backup base.
     *
     * Companion to testCleanupBlocksSymlinkTraversal: verifies that the
     * realpath guard does NOT block legitimate deletions of aged backup files
     * that genuinely live within the backup directory.
     *
     * The filename embeds `_development_` so that extractEnvironmentFromFilename()
     * resolves a real retention tier rather than falling through to its default,
     * keeping the test sensitive to changes in the environment-extraction regex.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testCleanupDeletesOldFileWithinBackupBase(): void
    {
        // Create a real backup file (not a symlink) inside the backup base.
        // Filename matches extractEnvironmentFromFilename regex
        // /_(development|test|production)_/ so retention is resolved.
        $oldBackup = $this->testBackupDir
            . 'automated/elanregistry_automated_development_2024-01-01T000000_schema.sql';
        file_put_contents($oldBackup, "-- old backup content\n");

        // Age past development/automated retention (default 7 days)
        touch($oldBackup, time() - 100 * 86400);

        $result = $this->backupManager->performEnhancedCleanup();

        $this->assertSame(1, $result['automated']['deleted']);
        $this->assertFileDoesNotExist($oldBackup);
    }

    /**
     * Verify that cleanup respects per-type retention from config.php constants:
     * a file aged past the automated window (7 days) but within the manual window (30 days)
     * must be deleted from automated and kept in manual.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testCleanupRespectsPerTypeRetentionConstants(): void
    {
        // File aged 10 days — past automated (7d) but within manual (30d)
        $automatedBackup = $this->testBackupDir
            . 'automated/automated_manual-backup_development_20250101_120000.sql';
        $manualBackup = $this->testBackupDir
            . 'manual/manual_manual-backup_development_20250101_120000.sql';

        file_put_contents($automatedBackup, "-- automated\n");
        file_put_contents($manualBackup, "-- manual\n");

        $ageSeconds = 10 * 86400;
        touch($automatedBackup, time() - $ageSeconds);
        touch($manualBackup, time() - $ageSeconds);

        $result = $this->backupManager->performEnhancedCleanup();

        $this->assertSame(1, $result['automated']['deleted'], 'Automated backup past 7-day retention should be deleted');
        $this->assertSame(0, $result['manual']['deleted'], 'Manual backup within 30-day retention should be kept');
        $this->assertFileDoesNotExist($automatedBackup);
        $this->assertFileExists($manualBackup);
    }

    /**
     * Verify analyzeRetention() bucket classification: a fresh backup (seconds old)
     * always lands in within_policy, never approaching_expiry or expired.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testAnalyzeRetentionClassifiesFreshBackupAsWithinPolicy(): void
    {
        $freshBackup = $this->testBackupDir
            . 'manual/manual_fresh-backup_development_20250101_120000.sql';
        file_put_contents($freshBackup, "-- fresh\n");
        // Leave mtime at now (default)

        $stats = $this->backupManager->getEnhancedBackupStatistics();

        $manualAnalysis = $stats['retention_analysis']['manual'] ?? [];
        $this->assertGreaterThan(0, $manualAnalysis['within_policy'] ?? 0, 'Fresh manual backup should be within_policy');
        $this->assertSame(0, $manualAnalysis['approaching_expiry'] ?? 0, 'Fresh manual backup should not be approaching_expiry');
        $this->assertSame(0, $manualAnalysis['expired'] ?? 0, 'Fresh manual backup should not be expired');
    }

    /**
     * Verify that a failed data query (SELECT *) during table dumping aborts the
     * whole backup and leaves no partial backup file on disk.
     *
     * generateTableDump() now checks $this->db->error() after the data query and
     * throws BackupException instead of silently continuing. Because
     * createStandardizedBackup() only calls file_put_contents() after every table
     * dumps successfully, the thrown exception must propagate all the way out of
     * createManualBackup() with zero bytes written to the manual/ directory.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testTableDumpDataQueryFailureAbortsBackup(): void
    {
        $mockDb = $this->createMockDatabase('SELECT * FROM `cars`');
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        $filesBefore = glob($this->testBackupDir . 'manual/*.sql');

        try {
            $backupManager->createManualBackup('Data Query Failure', ['cars']);
            $this->fail('Expected BackupException was not thrown');
        } catch (BackupException $e) {
            $this->assertStringContainsString('Failed to read data for table cars', $e->getMessage());
        }

        $filesAfter = glob($this->testBackupDir . 'manual/*.sql');
        $this->assertSame($filesBefore, $filesAfter, 'No partial backup file should be written when the data query fails');
    }

    /**
     * Verify an aborted backup leaves no `.partial` temp file behind.
     *
     * Since #1714 the dump is streamed to `{backupPath}.partial` and renamed only
     * once every table has written, so a failure part-way through leaves a temp file
     * that the outer catch must remove. Every other failure-path assertion in this
     * class globs `*.sql`, which a leftover `.partial` would slip straight past —
     * so a regression in that cleanup unlink() would go unnoticed while still
     * accumulating litter in the backup directory. This asserts on a bare `*` glob
     * for that reason: nothing at all should remain.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testAbortedBackupLeavesNoPartialTempFile(): void
    {
        $mockDb = $this->createMockDatabase('SELECT * FROM `cars`');
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        try {
            $backupManager->createManualBackup('Partial Cleanup', ['cars']);
            $this->fail('Expected BackupException was not thrown');
        } catch (BackupException $e) {
            // The abort itself is asserted by testTableDumpDataQueryFailureAbortsBackup();
            // this test is only interested in what it left on disk.
        }

        $this->assertSame(
            [],
            glob($this->testBackupDir . 'manual/*.partial'),
            'An aborted backup must not leave a .partial temp file behind'
        );
        $this->assertSame(
            [],
            glob($this->testBackupDir . 'manual/*'),
            'An aborted backup must leave the backup directory empty, not merely free of .sql files'
        );
    }

    /**
     * Verify that a failed structure query (SHOW CREATE TABLE) during table dumping
     * aborts the whole backup and leaves no partial backup file on disk.
     *
     * Companion to testTableDumpDataQueryFailureAbortsBackup(): the structure query
     * is checked first in generateTableDump(), so this exercises the earlier of the
     * two error branches.
     *
     * Also verifies the category split introduced alongside the 42S02/1146
     * soft-warning path: generateTableDump()'s own catch now logs the per-table
     * detail under LOG_CATEGORY_BACKUP_ERROR (it no longer raises the BackupFailed
     * alarm itself), while createStandardizedBackup()'s outer catch — the single
     * choke point for "a backup was attempted and did not complete" — logs the
     * propagated failure under LOG_CATEGORY_BACKUP_FAILED. This directly proves
     * BackupFailed is emitted from the outer catch, not (also/instead) from
     * generateTableDump()'s catch.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testTableDumpStructureQueryFailureAbortsBackup(): void
    {
        global $mockLogEntries;
        $mockLogEntries = [];

        $mockDb = $this->createMockDatabase('SHOW CREATE TABLE `cars`');
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        $filesBefore = glob($this->testBackupDir . 'manual/*.sql');

        try {
            $backupManager->createManualBackup('Structure Query Failure', ['cars']);
            $this->fail('Expected BackupException was not thrown');
        } catch (BackupException $e) {
            $this->assertStringContainsString('Failed to read structure for table cars', $e->getMessage());
        }

        $filesAfter = glob($this->testBackupDir . 'manual/*.sql');
        $this->assertSame($filesBefore, $filesAfter, 'No partial backup file should be written when the structure query fails');

        $backupFailedEntries = array_filter(
            $mockLogEntries,
            fn($e) => $e['category'] === LogCategories::LOG_CATEGORY_BACKUP_FAILED
                && str_contains($e['message'], 'Backup aborted for')
        );
        $this->assertNotEmpty(
            $backupFailedEntries,
            'createStandardizedBackup() should log LOG_CATEGORY_BACKUP_FAILED for the propagated table-dump failure'
        );

        $tableDumpErrorEntries = array_filter(
            $mockLogEntries,
            fn($e) => $e['category'] === LogCategories::LOG_CATEGORY_BACKUP_ERROR
                && str_contains($e['message'], 'Error backing up table cars')
        );
        $this->assertNotEmpty(
            $tableDumpErrorEntries,
            'generateTableDump() should log the per-table detail under LOG_CATEGORY_BACKUP_ERROR, not LOG_CATEGORY_BACKUP_FAILED'
        );
    }

    /**
     * Verify that a table dump for a table that does not exist (MySQL error 1146,
     * SQLSTATE 42S02) aborts the backup rather than degrading to a warning.
     *
     * This test previously asserted the opposite — that a missing table produced a
     * warning comment and the backup file was still written. #1696 reversed that
     * decision: getCriticalTables() listed `car_history`, which is not a real table
     * (it is `cars_hist`), so generateTableDump() emitted a warning comment and the
     * backup reported success — silently dropping the car audit trail from every
     * manual backup until the omission surfaced at restore time. A backup that
     * reports success while missing a table the caller explicitly requested is worse
     * than no backup at all.
     *
     * The SQLSTATE/driver-code distinction in isTableNotFoundError() still matters
     * and is still exercised here: 42S02 must produce the specific "table does not
     * exist" message rather than the generic structure-read failure message.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testTableDumpTableNotFoundAbortsBackup(): void
    {
        $mockDb = $this->createMockDatabase(null, 0, 'SHOW CREATE TABLE `missing_table`');
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        $this->expectException(BackupException::class);
        $this->expectExceptionMessage("Cannot back up 'missing_table': table does not exist");

        $backupManager->createManualBackup('Missing Table Test', ['missing_table', 'cars']);
    }

    /**
     * Verify that aborting on a missing table leaves no backup file behind.
     *
     * createStandardizedBackup() only writes the file once every requested table has
     * dumped successfully, so a caller that sees the exception can rely on there
     * being no half-written artifact to mistake for a good backup later (#1696).
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testMissingTableLeavesNoBackupFile(): void
    {
        $mockDb = $this->createMockDatabase(null, 0, 'SHOW CREATE TABLE `missing_table`');
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        $before = glob($this->testBackupDir . '/**/*.sql') ?: [];

        try {
            $backupManager->createManualBackup('Missing Table Test', ['missing_table', 'cars']);
            $this->fail('Expected BackupException for a missing table');
        } catch (BackupException) {
            // expected
        }

        $after = glob($this->testBackupDir . '/**/*.sql') ?: [];
        $this->assertSame($before, $after, 'A failed backup must not leave a file behind');
    }

    /**
     * Verify that a SHOW CREATE TABLE that succeeds but returns no rows aborts the
     * backup instead of writing a corrupt dump.
     *
     * The real `\DB::first()` returns `[]` — not null and not an object — when there
     * are no rows, and `[]->{'Create Table'}` silently evaluates to null. Without the
     * is_object() guard in generateTableDump() the dump would be written with a bare
     * `;` where the CREATE statement belongs, producing a backup file that looks
     * healthy but cannot restore the table. This is reachable in practice when a table
     * is dropped between the caller listing tables and the dump reaching it.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testTableDumpMissingStructureRowAbortsBackup(): void
    {
        $mockDb = $this->createMockDatabase(null, 0, null, 'SHOW CREATE TABLE `cars`');
        $backupManager = new BackupManager($mockDb, $this->testBackupDir, 1);

        $filesBefore = glob($this->testBackupDir . 'manual/*.sql');

        try {
            $backupManager->createManualBackup('Missing Structure Row', ['cars']);
            $this->fail('Expected BackupException was not thrown');
        } catch (BackupException $e) {
            $this->assertStringContainsString('No structure returned for table cars', $e->getMessage());
        }

        $filesAfter = glob($this->testBackupDir . 'manual/*.sql');
        $this->assertSame(
            $filesBefore,
            $filesAfter,
            'No backup file should be written when the structure row is missing'
        );
    }

    /**
     * Verify that a backup-directory creation failure (mkdir() failing inside
     * createStandardizedBackup(), before generateTableDump() is ever reached) is
     * logged under LOG_CATEGORY_BACKUP_FAILED — proving the other new source of
     * BackupFailed (not just a propagated table-dump failure) works too.
     *
     * A fresh backup base directory is used (unlike $this->testBackupDir from
     * setUp(), whose automated/manual/rollback/ subdirectories already exist) and
     * made read-only, so mkdir()'s attempt to create manual/ on demand fails.
     * mkdir() emits a PHP E_WARNING on failure that createStandardizedBackup() does
     * not suppress; a temporary error handler captures it, matching the pattern used
     * in testVerifyBackupIntegrityFopenFailureGuard().
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testMkdirFailureLogsBackupFailedFromCreateStandardizedBackup(): void
    {
        if (posix_getuid() === 0) {
            $this->markTestSkipped('Cannot test mkdir() failure as root — permission checks have no effect.');
        }

        global $mockLogEntries;
        $mockLogEntries = [];

        $readonlyBase = sys_get_temp_dir() . '/backup_readonly_' . uniqid() . '/';
        mkdir($readonlyBase);
        chmod($readonlyBase, 0555);

        $capturedWarning = null;
        set_error_handler(
            static function (int $errno, string $errstr) use (&$capturedWarning): bool {
                if ($errno === E_WARNING) {
                    $capturedWarning = $errstr;
                    return true;
                }
                return false;
            },
            E_WARNING
        );

        try {
            $backupManager = new BackupManager($this->mockDb, $readonlyBase, 1);

            try {
                $backupManager->createManualBackup('Mkdir Failure', ['settings']);
                $this->fail('Expected BackupException was not thrown');
            } catch (BackupException $e) {
                $this->assertStringContainsString('Failed to create backup directory', $e->getMessage());
            }
        } finally {
            restore_error_handler();
            chmod($readonlyBase, 0755);
            $this->recursiveRemoveDirectory($readonlyBase);
        }

        $this->assertNotNull(
            $capturedWarning,
            'A PHP E_WARNING should be emitted when mkdir() fails on a read-only parent directory.'
        );

        $backupFailedEntries = array_filter(
            $mockLogEntries,
            fn($e) => $e['category'] === LogCategories::LOG_CATEGORY_BACKUP_FAILED
                && str_contains($e['message'], 'Backup aborted for')
        );
        $this->assertNotEmpty(
            $backupFailedEntries,
            'createStandardizedBackup() should log LOG_CATEGORY_BACKUP_FAILED when mkdir() fails, not just on propagated table-dump failures'
        );
    }

    /**
     * Verify createStandardizedBackup() logs LOG_CATEGORY_BACKUP_FAILED even when the
     * failure surfaces as a raw \Throwable rather than a BackupException — e.g. a
     * PDOException from a DB connection dropping mid-dump. The outer catch widens to
     * \Throwable specifically so this doesn't silently skip the BackupFailed alarm and
     * leave the health badge falsely "Healthy" for a backup that never wrote a file.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testNonBackupExceptionFailureStillLogsBackupFailed(): void
    {
        global $mockLogEntries;
        $mockLogEntries = [];

        $backupManager = new BackupManager(new BackupManagerThrowingDatabase(), $this->testBackupDir, 1);

        try {
            $backupManager->createManualBackup('Connection Drop', ['settings']);
            $this->fail('Expected \RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertSame('MySQL server has gone away', $e->getMessage());
        }

        $backupFailedEntries = array_filter(
            $mockLogEntries,
            fn($e) => $e['category'] === LogCategories::LOG_CATEGORY_BACKUP_FAILED
                && str_contains($e['message'], 'Backup aborted for')
        );
        $this->assertNotEmpty(
            $backupFailedEntries,
            'createStandardizedBackup() should log LOG_CATEGORY_BACKUP_FAILED even when the failure is a raw \Throwable, not a BackupException'
        );
    }

    /**
     * Verify that getEnhancedBackupStatistics() reflects recent backup failures and
     * that the health score deducts exactly 30 points when hasRecentBackupFailures()
     * is true, compared to an otherwise-identical run with no recent failures.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testHealthScoreReflectsRecentBackupFailures(): void
    {
        // Same on-disk backup state is used for both calls below, so the only
        // difference between the two health scores is the recent_failures signal.
        $this->backupManager->createSchemaBackup('Health Failure Baseline', ['settings']);

        $baselineStats = $this->backupManager->getEnhancedBackupStatistics();
        $this->assertFalse($baselineStats['recent_failures']);

        $failureMockDb = $this->createMockDatabase(null, 1);
        $failureBackupManager = new BackupManager($failureMockDb, $this->testBackupDir, 1);

        $failureStats = $failureBackupManager->getEnhancedBackupStatistics();

        $this->assertTrue($failureStats['recent_failures']);
        $this->assertSame(
            $baselineStats['health_score'] - 30,
            $failureStats['health_score'],
            'Recent backup failures should deduct exactly 30 points from the health score'
        );
    }

    /**
     * Verify that hasRecentBackupFailures() fails open when the logs COUNT(*) query
     * itself errors: getEnhancedBackupStatistics() must not throw, `recent_failures`
     * must report false (not counted as a failure), and the health score must be
     * identical to a plain happy-path baseline on the same on-disk backup state —
     * proving the failed check has zero effect on the score rather than silently
     * being treated as a failure or blowing up the whole statistics call.
     *
     * @return void
     */
    #[Group('fast')]
    #[Group('unit')]
    public function testRecentBackupFailuresCheckFailsOpenOnDbError(): void
    {
        // Plain happy-path baseline, using the default (non-failing) mock database
        // set up in setUp(). Same on-disk backup state is reused for the fail-open
        // call below so the only variable is the logs-query outcome.
        $this->backupManager->createSchemaBackup('Fail Open Baseline', ['settings']);
        $baselineStats = $this->backupManager->getEnhancedBackupStatistics();
        $this->assertFalse($baselineStats['recent_failures']);

        // Mock database that fails only the 'FROM logs' COUNT(*) query, mirroring
        // the "logs table hiccup" scenario hasRecentBackupFailures() is documented
        // to fail open on.
        $failingLogsDb = $this->createMockDatabase('FROM logs');
        $failingLogsBackupManager = new BackupManager($failingLogsDb, $this->testBackupDir, 1);

        $failureStats = $failingLogsBackupManager->getEnhancedBackupStatistics();

        $this->assertFalse(
            $failureStats['recent_failures'],
            'A logs-query DB error must fail open (false), not be treated as a recent failure'
        );
        $this->assertSame(
            $baselineStats['health_score'],
            $failureStats['health_score'],
            'A failed (fail-open) recent-failures check must not affect the health score'
        );
    }

    /**
     * Helper: Create the DatabaseInterface double BackupManager runs against
     *
     * See BackupManagerFakeDatabase (foot of this file) for the simulated behaviour
     * and what each parameter controls.
     *
     * @param string|null $failOnSqlSubstring Substring to match against query SQL; when
     *                                        matched, that query is simulated as a
     *                                        generic (non-table-not-found) failure
     * @param int $recentFailureCount Canned `cnt` value returned by the `logs` COUNT(*)
     *                                query (used by hasRecentBackupFailures())
     * @param string|null $tableNotFoundOnSqlSubstring Substring to match against query
     *                                        SQL; when matched, that query is simulated
     *                                        as a MySQL 1146/42S02 "table not found" failure
     * @param string|null $emptyResultOnSqlSubstring Substring to match against query SQL;
     *                                        when matched, that query is simulated as
     *                                        succeeding but returning no rows
     * @param string[]|null $tableNames Table names the information_schema.TABLES
     *                                        discovery query (getAllTables()) returns,
     *                                        as TABLE_NAME rows. Defaults to a single
     *                                        canned table when null.
     */
    private function createMockDatabase(
        ?string $failOnSqlSubstring = null,
        int $recentFailureCount = 0,
        ?string $tableNotFoundOnSqlSubstring = null,
        ?string $emptyResultOnSqlSubstring = null,
        ?array $tableNames = null
    ): BackupManagerFakeDatabase
    {
        return new BackupManagerFakeDatabase(
            $failOnSqlSubstring,
            $recentFailureCount,
            $tableNotFoundOnSqlSubstring,
            $emptyResultOnSqlSubstring,
            $tableNames
        );
    }

    /**
     * Helper: Recursively remove a directory and its contents
     *
     * @param string $dir Directory path to remove
     * @return void
     */
    private function recursiveRemoveDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->recursiveRemoveDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

/**
 * DatabaseInterface double for BackupManager, mirroring the real `\DB`: `query()`
 * returns the same instance, and `count()`/`first()`/`results()`/`error()`/
 * `errorString()`/`errorInfo()` all report the outcome of that most recent call on
 * that same object. UserSpice flags a failed query via `error()` rather than throwing,
 * and `errorInfo()` carries the SQLSTATE/driver code that BackupManager's
 * `isTableNotFoundError()` inspects to distinguish a missing table from a genuine
 * failure.
 *
 * By default every query "succeeds": table-structure/data queries return two canned
 * rows, and the `logs` recent-failures COUNT(*) query returns `$recentFailureCount`
 * (default 0, i.e. no recent failures) — but only when the bound `logtype` param
 * (the query's first bound parameter) equals `LogCategories::LOG_CATEGORY_BACKUP_FAILED`;
 * any other logtype value (or no bound params at all) yields 0 regardless of
 * `$recentFailureCount`, so this double only reports failures for the category
 * `hasRecentBackupFailures()` is actually documented to query.
 *
 * The three SQL-substring parameters are independent — a test can configure any
 * combination to target different queries without affecting unrelated ones:
 *
 * - `$failOnSqlSubstring` — the matched query fails generically: `error()` becomes
 *   true and `errorInfo()` reports `['HY000', 2006, ...]`, so `isTableNotFoundError()`
 *   correctly treats it as a genuine failure.
 * - `$tableNotFoundOnSqlSubstring` — the matched query fails with MySQL's "table not
 *   found" error (`['42S02', 1146, ...]`), routing it to the soft-warning path.
 * - `$emptyResultOnSqlSubstring` — the matched query *succeeds* but returns no rows,
 *   so `first()` returns `[]` exactly as the real `\DB` does.
 *
 * A fourth, independent constructor parameter, `$tableNames`, controls what
 * `getAllTables()`'s `information_schema.TABLES` discovery query returns: each name
 * becomes a `TABLE_NAME` row, matching the real MySQL result shape. It defaults to a
 * single canned table so tests that don't care about discovery still get a non-empty
 * result. The three SQL-substring parameters above are checked first and take
 * priority, so a test can still force the discovery query itself to fail or return
 * empty via `$failOnSqlSubstring`/`$emptyResultOnSqlSubstring` targeting
 * `'information_schema.TABLES'`.
 *
 * Deliberately a *named* class rather than an anonymous `new class extends
 * FakeDatabase`: PHPStan reports `impureMethod.pure` when an anonymous class overrides
 * one of DatabaseInterface's `@phpstan-impure` methods with a side-effect-free body,
 * because an anonymous class can never be extended to add the side effect later.
 */
class BackupManagerFakeDatabase extends FakeDatabase
{
    private bool $lastQueryFailed = false;

    /** @var array<int, mixed> PDO errorInfo triple for the most recent query */
    private array $lastErrorInfo = ['', 0, ''];

    /** @var array<int, \stdClass> Rows returned by the most recent query */
    private array $lastRows = [];

    /** @var string[] Table names returned by the information_schema.TABLES discovery query */
    private readonly array $tableNames;

    /**
     * @param string[]|null $tableNames Table names getAllTables()'s discovery query
     *                                  returns as TABLE_NAME rows. Defaults to a
     *                                  single canned table (['settings']) when null.
     */
    public function __construct(
        private readonly ?string $failOnSqlSubstring = null,
        private readonly int $recentFailureCount = 0,
        private readonly ?string $tableNotFoundOnSqlSubstring = null,
        private readonly ?string $emptyResultOnSqlSubstring = null,
        ?array $tableNames = null
    ) {
        $this->tableNames = $tableNames ?? ['settings'];
    }

    /**
     * @param array<mixed> $params
     */
    public function query(string $sql, array $params = []): self
    {
        $this->lastQueryFailed = false;
        $this->lastErrorInfo = ['', 0, ''];
        $this->lastRows = [];

        if ($this->tableNotFoundOnSqlSubstring !== null && str_contains($sql, $this->tableNotFoundOnSqlSubstring)) {
            $this->lastQueryFailed = true;
            $this->lastErrorInfo = ['42S02', 1146, "Table doesn't exist"];
            return $this;
        }

        if ($this->failOnSqlSubstring !== null && str_contains($sql, $this->failOnSqlSubstring)) {
            $this->lastQueryFailed = true;
            $this->lastErrorInfo = ['HY000', 2006, 'MySQL server has gone away'];
            return $this;
        }

        if ($this->emptyResultOnSqlSubstring !== null && str_contains($sql, $this->emptyResultOnSqlSubstring)) {
            // Succeeds, but with no rows — first() stays `[]`
            return $this;
        }

        if (str_contains($sql, 'information_schema.TABLES')) {
            $this->lastRows = array_map(
                static function (string $tableName): \stdClass {
                    $row = new \stdClass();
                    $row->TABLE_NAME = $tableName;
                    return $row;
                },
                $this->tableNames
            );

            return $this;
        }

        if (str_contains($sql, 'FROM logs')) {
            $matchesFailedCategory = ($params[0] ?? null) === LogCategories::LOG_CATEGORY_BACKUP_FAILED;

            $countRow = new \stdClass();
            $countRow->cnt = $matchesFailedCategory ? $this->recentFailureCount : 0;
            $this->lastRows = [$countRow];

            return $this;
        }

        // Successful table structure/data query
        $createTableObj = new \stdClass();
        $createTableObj->{'Create Table'} = 'CREATE TABLE `settings` (`id` int) ENGINE=InnoDB';

        $dataObj = new \stdClass();
        $dataObj->id = 1;
        $dataObj->meta_key = 'test';
        $dataObj->meta_value = 'value';

        $this->lastRows = [$createTableObj, $dataObj];

        return $this;
    }

    /**
     * @return bool True when the most recent query was configured to fail
     */
    public function error(): bool
    {
        return $this->lastQueryFailed;
    }

    /**
     * @return string Canned description of the most recent failure, or empty on success
     */
    public function errorString(): string
    {
        return $this->lastQueryFailed ? 'Mock database error: query failed' : '';
    }

    /**
     * @return array<int, mixed> PDO errorInfo triple for the most recent query
     */
    public function errorInfo(): array
    {
        return $this->lastErrorInfo;
    }

    /**
     * @return int Row count of the most recent result set
     */
    public function count(): int
    {
        return count($this->lastRows);
    }

    /**
     * @return array<string, mixed>|object First row, or `[]` when there are none —
     *                                     matching the real `\DB`, which never returns null
     */
    public function first(bool $assoc = false): array|object
    {
        return $this->lastRows[0] ?? [];
    }

    /**
     * @return array<int, \stdClass> Rows from the most recent result set
     */
    public function results(bool $assoc = false): array
    {
        return $this->lastRows;
    }
}

/**
 * DatabaseInterface double whose every query throws a raw \Throwable, standing in for
 * a connection-level fault (e.g. the DB going away mid-dump) that surfaces as an
 * exception rather than UserSpice's error() flag.
 *
 * Named rather than anonymous for the same PHPStan `impureMethod.pure` reason
 * documented on BackupManagerFakeDatabase.
 */
class BackupManagerThrowingDatabase extends FakeDatabase
{
    /**
     * @param array<mixed> $params
     */
    public function query(string $sql, array $params = []): self
    {
        throw new \RuntimeException('MySQL server has gone away');
    }
}
