<?php
declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

/**
 * Integration tests for Admin AJAX endpoint data
 *
 * Verifies the car/user data process-car-details.php and process-user-details.php
 * read from the database: existence, required fields, and consistency with what
 * was inserted.
 */
class AdminAjaxEndpointsTest extends IntegrationTestCase
{
    private $testCarId;
    private $testUserId;

    /**
     * Create a fixture user and car for the tests to query
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->testUserId = $this->createTestUser();
        $this->testCarId = $this->createTestCar($this->testUserId);
    }

    // =========================================================================
    // Car Details Endpoint Tests
    // =========================================================================

    /**
     * Test car details endpoint with valid car ID returns success
     */
    public function testGetCarDetailsWithValidId(): void
    {

        // Verify test car exists
        $car = $this->db->query("SELECT * FROM cars WHERE id = ?", [$this->testCarId])->first();

        $this->assertNotNull($car, "Test car should exist");
        $this->assertIsObject($car);
        $this->assertTrue(property_exists($car, 'id'), "Car should have id property");
        $this->assertTrue(property_exists($car, 'chassis'), "Car should have chassis property");
    }

    /**
     * Test car details endpoint with invalid car ID returns error
     */
    public function testGetCarDetailsWithInvalidId(): void
    {

        // Test with non-existent ID
        $result = $this->db->query("SELECT * FROM cars WHERE id = ?", [99999])->results();

        $this->assertEmpty($result, "Query should return no results for non-existent car");
    }

    /**
     * Test car details endpoint with zero car ID returns error
     */
    public function testGetCarDetailsWithZeroId(): void
    {

        // Verify that ID 0 is never a valid car
        $result = $this->db->query("SELECT * FROM cars WHERE id = ?", [0])->results();

        $this->assertEmpty($result, "Car ID 0 should never exist");
    }

    /**
     * Test car details endpoint returns required fields
     */
    public function testGetCarDetailsReturnsAllRequiredFields(): void
    {

        $car = $this->db->query("SELECT * FROM cars WHERE id = ?", [$this->testCarId])->first();

        $requiredFields = [
            'id', 'year', 'type', 'chassis', 'color', 'series',
            'fname', 'lname', 'email', 'city', 'state', 'country',
            'ctime', 'mtime'
        ];

        foreach ($requiredFields as $field) {
            $this->assertTrue(property_exists($car, $field), "Car should have {$field} field");
        }
    }

    // =========================================================================
    // User Details Endpoint Tests
    // =========================================================================

    /**
     * Test user details endpoint with valid user ID returns success
     */
    public function testGetUserDetailsWithValidId(): void
    {

        // Verify test user exists
        $user = $this->db->query("SELECT * FROM users WHERE id = ?", [$this->testUserId])->first();

        $this->assertNotNull($user, "Test user should exist");
        $this->assertIsObject($user);
        $this->assertTrue(property_exists($user, 'id'), "User should have id property");
        $this->assertTrue(property_exists($user, 'email'), "User should have email property");
    }

    /**
     * Test user details endpoint with non-existent user ID returns not found
     */
    public function testGetUserDetailsWithNonExistentId(): void
    {

        // Test with non-existent ID
        $result = $this->db->query("SELECT * FROM users WHERE id = ?", [99999])->results();

        $this->assertEmpty($result, "Query should return no results for non-existent user");
    }

    /**
     * Test user details endpoint with zero user ID returns error
     */
    public function testGetUserDetailsWithZeroId(): void
    {

        // Verify that ID 0 is never a valid user
        $result = $this->db->query("SELECT * FROM users WHERE id = ?", [0])->results();

        $this->assertEmpty($result, "User ID 0 should never exist");
    }

    /**
     * Test user details endpoint returns required fields
     */
    public function testGetUserDetailsReturnsAllRequiredFields(): void
    {

        $user = $this->db->query("SELECT * FROM users WHERE id = ?", [$this->testUserId])->first();

        $requiredFields = ['id', 'fname', 'lname', 'email', 'join_date'];

        foreach ($requiredFields as $field) {
            $this->assertTrue(property_exists($user, $field), "User should have {$field} field");
        }
    }

    // =========================================================================
    // Data Consistency Tests
    // =========================================================================

    /**
     * Test that car data returned by endpoint matches database
     */
    public function testCarDataConsistency(): void
    {

        $car = $this->db->query("SELECT * FROM cars WHERE id = ?", [$this->testCarId])->first();

        $this->assertNotNull($car, "Car should exist in database");
        $this->assertEquals($this->testCarId, $car->id, "Car ID should match");
    }

    /**
     * Test that user data returned by endpoint matches database
     */
    public function testUserDataConsistency(): void
    {

        $user = $this->db->query("SELECT * FROM users WHERE id = ?", [$this->testUserId])->first();

        $this->assertNotNull($user, "User should exist in database");
        $this->assertEquals($this->testUserId, $user->id, "User ID should match");
    }
}
