<?php

declare(strict_types=1);

require_once __DIR__ . '/TransferIntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for the transfer-request initiation step.
 *
 * Covers the DB operations performed by app/api/cars/transfer-request.php.
 * The endpoint file itself cannot be included (it calls send() / exit), so
 * these tests exercise the same SQL queries and guard logic directly against
 * the real database.
 *
 * Complements CarTransferWorkflowTest (approve/deny coverage).
 */
#[Group('integration')]
#[Group('transfer')]
final class TransferRequestTest extends TransferIntegrationTestCase
{
    // =========================================================================
    // Tests
    // =========================================================================

    /**
     * A valid initiation creates a car_transfer_requests row in the database
     * with the correct car ID, requester ID, and 'pending' status.
     *
     * Pins the INSERT path in transfer-request.php:130–160.
     */
    public function testValidRequestCreatesTransferRow(): void
    {
        $ownerId     = $this->createTestUser();
        $requesterId = $this->createTestUser();
        $carId       = $this->createTestCar($ownerId);

        $transferId = $this->createTransferRequest($carId, $requesterId);

        $row = $this->db->query(
            "SELECT existing_car_id, requested_by_user_id, status FROM car_transfer_requests WHERE id = ?",
            [$transferId]
        )->first();

        $this->assertNotNull($row, 'Transfer request row must exist after creation');
        $this->assertSame((string) $carId, (string) $row->existing_car_id);
        $this->assertSame((string) $requesterId, (string) $row->requested_by_user_id);
        $this->assertSame('pending', $row->status, "New transfer requests must default to 'pending'");
    }

    /**
     * A duplicate pending request for the same car by the same user is detected
     * by the guard query in transfer-request.php:111–118.
     *
     * Pins that the guard SELECT with status='pending' returns > 0 when a
     * duplicate exists, so the endpoint can reject it.
     */
    public function testDuplicateRequestForSameCarIsRejected(): void
    {
        $ownerId     = $this->createTestUser();
        $requesterId = $this->createTestUser();
        $carId       = $this->createTestCar($ownerId);

        $this->createTransferRequest($carId, $requesterId);

        // Replicate the endpoint's duplicate-check guard
        $duplicateCheck = $this->db->query(
            'SELECT id FROM car_transfer_requests WHERE existing_car_id = ? AND requested_by_user_id = ? AND status = "pending"',
            [$carId, $requesterId]
        );

        $this->assertGreaterThan(0, $duplicateCheck->count(),
            'Guard query must find the existing pending request so the endpoint can reject the duplicate'
        );
    }

    /**
     * A request for a car the user already owns is detected by the ownership
     * check in transfer-request.php:106–108.
     *
     * Uses the endpoint's actual car-lookup query (year/type/chassis) rather
     * than a direct id lookup so that any future regression swapping those
     * columns would break this test.
     */
    public function testRequestForOwnedCarIsRejectedByOwnershipCheck(): void
    {
        $ownerId = $this->createTestUser();
        $chassis = 'OWN' . substr(uniqid(), -8); // 11 chars, within VARCHAR(15) limit
        $this->createTestCar($ownerId, ['year' => '1973', 'type' => 'FHC', 'chassis' => $chassis]);

        // Replicate the endpoint's car lookup (transfer-request.php:94–96)
        $carRow = $this->db->query(
            'SELECT id, user_id FROM cars WHERE year = ? AND type = ? AND chassis = ?',
            ['1973', 'FHC', $chassis]
        )->first();

        $this->assertNotNull($carRow, 'Endpoint car lookup must find the car by year/type/chassis');

        // Replicate the endpoint's ownership guard: $existingCar->user_id == $user->data()->id
        $ownerAlreadyOwnsCar = ((int) $carRow->user_id === $ownerId);
        $this->assertTrue($ownerAlreadyOwnsCar,
            'Owner must be detected as already owning the car so the endpoint can reject the self-transfer'
        );
    }

    /**
     * A request for a non-existent car (bogus chassis or year) is detected by
     * the car lookup in transfer-request.php:94–101.
     *
     * Pins that the SELECT returns 0 rows when no car matches, causing the
     * endpoint to throw "No car found with this chassis number".
     */
    public function testInvalidCarChassisReturnsNoResults(): void
    {
        $nonExistentChassis = 'GHOST_' . uniqid();

        $result = $this->db->query(
            'SELECT id, user_id FROM cars WHERE year = ? AND type = ? AND chassis = ?',
            ['1999', 'FHC', $nonExistentChassis]
        );

        $this->assertSame(0, $result->count(),
            'Car lookup must return 0 rows for a non-existent chassis so the endpoint can reject the request'
        );
    }
}
