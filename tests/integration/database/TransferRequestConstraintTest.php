<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Schema constraint tests for car_transfer_requests.
 *
 *   1. expires_at allows NULL (column re-typed to NULL DEFAULT NULL in #693)
 *
 * A second test asserting `existing_car_id → cars.id ON DELETE CASCADE` was
 * removed in #1679: that FK does not exist. The baseline migration documents
 * its absence deliberately (20260709000000_add_elanregistry_baseline.php, the
 * REVIEW note above the `cars` DDL) because dev lacks the constraint too, and
 * the migration reproduces dev's real schema. Deleting a car therefore leaves
 * its transfer-request rows orphaned — a real gap, tracked as #1547. Restore
 * that test only as part of adding the FK, never on its own.
 */
#[Group('integration')]
final class TransferRequestConstraintTest extends IntegrationTestCase
{
    /** @var int[] Transfer request IDs to clean up in tearDown (before base class deletes cars) */
    private array $createdTransferIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
        $this->createdTransferIds = [];
    }

    protected function tearDown(): void
    {
        // Delete every transfer request this test created. There is no FK from
        // existing_car_id to cars.id (#1547), so nothing is removed for us when
        // the base class tearDown deletes the cars — these rows would otherwise
        // be left orphaned in the test database.
        foreach ($this->createdTransferIds as $id) {
            try {
                $this->db->query("DELETE FROM car_transfer_requests WHERE id = ?", [$id]);
            } catch (\Throwable $e) {
                // Ignore — the base class DELETE may already have removed it.
            }
        }
        $this->createdTransferIds = [];

        parent::tearDown();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Insert a minimal car_transfer_requests row for a given car and user.
     * Mirrors the pattern in TransferIntegrationTestCase::createTransferRequest().
     *
     * @param int   $carId      The existing_car_id value
     * @param int   $userId     Used for both requested_by_user_id and created_by
     * @param array $overrides  Column overrides (e.g. ['expires_at' => null])
     * @return int              The newly inserted row ID
     */
    private function createTransferRequest(int $carId, int $userId, array $overrides = []): int
    {
        $defaults = [
            'existing_car_id'      => $carId,
            'requested_by_user_id' => $userId,
            'security_token'       => bin2hex(random_bytes(32)),
            'expires_at'           => date('Y-m-d H:i:s', strtotime('+30 days')),
            'submitted_model'      => 'S4|SE|FHC',
            'submitted_series'     => 'S4',
            'submitted_variant'    => 'SE',
            'submitted_year'       => '1973',
            'submitted_type'       => 'FHC',
            'submitted_chassis'    => 'FKTEST001',
            'submitted_color'      => 'Red',
            'submitted_engine'     => 'ENG001',
            'submitted_comments'   => 'FK constraint test',
            'submitted_email'      => 'fktest@example.com',
            'submitted_fname'      => 'FK',
            'submitted_lname'      => 'Test',
            'submitted_city'       => 'Portland',
            'submitted_state'      => 'Oregon',
            'submitted_country'    => 'United States',
            'created_by'           => $userId,
        ];

        $row = array_merge($defaults, $overrides);

        $this->db->query(
            'INSERT INTO car_transfer_requests (
                existing_car_id, requested_by_user_id, security_token, expires_at,
                submitted_model, submitted_series, submitted_variant, submitted_year, submitted_type,
                submitted_chassis, submitted_color, submitted_engine, submitted_comments,
                submitted_email, submitted_fname, submitted_lname, submitted_city, submitted_state, submitted_country,
                created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $row['existing_car_id'],
                $row['requested_by_user_id'],
                $row['security_token'],
                $row['expires_at'],
                $row['submitted_model'],
                $row['submitted_series'],
                $row['submitted_variant'],
                $row['submitted_year'],
                $row['submitted_type'],
                $row['submitted_chassis'],
                $row['submitted_color'],
                $row['submitted_engine'],
                $row['submitted_comments'],
                $row['submitted_email'],
                $row['submitted_fname'],
                $row['submitted_lname'],
                $row['submitted_city'],
                $row['submitted_state'],
                $row['submitted_country'],
                $row['created_by'],
            ]
        );

        $id = (int) $this->db->lastId();
        if ($id <= 0) {
            throw new \RuntimeException("createTransferRequest: INSERT failed — check NOT NULL columns");
        }

        $this->createdTransferIds[] = $id;
        return $id;
    }

    // =========================================================================
    // Tests
    // =========================================================================

    /**
     * car_transfer_requests.expires_at accepts NULL.
     *
     * Before the migration the column was NOT NULL DEFAULT '0000-00-00 00:00:00'.
     * After: NULL DEFAULT NULL.
     *
     * Arrange: create a test car and user.
     * Act:     insert a transfer request with expires_at explicitly NULL.
     * Assert:  no exception is thrown and the row exists with a NULL expires_at.
     */
    public function test_transferRequest_expiresAtAcceptsNull(): void
    {
        $userId = $this->createTestUser();
        $carId  = $this->createTestCar($userId);

        $transferId = $this->createTransferRequest($carId, $userId, ['expires_at' => null]);

        $row = $this->db->query(
            "SELECT id, expires_at FROM car_transfer_requests WHERE id = ?",
            [$transferId]
        )->first();

        $this->assertNotNull($row, 'Transfer request row must exist after insert with NULL expires_at');
        $this->assertNull($row->expires_at, 'expires_at must be stored as NULL');
    }
}
