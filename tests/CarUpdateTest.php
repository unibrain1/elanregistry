<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test cases for car update functionality (editCar.php)
 * 
 * Tests cover CRUD operations, validation, security, and file uploads
 * for the car registry system.
 */
class CarUpdateTest extends TestCase
{
    private $testCarId;
    private $testUserId;
    private $originalPost;
    private $originalFiles;
    private $db;
    
    protected function setUp(): void
    {
        // Save original superglobals
        $this->originalPost = $_POST;
        $this->originalFiles = $_FILES;
        
        // Initialize database connection
        $this->db = DB::getInstance();
        
        // Create test user
        $this->testUserId = $this->createTestUser();
        
        // Create test car
        $this->testCarId = $this->createTestCar();
    }
    
    protected function tearDown(): void
    {
        // Clean up test data
        $this->cleanupTestData();
        
        // Restore original superglobals
        $_POST = $this->originalPost;
        $_FILES = $this->originalFiles;
    }
    
    /**
     * Test successful car creation
     */
    public function testCreateCarSuccess(): void
    {
        $_POST = [
            'action' => 'addCar',
            'csrf' => Token::generate(),
            'year' => '1973',
            'model' => 'S4|SE|FHC',
            'chassis' => '1234567890123',
            'color' => 'Red',
            'engine' => 'ABC123',
            'purchasedate' => '2020-01-01',
            'website' => 'https://example.com',
            'comments' => 'Test car',
            'filenames' => ''
        ];
        
        // Mock file upload (no files)
        $_FILES = [
            'file' => [
                'name' => ['blob'],
                'tmp_name' => [''],
                'error' => [UPLOAD_ERR_NO_FILE],
                'size' => [0]
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('addCar', $response['action']);
        $this->assertNotEmpty($response['cardetails']['id']);
        $this->assertEquals('1973', $response['cardetails']['year']);
        $this->assertEquals('S4', $response['cardetails']['series']);
        $this->assertEquals('SE', $response['cardetails']['variant']);
        $this->assertEquals('FHC', $response['cardetails']['type']);
    }
    
    /**
     * Test car creation with missing required fields
     */
    public function testCreateCarMissingRequiredFields(): void
    {
        $_POST = [
            'action' => 'addCar',
            'csrf' => Token::generate(),
            // Missing year, model, chassis
            'color' => 'Red',
            'filenames' => ''
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['blob'],
                'tmp_name' => [''],
                'error' => [UPLOAD_ERR_NO_FILE],
                'size' => [0]
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertContains('Please select Year', $response['info']);
        $this->assertContains('Please select Model', $response['info']);
        $this->assertContains('Please enter chassis number', $response['info']);
    }
    
    /**
     * Test successful car update
     */
    public function testUpdateCarSuccess(): void
    {
        $_POST = [
            'action' => 'updateCar',
            'csrf' => Token::generate(),
            'carid' => $this->testCarId,
            'year' => '1974',
            'model' => 'S4|SE|DHC',
            'chassis' => '1234567890124',
            'color' => 'Blue',
            'engine' => 'DEF456',
            'purchasedate' => '2021-01-01',
            'solddate' => '2023-01-01',
            'website' => 'https://updated.com',
            'comments' => 'Updated test car',
            'filenames' => ''
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['blob'],
                'tmp_name' => [''],
                'error' => [UPLOAD_ERR_NO_FILE],
                'size' => [0]
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('updateCar', $response['action']);
        $this->assertEquals($this->testCarId, $response['cardetails']['id']);
        $this->assertEquals('1974', $response['cardetails']['year']);
        $this->assertEquals('Blue', $response['cardetails']['color']);
        $this->assertEquals('DEF456', $response['cardetails']['engine']);
        $this->assertEquals('2021-01-01', $response['cardetails']['purchasedate']);
        $this->assertEquals('2023-01-01', $response['cardetails']['solddate']);
    }
    
    /**
     * Test chassis number validation for pre-1970 cars
     */
    public function testChassisValidationPre1970(): void
    {
        $_POST = [
            'action' => 'addCar',
            'csrf' => Token::generate(),
            'year' => '1969',
            'model' => 'S2|Standard|DHC',
            'chassis' => '12345', // Invalid - should be 4 digits for pre-1970
            'color' => 'Red',
            'filenames' => ''
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['blob'],
                'tmp_name' => [''],
                'error' => [UPLOAD_ERR_NO_FILE],
                'size' => [0]
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertContains('Enter Chassis Number. Four Digits', $response['info']);
    }
    
    /**
     * Test race car chassis validation exception
     */
    public function testRaceCarChassisValidation(): void
    {
        $_POST = [
            'action' => 'addCar',
            'csrf' => Token::generate(),
            'year' => '1969',
            'model' => 'S2|Race|DHC',
            'chassis' => '26R123456', // Should be allowed for race cars
            'color' => 'Red',
            'filenames' => ''
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['blob'],
                'tmp_name' => [''],
                'error' => [UPLOAD_ERR_NO_FILE],
                'size' => [0]
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('26R123456', $response['cardetails']['chassis']);
    }
    
    /**
     * Test CSRF token validation
     */
    public function testCSRFTokenValidation(): void
    {
        $_POST = [
            'action' => 'addCar',
            'csrf' => 'invalid_token',
            'year' => '1973',
            'model' => 'S4|SE|FHC',
            'chassis' => '1234567890123',
            'filenames' => ''
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        // Should include token error page
        $this->assertStringContainsString('token_error', $output);
    }
    
    /**
     * Test file upload security - valid image
     */
    public function testFileUploadValidImage(): void
    {
        $this->createTestImageFile();
        
        $_POST = [
            'action' => 'updateCar',
            'csrf' => Token::generate(),
            'carid' => $this->testCarId,
            'year' => '1973',
            'model' => 'S4|SE|FHC',
            'chassis' => '1234567890123',
            'filenames' => 'test.jpg'
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['test.jpg'],
                'tmp_name' => ['/tmp/test_upload.jpg'],
                'error' => [UPLOAD_ERR_OK],
                'size' => [1024],
                'type' => ['image/jpeg']
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertContains('Photo has been uploaded', $response['info']);
    }
    
    /**
     * Test file upload security - invalid file type
     */
    public function testFileUploadInvalidType(): void
    {
        $_POST = [
            'action' => 'updateCar',
            'csrf' => Token::generate(),
            'carid' => $this->testCarId,
            'year' => '1973',
            'model' => 'S4|SE|FHC',
            'chassis' => '1234567890123',
            'filenames' => 'malicious.php'
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['malicious.php'],
                'tmp_name' => ['/tmp/malicious.php'],
                'error' => [UPLOAD_ERR_OK],
                'size' => [1024],
                'type' => ['application/x-php']
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertContains('File upload rejected', $response['info']);
    }
    
    /**
     * Test file upload security - file too large
     */
    public function testFileUploadTooLarge(): void
    {
        $_POST = [
            'action' => 'updateCar',
            'csrf' => Token::generate(),
            'carid' => $this->testCarId,
            'year' => '1973',
            'model' => 'S4|SE|FHC',
            'chassis' => '1234567890123',
            'filenames' => 'large.jpg'
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['large.jpg'],
                'tmp_name' => ['/tmp/large.jpg'],
                'error' => [UPLOAD_ERR_OK],
                'size' => [10485760], // 10MB - exceeds 5MB limit
                'type' => ['image/jpeg']
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertContains('File too large', $response['info']);
    }
    
    /**
     * Test fetchImages action
     */
    public function testFetchImages(): void
    {
        $_POST = [
            'action' => 'fetchImages',
            'csrf' => Token::generate(),
            'carID' => $this->testCarId
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('images', $response);
        $this->assertIsArray($response['images']);
    }
    
    /**
     * Test removeImages action
     */
    public function testRemoveImage(): void
    {
        // First add an image to the test car
        $this->addTestImageToCar();
        
        $_POST = [
            'action' => 'removeImages',
            'csrf' => Token::generate(),
            'carID' => $this->testCarId,
            'file' => 'test_image.jpg'
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertArrayHasKey('count', $response);
        $this->assertArrayHasKey('images', $response);
    }
    
    /**
     * Test date validation and formatting
     */
    public function testDateValidation(): void
    {
        $_POST = [
            'action' => 'updateCar',
            'csrf' => Token::generate(),
            'carid' => $this->testCarId,
            'year' => '1973',
            'model' => 'S4|SE|FHC',
            'chassis' => '1234567890123',
            'purchasedate' => '01/15/2020', // Should be converted to Y-m-d format
            'solddate' => '2023-12-31',
            'filenames' => ''
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['blob'],
                'tmp_name' => [''],
                'error' => [UPLOAD_ERR_NO_FILE],
                'size' => [0]
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('2020-01-15', $response['cardetails']['purchasedate']);
        $this->assertEquals('2023-12-31', $response['cardetails']['solddate']);
    }
    
    /**
     * Test engine number formatting
     */
    public function testEngineNumberFormatting(): void
    {
        $_POST = [
            'action' => 'updateCar',
            'csrf' => Token::generate(),
            'carid' => $this->testCarId,
            'year' => '1973',
            'model' => 'S4|SE|FHC',
            'chassis' => '1234567890123',
            'engine' => ' abc 123 ', // Should be trimmed and uppercased, spaces removed
            'filenames' => ''
        ];
        
        $_FILES = [
            'file' => [
                'name' => ['blob'],
                'tmp_name' => [''],
                'error' => [UPLOAD_ERR_NO_FILE],
                'size' => [0]
            ]
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('success', $response['status']);
        $this->assertEquals('ABC123', $response['cardetails']['engine']);
    }
    
    /**
     * Test invalid action handling
     */
    public function testInvalidAction(): void
    {
        $_POST = [
            'action' => 'invalidAction',
            'csrf' => Token::generate()
        ];
        
        ob_start();
        include __DIR__ . '/../app/action/editCar.php';
        $output = ob_get_clean();
        
        $response = json_decode($output, true);
        
        $this->assertEquals('error', $response['status']);
        $this->assertContains('No valid action', $response['info']);
    }
    
    // Helper methods
    
    private function createTestUser(): int
    {
        // Create a test user and return user ID
        // This would integrate with your user creation system
        return 1; // Placeholder
    }
    
    private function createTestCar(): int
    {
        $car = new Car();
        $carData = [
            'user_id' => $this->testUserId,
            'year' => '1973',
            'series' => 'S4',
            'variant' => 'SE',
            'type' => 'FHC',
            'chassis' => 'TEST123456',
            'color' => 'Red',
            'email' => 'test@example.com',
            'fname' => 'Test',
            'lname' => 'User',
            'city' => 'Test City',
            'state' => 'Test State',
            'country' => 'Test Country'
        ];
        
        $car->create($carData);
        return $car->data()->id;
    }
    
    private function createTestImageFile(): void
    {
        // Create a minimal valid JPEG file for testing
        $imageData = base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/wAAAAAAA');
        file_put_contents('/tmp/test_upload.jpg', $imageData);
    }
    
    private function addTestImageToCar(): void
    {
        // Add a test image to the car for removal testing
        $this->db->update('cars', $this->testCarId, ['image' => 'test_image.jpg']);
    }
    
    private function cleanupTestData(): void
    {
        // Clean up test car
        if ($this->testCarId) {
            $this->db->delete('cars', ['id' => $this->testCarId]);
        }
        
        // Clean up test files
        if (file_exists('/tmp/test_upload.jpg')) {
            unlink('/tmp/test_upload.jpg');
        }
    }
}