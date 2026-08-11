<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\Car\Car;
use ElanRegistry\Owner;
use ElanRegistry\Exceptions\CarNotFoundException;
use ElanRegistry\Exceptions\CarValidationException;

use PHPUnit\Framework\Attributes\Group;

/**
 * Test cases for Car transfer functionality
 *
 * Tests cover car ownership transfer operations with user validation,
 * transaction handling, relationship updates, and profile data copying.
 */
#[Group('integration')]
final class CarTransferTest extends IntegrationTestCase
{
    private $testCarId;
    private $testUserId;
    private $targetUserId;
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->testUserId = $this->createTestUser();
        $this->targetUserId = $this->createTestUser();

        // Set up authenticated user context for transfer operations
        global $user;
        $user = new User();
        $user->find($this->testUserId);

        // Bypass login() to set the private $_isLoggedIn flag directly via reflection.
        // setAccessible() is intentionally omitted — it is a no-op since PHP 8.1.
        $reflection = new ReflectionClass($user);
        $isLoggedInProperty = $reflection->getProperty('_isLoggedIn');
        $isLoggedInProperty->setValue($user, true);

        $GLOBALS['user'] = $user;

        $this->db = DB::getInstance();

        // city/state/country/lat/lon live on `profiles`, not `users` — createTestUser() only
        // writes `users`. Give the transfer target real location data so
        // testTransferUpdatesLocationData verifies an actual copy instead of comparing '' === ''.
        // If this insert silently failed, the test would go right back to comparing '' === ''
        // with no signal — so check it explicitly rather than let a failure hide.
        $profileInserted = $this->db->insert('profiles', [
            'user_id' => $this->targetUserId,
            'city'    => 'Hethel',
            'state'   => 'Norfolk',
            'country' => 'United Kingdom',
        ]);
        $this->assertTrue((bool) $profileInserted, 'Test fixture: profiles insert must succeed');

        // Create unique test car for this test
        try {
            $this->testCarId = $this->createTestCar($this->testUserId, [
                'chassis' => 'TR' . uniqid()
            ]);
        } catch (RuntimeException $e) {
            $this->markTestSkipped('Could not create test car: ' . $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->databaseConnected && $this->targetUserId) {
            $this->db->query("DELETE FROM profiles WHERE user_id = ?", [$this->targetUserId]);
        }
        parent::tearDown();
    }

    /**
     * Test successful car transfer to valid user
     */
    #[Group('fast')]
    public function testTransferCarSuccessWithValidUser(): void
    {
        $car = new Car($this->testCarId);

        $car->transfer($this->targetUserId, 'Test transfer', 'NEWOWNER', $this->testUserId);

        // Reload car data from database to verify transfer
        $transferredCar = new Car($this->testCarId);
        $this->assertEquals($this->targetUserId, $transferredCar->data()->user_id);
    }

    /**
     * Test car transfer fails with invalid user ID
     */
    #[Group('fast')]
    public function testTransferCarFailsWithInvalidUser(): void
    {
        $this->expectException(CarValidationException::class);

        $car = new Car($this->testCarId);
        $car->transfer(99999, 'Test transfer', 'NEWOWNER', $this->testUserId);
    }

    /**
     * Test car transfer fails when car does not exist
     */
    #[Group('fast')]
    public function testTransferCarFailsWhenCarNotExists(): void
    {
        $this->expectException(CarNotFoundException::class);

        $car = new Car(99999);
        $car->transfer($this->targetUserId, 'Test transfer', 'NEWOWNER', $this->testUserId);
    }

    /**
     * Test car transfer updates cars.user_id
     */
    #[Group('fast')]
    public function testTransferUpdatesCarsUserId(): void
    {
        $car = new Car($this->testCarId);
        $carId = $car->data()->id;

        // Verify original owner
        $before = $this->db->query(
            "SELECT user_id FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertSame($this->testUserId, (int) $before->user_id);

        $car->transfer($this->targetUserId, 'Test transfer', 'NEWOWNER', $this->testUserId);

        // Verify owner was updated
        $after = $this->db->query(
            "SELECT user_id FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertSame($this->targetUserId, (int) $after->user_id);
    }

    /**
     * Test car transfer creates history record
     */
    #[Group('fast')]
    public function testTransferCreatesHistoryRecord(): void
    {
        $car = new Car($this->testCarId);
        $carId = $car->data()->id;

        $car->transfer($this->targetUserId, 'Test transfer history', 'NEWOWNER', $this->testUserId);

        // Check that history record was created with NEWOWNER operation
        $historyQuery = $this->db->query(
            "SELECT * FROM cars_hist WHERE car_id = ? AND operation = 'NEWOWNER'",
            [$carId]
        );
        $this->assertTrue($historyQuery->count() > 0);
    }

    /**
     * Test car transfer copies user profile data
     */
    #[Group('fast')]
    public function testTransferCopiesUserProfileData(): void
    {
        $car = new Car($this->testCarId);

        // Get target user's profile data
        $targetUser = (new Owner($this->targetUserId))->data();
        $this->assertNotNull($targetUser);

        $car->transfer($this->targetUserId, 'Test transfer profile', 'NEWOWNER', $this->testUserId);

        // Verify that car now has target user's profile data
        $updatedCar = new Car((int) $car->data()->id);
        $this->assertEquals($targetUser->fname ?? '', $updatedCar->data()->fname);
        $this->assertEquals($targetUser->lname ?? '', $updatedCar->data()->lname);
        $this->assertEquals($targetUser->email ?? '', $updatedCar->data()->email);
    }

    /**
     * Test car transfer transaction rollback on failure
     */
    #[Group('fast')]
    public function testTransferTransactionRollbackOnFailure(): void
    {
        // Test that invalid user causes transfer to fail completely
        $this->expectException(CarValidationException::class);

        $car = new Car($this->testCarId);
        $originalUserId = $car->data()->user_id;

        try {
            $car->transfer(99999, 'Test transfer', 'NEWOWNER', $this->testUserId);
        } catch (Exception $e) {
            // After failed transfer, car should still have original owner
            $carReloaded = new Car((int) $car->data()->id);
            $this->assertEquals($originalUserId, $carReloaded->data()->user_id);
            throw $e;
        }
    }

    /**
     * Test car transfer with TRANSFER operation type
     */
    #[Group('fast')]
    public function testTransferCarWithTransferOperationType(): void
    {
        $car = new Car($this->testCarId);
        $carId = $car->data()->id;

        $car->transfer($this->targetUserId, 'Test transfer', 'TRANSFER', $this->testUserId);

        // Check that history record was created with TRANSFER operation
        $historyQuery = $this->db->query(
            "SELECT * FROM cars_hist WHERE car_id = ? AND operation = 'TRANSFER'",
            [$carId]
        );
        $this->assertTrue($historyQuery->count() > 0);
    }

    /**
     * Test car transfer updates location data if available
     */
    #[Group('fast')]
    public function testTransferUpdatesLocationData(): void
    {
        $car = new Car($this->testCarId);

        $car->transfer($this->targetUserId, 'Test transfer location', 'NEWOWNER', $this->testUserId);

        // Verify that car now has target user's location data
        $targetUser = (new Owner($this->targetUserId))->data();
        $updatedCar = new Car((int) $car->data()->id);

        $this->assertEquals($targetUser->city ?? '', $updatedCar->data()->city);
        $this->assertEquals($targetUser->state ?? '', $updatedCar->data()->state);
        $this->assertEquals($targetUser->country ?? '', $updatedCar->data()->country);
    }

    /**
     * Test transfer works with an explicit actingUserId even when global $user is unset.
     * Verifies that Car::transfer() does not fall back to currentUserId() internally.
     */
    #[Group('fast')]
    public function testTransferHonorsExplicitActingUserIdWithoutGlobalUser(): void
    {
        // Car::__construct() needs a global $user (via getSettings()), so construct before
        // unsetting it — only transfer() itself must not fall back to a global $user internally.
        $car = new Car($this->testCarId);

        $savedUser = $GLOBALS['user'] ?? null;
        unset($GLOBALS['user']);

        try {
            $car->transfer($this->targetUserId, 'Explicit actingUserId test', 'NEWOWNER', $this->testUserId);
        } finally {
            if ($savedUser !== null) {
                $GLOBALS['user'] = $savedUser;
            }
        }

        // Reload with global $user restored (Car::__construct() needs it via getSettings())
        // to verify transfer() itself did not depend on it.
        $transferredCar = new Car($this->testCarId);
        $this->assertEquals(
            $this->targetUserId,
            $transferredCar->data()->user_id,
            'transfer() must not depend on global $user'
        );
    }
}
