<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use ElanRegistry\Transfer\CarTransferRepository;

/**
 * Base class for car-transfer integration tests.
 *
 * Provides a shared CarTransferRepository instance, a createTransferRequest()
 * fixture helper that delegates to CarTransferRepository::create(), and automatic
 * tearDown cleanup of every transfer request row it creates.
 *
 * setUp() calls requireDatabase() unconditionally, so every subclass requires a
 * database connection — no need for a subclass to call it again.
 */
abstract class TransferIntegrationTestCase extends IntegrationTestCase
{
    protected CarTransferRepository $repo;

    /** @var int[] Transfer request IDs created via createTransferRequest(), deleted in tearDown */
    protected array $createdTransferIds = [];

    /**
     * Skips the test via requireDatabase() if no database connection is available,
     * then builds the shared CarTransferRepository instance.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
        $this->repo = new CarTransferRepository($this->db);
    }

    protected function tearDown(): void
    {
        try {
            if ($this->databaseConnected) {
                foreach ($this->createdTransferIds as $id) {
                    // DB::query() never throws on execute-time failure — check error() explicitly
                    // so a failed cleanup DELETE doesn't silently leave a row in the test schema.
                    $result = $this->db->query("DELETE FROM car_transfer_requests WHERE id = ?", [$id]);
                    if ($result->error()) {
                        fwrite(STDERR, "NOTE: tearDown() cleanup failed for transfer request ID {$id}: {$result->errorString()}\n");
                    }
                }
            }
            $this->createdTransferIds = [];
        } finally {
            // Run even if the loop above throws (e.g. a dropped DB connection), so the
            // base class's own fixture cleanup — and its restoreGlobalUser() call — is
            // never skipped.
            parent::tearDown();
        }
    }

    /**
     * Create a car_transfer_requests row via CarTransferRepository::create(), tracked
     * for automatic cleanup in tearDown().
     *
     * @param int $carId The existing_car_id value
     * @param int $requesterId Used for requested_by_user_id and created_by
     * @param array<string, mixed> $overrides Field values merged over the defaults below;
     *   caller-supplied keys win.
     * @return int The created transfer request ID
     * @throws \ElanRegistry\Exceptions\CarDatabaseException If the insert fails
     */
    protected function createTransferRequest(int $carId, int $requesterId, array $overrides = []): int
    {
        $defaults = [
            'existing_car_id'      => $carId,
            'requested_by_user_id' => $requesterId,
            'created_by'           => $requesterId,
            'security_token'       => bin2hex(random_bytes(32)),
            'expires_at'           => date('Y-m-d H:i:s', strtotime('+30 days')),
            'submitted_model'      => 'S4|SE|FHC',
            'submitted_series'     => 'S4',
            'submitted_variant'    => 'SE',
            'submitted_year'       => '1973',
            'submitted_type'       => 'FHC',
            'submitted_chassis'    => 'INTTEST001',
            'submitted_color'      => 'Red',
            'submitted_engine'     => 'ENG001',
            'submitted_comments'   => 'Integration test request',
            'submitted_email'      => 'requester@example.com',
            'submitted_fname'      => 'Test',
            'submitted_lname'      => 'Requester',
            'submitted_city'       => 'Portland',
            'submitted_state'      => 'Oregon',
            'submitted_country'    => 'United States',
        ];

        $id = $this->repo->create(array_merge($defaults, $overrides));
        $this->createdTransferIds[] = $id;
        return $id;
    }
}
