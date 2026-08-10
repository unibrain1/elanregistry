<?php

declare(strict_types=1);

use ElanRegistry\LogCategories;

/**
 * PHPUnit Bootstrap File for Unit Tests
 *
 * Sets up the testing environment with MOCKS ONLY.
 * No UserSpice framework or database.
 * Use this for: tests/unit/* and tests/regression/*
 *
 * For integration tests, use: tests/bootstrap-integration.php
 */

// Set up testing environment - MOCKS ONLY
define('TESTING', true);
define('TESTING_UNIT_ONLY', true);
define('UNIT_TEST_SUITE', true);

// Backup retention constants (normally from usersc/includes/config.php)
if (!defined('BACKUP_RETENTION_AUTOMATED')) {
    define('BACKUP_RETENTION_AUTOMATED', 7);
    define('BACKUP_RETENTION_MANUAL', 30);
    define('BACKUP_RETENTION_ROLLBACK', 30);
    define('BACKUP_WARNING_THRESHOLD_DAYS', 7);
    define('BACKUP_FAILURE_LOOKBACK_DAYS', 7);
}

// Prevent any integration test code from loading
if (defined('INTEGRATION_TEST_SUITE')) {
    die("ERROR: bootstrap-unit.php cannot be used with INTEGRATION_TEST_SUITE defined");
}

// Set up basic paths
$projectRoot = dirname(__DIR__);
$_SERVER['DOCUMENT_ROOT'] = $projectRoot;
$_SERVER['PHP_SELF'] = '/tests/';

// Skip UserSpice initialization for now - use mocks instead
// The real framework requires database connection which isn't needed for unit tests

// Mock session for testing
if (!isset($_SESSION)) {
    $_SESSION = [];
}

// These mocks are plain global classes (Token, Input, DB, QueryResult) plus the
// namespaced CarModel below. Composer autoloads only namespaced ElanRegistry\*
// classes, so it can never resolve these bare names — defining them here simply
// provides the only declaration the unit suite ever sees. Order relative to the
// vendor/autoload.php require below is immaterial.
//
// CarModel is different: it IS namespaced and Composer-resolvable
// (ElanRegistry\Reference\CarModel, PSR-4 mapped to usersc/classes/Reference/), so its
// eval'd shadow below must be declared before anything first touches the class — see
// the note at the eval.
//
// Why Token and Input are mocked rather than loaded for real: NOTHING under
// users/classes/ can ever be require_once'd from this bootstrap, because the whole
// users/ tree is .gitignore'd (`users/**`) — it is a manually installed upstream
// UserSpice checkout, absent from every CI checkout and from `composer install`.
// Requiring users/classes/Token.php works on a developer machine and fatals in CI.
// This constraint is about file availability, not runtime dependencies: Token and
// Input touch nothing but superglobals and would otherwise be perfectly loadable.
//
// DB and CarModel are mocked for the separate, additional reason that their real
// implementations require a live database connection, which unit tests deliberately
// do not have.
//
// Consequence: these mocks are stubs, not production behavior. Real CSRF crypto
// (hash_equals over the session token) and real htmlspecialchars() input sanitization
// are verified in tests/integration/TokenAndInputSecurityTest.php, the only tier where
// users/init.php actually loads the upstream classes. Unit tests here may only assert
// the stub's own contract.
if (!class_exists('Token')) {
    class Token {
        public static function generate(): string {
            return 'test_csrf_token_' . uniqid();
        }

        public static function check(mixed $token): bool {
            if ($token === null || $token === '') {
                return false;
            }
            return strpos($token, 'test_csrf_token_') === 0;
        }
    }
}

if (!class_exists('Input')) {
    /**
     * Stub of the upstream UserSpice Input class.
     *
     * Deliberately a raw passthrough: it does NOT apply the real class's
     * htmlspecialchars() encoding, so no unit test may assert sanitized output.
     */
    class Input {
        public static function get($key, $default = null): mixed {
            return $_POST[$key] ?? $_GET[$key] ?? $default;
        }

        public static function exists($method = 'post'): bool {
            return $method === 'post' ? !empty($_POST) : !empty($_GET);
        }
    }
}

// Define exception classes for testing
// Exception classes and LogCategories are now real classes loaded via autoloader
// No longer using mock implementations - allows tests to verify actual exception behavior

// Mock logger function — tracks calls in $mockLogEntries for test assertions
// Signature must match the real logger() (users/helpers/us_helpers.php) — the
// optional $metadata param is required here too. The real signature is invisible
// to PHPStan (users/ is excluded from its scan), so this stub is the only
// declaration it can resolve logger() calls against once tests/ is in its scan
// path — including calls in production files like usersc/join.php.
if (!function_exists('logger')) {
    function logger($userId, $category, $message, $metadata = null): void {
        global $mockLogEntries;
        if (!isset($mockLogEntries)) {
            $mockLogEntries = [];
        }
        $mockLogEntries[] = [
            'user_id' => $userId,
            'category' => $category,
            'message' => $message,
        ];
    }
}

// Mock email function — tracks sent emails in $mockSentEmails for test assertions.
// Return value can be overridden via $GLOBALS['mockEmailSendResult'] to simulate
// send failures (defaults to true).
if (!function_exists('email')) {
    function email($to, $subject, $body, $opts = [], $attachment = null): bool {
        global $mockSentEmails, $mockEmailSendResult;
        if (!isset($mockSentEmails)) {
            $mockSentEmails = [];
        }
        $mockSentEmails[] = [$to, $subject, $body];

        return $mockEmailSendResult ?? true;
    }
}

// Mock email_body function — returns a canned rendered body by default.
// Override via $GLOBALS['mockEmailBodyResult'] (e.g. '') to exercise the
// empty-body failure branch of callers.
if (!function_exists('email_body')) {
    function email_body($template, $options = [], $security_override = false): string {
        global $mockEmailBodyResult;
        return $mockEmailBodyResult ?? '<html>mock email body</html>';
    }
}

// Mock randomstring/hashVericode — the real implementations live in
// users/helpers/us_helpers.php (randomString(), case-insensitively callable as
// randomstring()) which is not loaded in the unit-test environment, and
// hashVericode() depends on getVericodeSecret()'s file-based secret key. Both
// are pure enough to stand in for here: a fixed-length test string and a
// deterministic (non-identity) transform so tests can assert hashing occurred.
if (!function_exists('randomstring')) {
    function randomstring(int $len): string {
        return str_repeat('x', $len);
    }
}

if (!function_exists('hashVericode')) {
    function hashVericode(string $vericode): string {
        return 'hashed_' . $vericode;
    }
}

// Mock DB class
if (!class_exists('DB')) {
    /**
     * Mock DB class for unit tests (simple variant)
     */
    class DB {
        /** @var self|null */
        private static $instance;

        /**
         * Get singleton instance
         *
         * @return self
         */
        public static function getInstance(): self {
            if (self::$instance === null) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Execute a query
         *
         * @param string $sql SQL query
         * @param array<mixed> $params Query parameters
         * @return QueryResult
         */
        public function query(string $sql, array $params = []): QueryResult {
            // Owner::find() runs a users LEFT JOIN profiles WHERE u.id = ? query.
            // Return a standard mock user row so tests that need a valid owner work.
            // The WHERE-clause guard prevents matching Owner::searchOwners() and other
            // queries that join users+profiles but use different WHERE conditions.
            //
            // This branch must stay even though #1441 moved most DB-behavior control to
            // per-test createMock(DB::class) doubles: Owner DOES accept DB via
            // constructor injection (?object $db = null), but
            // CarAdministrationService::transfer():96 calls `new Owner($newUserId)`
            // without passing one, so Owner falls back to this shared
            // DB::getInstance() singleton — independent of whatever mock DB a test
            // injects directly into CarRepository. Removing this branch would break
            // CarAdministrationServiceTest's transfer tests; fixing it properly means
            // giving transfer() a seam to pass its own DB through to Owner, a
            // production-code change out of scope for a test-only issue.
            if (stripos($sql, 'profiles') !== false
                && stripos($sql, 'users') !== false
                && stripos($sql, 'WHERE u.id') !== false) {
                $userId = $params[0] ?? 1;
                return new QueryResult([(object) [
                    'id'         => (string) $userId,
                    'email'      => 'test@example.com',
                    'fname'      => 'Test',
                    'lname'      => 'User',
                    'join_date'  => '2024-01-01 00:00:00',
                    'last_login' => '2024-01-01 00:00:00',
                    'city'       => 'Test City',
                    'state'      => 'TS',
                    'country'    => 'US',
                    'lat'        => null,
                    'lon'        => null,
                    'website'    => '',
                ]]);
            }
            return new QueryResult([]);
        }

        /**
         * Get a record by column/value
         *
         * @param string $table Table name
         * @param array<mixed> $where Where conditions
         * @return QueryResult
         */
        public function get(string $table, array $where): QueryResult {
            $id = $where[2] ?? 1;
            return new QueryResult([(object) [
                'id' => (string) $id,
                'user_id' => '1',
                'year' => '1973',
                'model' => 'Elan S4',
                'series' => 'S4',
                'variant' => 'SE',
                'type' => 'FHC',
                'chassis' => 'TEST123456',
                'color' => 'Red',
                'engine' => 'ABC123',
                'image' => null,
                'email' => 'test@example.com',
                'fname' => 'Test',
                'lname' => 'User',
                'join_date' => '2024-01-01',
                'city' => 'Test City',
                'state' => 'TS',
                'country' => 'US',
                'lat' => '0.0',
                'lon' => '0.0',
                'vericode' => null,
                'last_verified' => null,
                'solddate' => null,
                'purchasedate' => null,
                'ctime' => date('Y-m-d H:i:s'),
                'mtime' => date('Y-m-d H:i:s'),
                'website' => '',
                'comments' => ''
            ]]);
        }

        /**
         * Find all records in a table
         *
         * @param string $table Table name
         * @return QueryResult
         */
        public function findAll(string $table): QueryResult {
            return new QueryResult([]);
        }

        /**
         * Insert a record
         *
         * @param string $table Table name
         * @param array<string, mixed> $data Field values
         * @return bool
         */
        public function insert(string $table, array $data): bool {
            return true;
        }

        /**
         * Update a record.
         *
         * @param string $table Table name
         * @param int $id Record ID
         * @param array<string, mixed> $data Field values
         * @return bool
         */
        public function update(string $table, int $id, array $data): bool {
            return true;
        }

        /**
         * Check for database errors
         *
         * @return bool
         */
        public function error(): bool {
            return false;
        }

        /**
         * Get error string
         *
         * @return string
         */
        public function errorString(): string {
            return '';
        }

        /**
         * Get last insert ID
         *
         * @return int
         */
        public function lastId(): int {
            return 1;
        }

        public function count(): int {
            return 0;
        }

        /** Returns the first row from the last query result, or null when empty. */
        public function first(): mixed {
            return null;
        }

        /** @return array<mixed> */
        public function results(): array {
            return [];
        }

        public function deleteById(string $table, int $id): bool {
            return true;
        }

        public function beginTransaction(): void {}
        public function commit(): void {}
        public function rollBack(): void {}
        public function inTransaction(): bool { return false; }
    }
}

if (!class_exists('QueryResult')) {
    /**
     * Mock query result class for unit tests (simple variant)
     */
    class QueryResult {
        /** @var array<mixed> */
        private array $results;

        /**
         * @param array<mixed> $results Mock data
         */
        public function __construct(array $results) {
            $this->results = $results;
        }

        /**
         * Get result count
         *
         * @return int
         */
        public function count(): int {
            return count($this->results);
        }

        /**
         * Get first result
         *
         * @return object|false
         */
        public function first(): object|false {
            return reset($this->results);
        }

        /**
         * Get all results
         *
         * @return array<mixed>
         */
        public function results(): array {
            return $this->results;
        }
    }
}

// Load type helper functions (dbInt, currentUserId)
// Defined here directly since custom_functions.php requires server_globals.php
// which depends on the Server class and full framework initialization
if (!function_exists('dbInt')) {
    function dbInt(mixed $value, string $property = 'id'): int
    {
        if (is_object($value)) {
            if (!isset($value->$property)) {
                throw new InvalidArgumentException("Property '$property' does not exist on object");
            }
            $value = $value->$property;
        }
        if ($value === null || $value === '') {
            throw new InvalidArgumentException("Cannot convert empty value to int (property: $property)");
        }
        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Cannot convert non-numeric value to int (property: $property): $value");
        }
        return (int) $value;
    }
}

if (!function_exists('currentUserId')) {
    function currentUserId(): int
    {
        global $user;
        if (!isset($user) || !$user->isLoggedIn()) {
            throw new RuntimeException('No user is currently logged in');
        }
        return (int) $user->data()->id;
    }
}

// ============================================================
// Mock CarModel Reference Data Class
// ============================================================
// CRITICAL: Must be defined BEFORE autoloader to prevent loading real CarModel.
// Unlike the bare classes above, ElanRegistry\Reference\CarModel is PSR-4 mapped in
// composer.json (usersc/classes/Reference/CarModel.php), so Composer WOULD resolve it —
// this declaration only wins if it lands first.
// Provides test data for valid model combinations without requiring database

// Use eval to create the class in the correct namespace
// This is a special case for unit testing - allows us to mock a namespaced class
eval('
namespace ElanRegistry\Reference;

/**
 * Mock CarModel class for unit tests
 * Returns valid test data for known model combinations
 */
class CarModel {
    /**
     * Valid model combinations for testing
     * @var array<string, bool>
     */
    private static array $validModels = [
        "S4|FHC|36" => true,
        "S4|DHC|45" => true,
        "Sprint|FHC|36" => true,
        "Sprint|DHC|45" => true,
        "S3|FHC|36" => true,
        "S3|DHC|45" => true,
        "+2|FHC|50" => true,
        "+2S|FHC|50" => true,
        "+2S/130|FHC|50" => true,
    ];

    /**
     * Check if a model combination exists
     */
    public function exists(string $series, string $variant, string $typeCode): bool {
        $series = trim($series);
        $variant = trim($variant);
        $typeCode = trim($typeCode);

        $modelValue = "{$series}|{$variant}|{$typeCode}";
        return isset(self::$validModels[$modelValue]);
    }

    /**
     * Get model by composite value
     */
    public function byValue(string $value): ?object {
        if (isset(self::$validModels[$value])) {
            $parts = explode("|", $value);
            return (object)[
                "model_value" => $value,
                "series_normalized" => $parts[0],
                "variant" => $parts[1],
                "type_code" => $parts[2],
            ];
        }
        return null;
    }
}
');

// Load Composer autoloader for all custom classes and exceptions
require_once $projectRoot . '/vendor/autoload.php';

// Mock securePage function - only for unit tests
if (!function_exists('securePage')) {
    function securePage($page): bool {
        return true; // Always allow access in tests
    }
}

// Mock getSettings function - only for unit tests
if (!function_exists('getSettings')) {
    /**
     * Mock getSettings function for testing
     */
    function getSettings($id = 1): object {
        // Return mock settings object
        return (object) [
            'id' => (string) $id,
            'elan_image_dir' => '/userimages/'
        ];
    }
}

// Mock getBaseUrl function - needed by EmailTemplate
if (!function_exists('getBaseUrl')) {
    /**
     * Mock getBaseUrl function for testing
     * Returns a test base URL
     */
    function getBaseUrl(): string
    {
        return 'https://test.elanregistry.org';
    }
}

// Mock getFeedbackEmail function - needed by verify-new-email template
if (!function_exists('getFeedbackEmail')) {
    /**
     * Mock getFeedbackEmail function for testing
     * Returns 'registrar@elanregistry.org' — mirrors the production fallback in
     * custom_functions.php so that render assertions reflect real-world behavior.
     */
    function getFeedbackEmail(): string
    {
        return 'registrar@elanregistry.org';
    }
}

// Mock getAdminEmails function - needed by transfer email templates
if (!function_exists('getAdminEmails')) {
    /**
     * Mock getAdminEmails function for testing.
     * Set $GLOBALS['mockAdminEmails'] to override the default in a test.
     */
    function getAdminEmails(): string
    {
        global $mockAdminEmails;
        if (isset($mockAdminEmails)) {
            return $mockAdminEmails;
        }
        return 'admin@elanregistry.org';
    }
}

// Mock isRegistryAdmin function - needed by admin permission checks
if (!function_exists('isRegistryAdmin')) {
    /**
     * Mock isRegistryAdmin function for testing
     * Uses global $mockIsRegistryAdmin to control behavior
     *
     * Signature must match the real isRegistryAdmin() (usersc/includes/custom_functions.php)
     * — the optional, nullable $userId is required here too, otherwise PHPStan resolves
     * every isRegistryAdmin() call project-wide against this narrower stub once tests/ is
     * in its scan path.
     *
     * The no-argument path delegates to currentUserId(), which in the unit tier always
     * THROWS RuntimeException('No user is currently logged in'): since MockUser was
     * removed in #1554 nothing here sets an ambient $user, deliberately. A caller that
     * needs a bool must therefore either pass an explicit $userId or set the
     * $mockIsRegistryAdmin global. Throwing loudly is the intent — silently resolving a
     * missing user to "not admin" would be a quiet wrong answer.
     * dbInt() normalizes the string-ID case too (both real call sites pass $user->data()->id,
     * which is a string) — a bare `=== 1` would silently return false for '1' under strict
     * comparison, the same quiet-wrong-answer trap the null case was fixed to avoid.
     */
    function isRegistryAdmin(int|string|null $userId = null): bool
    {
        global $mockIsRegistryAdmin;

        // If explicitly set in test, use that value
        if (isset($mockIsRegistryAdmin)) {
            return (bool) $mockIsRegistryAdmin;
        }

        // Default: user ID 1 is admin
        return dbInt($userId ?? currentUserId()) === 1;
    }
}

// RegressionTestCase is not in the PSR-4 autoloaded path, so it must be
// explicitly required before PHPUnit loads test classes that extend it.
require_once __DIR__ . '/regression/RegressionTestCase.php';
