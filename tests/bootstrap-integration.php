<?php

declare(strict_types=1);

/**
 * PHPUnit Bootstrap File for Integration Tests
 *
 * Sets up the testing environment by loading the REAL UserSpice framework
 * and connecting to the actual database.
 * NO MOCKS are used (except when creating test fixtures).
 * Use this for: tests/integration/*
 *
 * For unit tests, use: tests/bootstrap-unit.php
 */

// Mark as integration test suite - prevents mock loading
define('INTEGRATION_TEST_SUITE', true);
define('TESTING', true);

// Set up basic paths
$projectRoot = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $projectRoot;
$_SERVER['PHP_SELF'] = '/tests/';

// Set up testing environment
define('TESTING_ROOT', $projectRoot);

// Load Composer autoloader for project classes FIRST (before UserSpice)
require_once $projectRoot . '/vendor/autoload.php';

// Load UserSpice framework for real database testing and authentication
$initPath = $projectRoot . '/users/init.php';
if (!file_exists($initPath)) {
    fwrite(STDERR, "ERROR: UserSpice initialization file not found at: {$initPath}\n");
    fwrite(STDERR, "Integration tests require UserSpice framework to be installed.\n");
    fwrite(STDERR, "To skip integration tests, use: composer test:unit\n");
    exit(1);
}

// Integration tests must run against a DEDICATED test schema, never the dev
// database. We require .env.test.local (pointing at that schema) and refuse to
// fall back to .env.local/.env, because a fallback risks running destructive
// integration tests against the live development database.
$envTestLocal = $projectRoot . '/.env.test.local';
if (!file_exists($envTestLocal)) {
    fwrite(STDERR, "ERROR: Integration test environment file not found at: {$envTestLocal}\n");
    fwrite(STDERR, "Integration tests require .env.test.local pointing at a dedicated test schema (e.g. elanregi_spice_test).\n");
    fwrite(STDERR, "This bootstrap intentionally does NOT fall back to .env.local or .env, because that risks\n");
    fwrite(STDERR, "running destructive integration tests against the development database.\n");
    fwrite(STDERR, "To set up: cp .env.test.local.sample .env.test.local, then fill in DB_PASS.\n");
    fwrite(STDERR, "See docs/development/ENVIRONMENT.md for details.\n");
    exit(1);
}

// The Dotenv class is available because vendor/autoload.php was loaded above.
// createMutable() allows init.php's createImmutable() to read our test values from $_ENV.
try {
    \Dotenv\Dotenv::createMutable($projectRoot, '.env.test.local')->load();
    fwrite(STDERR, "NOTE: Loaded test environment from .env.test.local\n");
} catch (\Dotenv\Exception\ExceptionInterface $e) {
    // A load failure here is fatal: we have no reliable way to know which
    // database we would connect to, so proceeding at all would be unsafe.
    fwrite(STDERR, "ERROR: Could not load .env.test.local: {$e->getMessage()}\n");
    fwrite(STDERR, "Integration tests cannot run without a valid test environment. Aborting.\n");
    exit(1);
}

// Defense-in-depth: refuse to proceed if the test environment points at the dev database.
// Case-folded and trimmed because MAMP's MySQL runs with lower_case_table_names=2 on
// macOS's case-insensitive filesystem, so ELANREGI_SPICE and elanregi_spice are the same
// physical database — a naive === comparison would miss a typo'd-case DB_NAME.
$configuredDbName = strtolower(trim($_ENV['DB_NAME'] ?? ''));
if ($configuredDbName === 'elanregi_spice') {
    fwrite(STDERR, "ERROR: .env.test.local is pointed at the development database (elanregi_spice).\n");
    fwrite(STDERR, "Integration tests must use a dedicated test schema (e.g. elanregi_spice_test).\n");
    fwrite(STDERR, "Update DB_NAME in .env.test.local before running integration tests. Aborting.\n");
    exit(1);
}

// Suppress UserSpice initialization errors (especially database connection errors)
// Integration tests will handle the missing database gracefully via IntegrationTestCase
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    // Suppress all errors during bootstrap - integration tests will handle gracefully
    return true;
});

// Start output buffering to catch any die() output messages
ob_start();

try {
    require_once $initPath;
} catch (Throwable $e) {
    // Capture UserSpice initialization errors
    fwrite(STDERR, "NOTE: UserSpice initialization error: {$e->getMessage()}\n");
}

// Get any output from die() and clear it
$output = ob_get_clean();
if (!empty(trim($output))) {
    fwrite(STDERR, "NOTE: {$output}\n");
}

restore_error_handler();

// Ensure $user global is properly initialized for getSettings() calls
// If users/init.php didn't fully initialize $user, create a minimal User object
if (!isset($GLOBALS['user']) || $GLOBALS['user'] === null) {
    if (class_exists('User')) {
        try {
            $GLOBALS['user'] = new User();
            fwrite(STDERR, "NOTE: Initialized \$user global for integration tests\n");
        } catch (Throwable $e) {
            fwrite(STDERR, "NOTE: Could not initialize \$user global: {$e->getMessage()}\n");
        }
    }
}

// Try to verify database connection and reinitialize $db if configuration was fixed
try {
    if (class_exists('DB')) {
        // Reset the DB singleton cache to force reconnection with corrected config
        // The DB class caches the PDO connection, so we need to clear it to force a new one
        $reflectionClass = new ReflectionClass('DB');
        $instanceProperty = $reflectionClass->getProperty('_instance');
        $instanceProperty->setValue(null, null); // static property: first arg is object (null), second is new value
        fwrite(STDERR, "NOTE: Reset DB singleton cache for reinitialization\n");

        // Now create a fresh DB instance with the corrected configuration
        $testDb = DB::getInstance();
        $result = $testDb->query("SELECT 1");
        fwrite(STDERR, "NOTE: Database connection verified for integration tests\n");

        // Defense-in-depth: check the database ACTUALLY connected to, not just the
        // $_ENV value read earlier. If .env.test.local omits DB_NAME (or any other
        // DB_* key), users/init.php's own createImmutable()->safeLoad() of the root
        // .env backfills the missing key(s) from the dev config — the earlier $_ENV
        // guard can't see that, because it runs before init.php loads at all. This
        // check reflects the connection as it truly resolved, closing that gap.
        //
        // This has its own try/catch (rather than sharing the outer one, which
        // treats failure as a non-fatal "NOTE") because a failure HERE means we
        // couldn't confirm which database we're connected to — that must abort,
        // not be logged as a passive reconnection hiccup and continue anyway.
        try {
            $connectedDb = strtolower(trim((string)($testDb->query('SELECT DATABASE() AS name')->first()?->name ?? '')));
        } catch (\Throwable $e) {
            fwrite(STDERR, "ERROR: Could not verify the connected database's identity: {$e->getMessage()}\n");
            fwrite(STDERR, "Refusing to proceed without confirming this is not the development database.\n");
            exit(1);
        }
        if ($connectedDb === 'elanregi_spice') {
            fwrite(STDERR, "ERROR: Integration tests connected to the development database (elanregi_spice).\n");
            fwrite(STDERR, "This likely means .env.test.local is missing one or more DB_* keys, which were\n");
            fwrite(STDERR, "backfilled from the root .env file. Ensure .env.test.local sets DB_HOST, DB_USER,\n");
            fwrite(STDERR, "DB_NAME, and DB_PASS explicitly. Aborting.\n");
            exit(1);
        }

        // Re-initialize the global $db after configuration fixes
        // This ensures $db in tests uses the corrected configuration
        $GLOBALS['db'] = $testDb;
        fwrite(STDERR, "NOTE: Re-initialized global \$db for integration tests\n");
    }
} catch (Throwable $e) {
    fwrite(STDERR, "NOTE: Database reconnection attempt failed: {$e->getMessage()}\n");
}

// ============================================================
// Auto-load Reference Data for Integration Tests
// ============================================================
// Integration tests require car_models table to be populated.
// Automatically load reference data if the table is empty.

try {
    if (class_exists('DB')) {
        $db = DB::getInstance();

        // Check if car_models table exists and is empty
        $count = $db->query("SELECT COUNT(*) as cnt FROM car_models")->first();

        if ($count && $count->cnt == 0) {
            fwrite(STDERR, "NOTE: car_models table is empty, loading reference data...\n");

            // Load reference data from SQL file
            $refDataPath = dirname(__DIR__) . '/database/2-reference-data.sql';

            if (file_exists($refDataPath)) {
                $refDataSql = file_get_contents($refDataPath);

                if ($refDataSql !== false) {
                    // Extract just the car_models INSERT statement
                    $carModelsPattern = '/INSERT IGNORE INTO `car_models`.*?VALUES\s*(.*?);/s';

                    if (preg_match($carModelsPattern, $refDataSql, $matches)) {
                        $carModelsInsert = "INSERT IGNORE INTO `car_models`
                          (`year_available_from`, `year_available_to`, `display_name`,
                           `human_readable_short`, `series`, `variant`, `type_code`, `model_value`)
                        VALUES " . $matches[1] . ";";

                        // Execute the INSERT
                        $db->query($carModelsInsert);

                        // Verify loaded
                        $newCount = $db->query("SELECT COUNT(*) as cnt FROM car_models")->first();
                        $loadedCount = (int)($newCount?->cnt ?? 0);

                        fwrite(STDERR, "NOTE: Loaded {$loadedCount} car_models records for integration tests\n");
                    } else {
                        fwrite(STDERR, "NOTE: Could not parse car_models INSERT from reference data file\n");
                    }
                } else {
                    fwrite(STDERR, "NOTE: Failed to read reference data file\n");
                }
            } else {
                fwrite(STDERR, "NOTE: Reference data file not found: {$refDataPath}\n");
            }
        } else {
            $recordCount = (int)($count?->cnt ?? 0);
            fwrite(STDERR, "NOTE: car_models table already populated with {$recordCount} records\n");
        }
    }
} catch (Throwable $e) {
    fwrite(STDERR, "NOTE: Failed to load reference data: {$e->getMessage()}\n");
    // Non-fatal: tests requiring car_models will handle gracefully
}
