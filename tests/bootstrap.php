<?php
/**
 * PHPUnit Bootstrap File for Elan Registry Tests
 * 
 * Sets up the testing environment with UserSpice framework
 * and mocks for comprehensive testing.
 */

// Set up testing environment
define('TESTING', true);

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

// Mock classes for testing if they don't exist
if (!class_exists('Token')) {
    class Token {
        public static function generate() {
            return 'test_csrf_token_' . uniqid();
        }
        
        public static function check($token) {
            return strpos($token, 'test_csrf_token_') === 0;
        }
    }
}

// Ensure required functions are available for file upload tests
if (!function_exists('generateSecureFilename')) {
    /**
     * Generate a cryptographically secure filename
     */
    function generateSecureFilename($extension) {
        $randomBytes = random_bytes(16);
        return 'img_' . bin2hex($randomBytes) . '.' . $extension;
    }
}

if (!function_exists('getMimeType')) {
    /**
     * Get and validate MIME type of uploaded file
     */
    function getMimeType($filepath) {
        if (!file_exists($filepath)) {
            throw new Exception('File does not exist');
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        
        $allowedMimes = [
            'image/jpeg',
            'image/png', 
            'image/gif',
            'image/webp'
        ];
        
        if (!in_array($mimeType, $allowedMimes)) {
            throw new Exception('Invalid file type detected: ' . $mimeType);
        }
        
        return $mimeType;
    }
}

if (!function_exists('getExtension')) {
    /**
     * Get file extension based on MIME type
     */
    function getExtension($mimeType) {
        $extensionMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp'
        ];
        
        if (!isset($extensionMap[$mimeType])) {
            throw new Exception('Unsupported file type: ' . $mimeType);
        }
        
        return $extensionMap[$mimeType];
    }
}

if (!function_exists('validateFileUpload')) {
    /**
     * Validate file upload security
     */
    function validateFileUpload($file) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Check file size limits
        $maxSize = 5 * 1024 * 1024; // 5MB
        $minSize = 100; // 100 bytes
        
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large. Maximum size is ' . ($maxSize / 1024 / 1024) . 'MB');
        }
        
        if ($file['size'] < $minSize) {
            throw new Exception('File too small. Minimum size is ' . $minSize . ' bytes');
        }
        
        // Validate that uploaded file exists
        if (!is_uploaded_file($file['tmp_name']) && !file_exists($file['tmp_name'])) {
            throw new Exception('Invalid file upload');
        }
        
        return true;
    }
}

// Debug: Check if Car class exists
error_log("Bootstrap: Checking if Car class exists: " . (class_exists('Car') ? 'YES' : 'NO'));

// Mock Car class if not loaded from UserSpice
if (!class_exists('Car')) {
    error_log("Bootstrap: Creating mock Car class");
    class Car {
        private $data;
        private static $nextId = 1000;
        
        public function __construct() {
            $this->data = (object) [
                'id' => self::$nextId++,
                'user_id' => 1,
                'year' => '1973',
                'series' => 'S4',
                'variant' => 'SE',
                'type' => 'FHC',
                'chassis' => 'TEST123456',
                'color' => 'Red',
                'engine' => 'ABC123',
                'email' => 'test@example.com',
                'fname' => 'Test',
                'lname' => 'User',
                'city' => 'Test City',
                'state' => 'Test State',
                'country' => 'Test Country'
            ];
        }
        
        public static function find($id) {
            $car = new self();
            $car->data->id = $id;
            return $car;
        }
        
        public function data() {
            return $this->data;
        }
        
        public function create($data) {
            foreach ($data as $key => $value) {
                $this->data->$key = $value;
            }
            return true;
        }
        
        public function update($data) {
            foreach ($data as $key => $value) {
                $this->data->$key = $value;
            }
            return true;
        }
    }
}

// Set up test database if needed
try {
    if (class_exists('DB') && method_exists('DB', 'getInstance')) {
        $db = DB::getInstance();
        // Verify database connection is working
        $db->query("SELECT 1");
    }
} catch (Exception $e) {
    // Mock DB class if database is not available
    if (!class_exists('DB')) {
        class DB {
            private static $instance = null;
            
            public static function getInstance() {
                if (self::$instance === null) {
                    self::$instance = new self();
                }
                return self::$instance;
            }
            
            public function query($sql, $params = []) {
                return new MockQueryResult();
            }
            
            public function insert($table, $fields) {
                return rand(1, 1000); // Mock insert ID
            }
            
            public function update($table, $id, $fields) {
                return true;
            }
            
            public function delete($table, $where) {
                return true;
            }
            
            public function findById($id, $table) {
                return new MockQueryResult();
            }
        }
        
        class MockQueryResult {
            public function results() {
                return [(object) [
                    'id' => 1,
                    'fname' => 'Test', 
                    'lname' => 'User',
                    'email' => 'test@example.com'
                ]];
            }
            
            public function first() {
                return (object) [
                    'id' => 1,
                    'fname' => 'Test',
                    'lname' => 'User', 
                    'email' => 'test@example.com'
                ];
            }
            
            public function count() {
                return 1;
            }
        }
    }
}

// Mock user object and authentication system
if (!isset($user) || !is_object($user)) {
    class MockUser {
        private $userData;
        
        public function __construct() {
            $this->userData = (object) [
                'id' => 1,
                'username' => 'testuser',
                'email' => 'test@example.com',
                'fname' => 'Test',
                'lname' => 'User'
            ];
        }
        
        public function data() {
            return $this->userData;
        }
        
        public function isLoggedIn() {
            return true;
        }
    }
    
    $user = new MockUser();
    $GLOBALS['user'] = $user;
}

// Mock securePage function
if (!function_exists('securePage')) {
    function securePage($page) {
        return true; // Always allow access in tests
    }
}

// Mock Input class if not available
if (!class_exists('Input')) {
    class Input {
        public static function get($key, $default = null) {
            return $_POST[$key] ?? $_GET[$key] ?? $default;
        }
        
        public static function exists($method = 'post') {
            return $method === 'post' ? !empty($_POST) : !empty($_GET);
        }
    }
}