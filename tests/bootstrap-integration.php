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

/**
 * Write each message line to STDERR and exit(1).
 *
 * Consolidates the repeated "fwrite(STDERR, ...); ...; exit(1);" pattern used
 * throughout the reference-data and settings auto-load blocks below, which
 * fail loudly rather than let tests run against a silently broken environment.
 *
 * @param string ...$lines Message lines, printed in order (no trailing "\n" needed).
 */
function abortBootstrap(string ...$lines): never
{
    foreach ($lines as $line) {
        fwrite(STDERR, $line . "\n");
    }
    exit(1);
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

                        if ($loadedCount === 0) {
                            abortBootstrap(
                                "ERROR: car_models INSERT ran but the table is still empty.",
                                "Integration tests that depend on car_models cannot run. Aborting.",
                                "Run: ./scripts/create-test-schema.sh to reset the test schema, then re-run tests."
                            );
                        }

                        fwrite(STDERR, "NOTE: Loaded {$loadedCount} car_models records for integration tests\n");
                    } else {
                        abortBootstrap(
                            "ERROR: Could not parse the car_models INSERT from {$refDataPath}.",
                            "The file's format may have changed and this bootstrap's parsing regex needs updating. Aborting.",
                            "Run: ./scripts/create-test-schema.sh to reset the test schema, then re-run tests.",
                            "If that does not help, database/2-reference-data.sql or the bootstrap regex needs attention."
                        );
                    }
                } else {
                    abortBootstrap(
                        "ERROR: Failed to read reference data file: {$refDataPath}",
                        "Check the file's permissions and readability. Aborting.",
                        "Run: ./scripts/create-test-schema.sh to reset the test schema, then re-run tests."
                    );
                }
            } else {
                abortBootstrap(
                    "ERROR: Reference data file not found: {$refDataPath}",
                    "car_models cannot be populated and integration tests would fail confusingly. Aborting.",
                    "Run: ./scripts/create-test-schema.sh to reset the test schema, then re-run tests.",
                    "If that does not help, database/2-reference-data.sql itself may be missing or misnamed."
                );
            }
        } else {
            $recordCount = (int)($count?->cnt ?? 0);
            fwrite(STDERR, "NOTE: car_models table already populated with {$recordCount} records\n");
        }
    }
} catch (Throwable $e) {
    abortBootstrap(
        "ERROR: Failed to load reference data: {$e->getMessage()}",
        "Integration tests that depend on car_models cannot run. Aborting.",
        "Run: ./scripts/create-test-schema.sh to reset the test schema, then re-run tests."
    );
}

// ============================================================
// Auto-seed the settings row for Integration Tests
// ============================================================
// Car::__construct() and other framework code call getSettings()/query
// `settings WHERE id = 1` and silently no-op (never loading real data) if
// that lookup returns nothing — so a missing settings row doesn't throw, it
// makes unrelated code paths behave as if the row being looked up doesn't
// exist either. The `settings` table has ~100 UserSpice-core columns with no
// canonical INSERT tracked in this repo (it's normally created by the
// UserSpice installer, which isn't part of this checkout). Rather than
// hand-maintain a giant literal INSERT that drifts from the schema, build
// one dynamically: satisfy every NOT NULL column with a type-appropriate
// placeholder, then layer in the real ElanRegistry values that application
// code actually depends on (documented in database/3-configuration.sql).
// This also keeps the test DB privacy-safe — a synthetic row avoids ever
// copying real secrets (gsecret, fbsecret, spice_api, elan_admin_emails, etc.)
// from a live settings row into a test schema.

try {
    if (class_exists('DB')) {
        $db = DB::getInstance();

        $settingsCount = $db->query("SELECT COUNT(*) as cnt FROM settings WHERE id = 1")->first();

        if (!is_object($settingsCount) || !property_exists($settingsCount, 'cnt')) {
            abortBootstrap(
                "ERROR: Could not query the settings table: {$db->errorString()}",
                "Code that depends on settings (e.g. Car::__construct()) would silently misbehave. Aborting."
            );
        }

        if ((int) $settingsCount->cnt === 0) {
            fwrite(STDERR, "NOTE: settings row (id=1) is missing, seeding minimal test settings...\n");

            $columns = $db->query(
                "SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'"
            )->results();

            if (!$columns) {
                abortBootstrap(
                    "ERROR: Could not introspect the settings table's columns (query returned no rows).",
                    "The settings table may be missing entirely — check that scripts/create-test-schema.sh",
                    "was run and cloned the settings table structure. Aborting."
                );
            }

            $textTypes = ['varchar', 'char', 'text', 'tinytext', 'mediumtext', 'longtext'];
            $numericTypes = ['int', 'tinyint', 'smallint', 'mediumint', 'bigint'];

            // Real ElanRegistry values app code depends on (source: database/3-configuration.sql,
            // the canonical "UPDATE settings SET ..." for a fresh install). Includes the
            // security-relevant settings so tests exercise the same permission/session/rate-limit
            // posture as a real install, not a permissive all-zero fallback.
            $elanDefaults = [
                'site_name'              => 'Lotus Elan Registry',
                'template'               => 'customizer',
                'copyright'              => 'Lotus Elan Registry and UniBrain',
                'navigation_type'        => 0,
                'elan_image_dir'         => 'userimages/',
                'elan_image_max'         => 6,
                'permission_restriction' => 1,
                'session_manager'        => 1,
                'recaptcha'              => 0,
                'req_cap'                => 1,
                'req_num'                => 1,
                'email_login'            => 2,
                // Length-range settings validated by users/join.php, user_settings.php, and
                // forgot_password_reset.php — a 0/0 range would reject every username/password
                // ("must be at most 0 characters"), silently breaking any test that registers
                // or resets a user. Standard UserSpice defaults.
                'min_pw' => 5,
                'max_pw' => 32,
                'min_un' => 5,
                'max_un' => 20,
            ];

            $insertColumns = ['id'];
            $placeholders = ['?'];
            $bindings = [1];
            $unmatchedDefaults = array_keys($elanDefaults);

            foreach ($columns as $col) {
                $name = $col->COLUMN_NAME;
                if ($name === 'id') {
                    continue;
                }

                // Column names come from information_schema (a trusted system catalog scoped to
                // this exact table), never external input — but quote defensively regardless.
                $quotedName = '`' . str_replace('`', '``', $name) . '`';

                if (array_key_exists($name, $elanDefaults)) {
                    $insertColumns[] = $quotedName;
                    $placeholders[] = '?';
                    $bindings[] = $elanDefaults[$name];
                    $unmatchedDefaults = array_diff($unmatchedDefaults, [$name]);
                    continue;
                }

                // Columns that are nullable, or have their own DB default, don't need a value.
                if ($col->IS_NULLABLE === 'YES' || $col->COLUMN_DEFAULT !== null) {
                    continue;
                }

                // Only fill types where '' or 0 is unambiguously safe. A NOT NULL `datetime`,
                // `enum`, `decimal`, `json`, etc. could reject or misinterpret either placeholder
                // (e.g. 0 into a strict-mode `datetime` errors; the equivalent enum member may not
                // exist) — abort instead of guessing, consistent with this block failing loudly
                // rather than seeding a row that looks valid but silently misbehaves.
                if (in_array($col->DATA_TYPE, $textTypes, true)) {
                    $placeholderValue = '';
                } elseif (in_array($col->DATA_TYPE, $numericTypes, true)) {
                    $placeholderValue = 0;
                } else {
                    abortBootstrap(
                        "ERROR: settings column `{$name}` is NOT NULL with no default and an",
                        "unhandled type ({$col->DATA_TYPE}) — no safe placeholder value is known.",
                        "Add it to \$elanDefaults or \$textTypes/\$numericTypes above. Aborting."
                    );
                }

                $insertColumns[] = $quotedName;
                $placeholders[] = '?';
                $bindings[] = $placeholderValue;
            }

            // A key in $elanDefaults that never matched an actual settings column (renamed,
            // typo'd, or a future schema change) would otherwise be silently dropped — the
            // column falls through to a generic placeholder with no error, undercutting the
            // whole point of layering in real values. Fail loudly instead.
            if ($unmatchedDefaults) {
                abortBootstrap(
                    "ERROR: \$elanDefaults keys not found as settings columns: " . implode(', ', $unmatchedDefaults),
                    "Update \$elanDefaults in this file to match the current settings schema. Aborting."
                );
            }

            $seedSql = 'INSERT INTO `settings` (' . implode(', ', $insertColumns) . ') VALUES ('
                . implode(', ', $placeholders) . ')';

            $db->query($seedSql, $bindings);

            $verify = $db->query("SELECT COUNT(*) as cnt FROM settings WHERE id = 1")->first();
            if (!$verify || (int) $verify->cnt === 0) {
                abortBootstrap(
                    "ERROR: settings row INSERT ran but id=1 still does not exist.",
                    "Car::__construct() and other code silently skip loading data without this row. Aborting."
                );
            }

            fwrite(STDERR, "NOTE: Seeded settings row (id=1) for integration tests\n");
        } else {
            fwrite(STDERR, "NOTE: settings row (id=1) already present\n");
        }
    }
} catch (Throwable $e) {
    abortBootstrap(
        "ERROR: Failed to seed the settings row: {$e->getMessage()}",
        "Code that depends on settings (e.g. Car::__construct()) would silently misbehave. Aborting."
    );
}
