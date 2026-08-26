<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';
require_once __DIR__ . '/../../app/admin/includes/account-cleanup-helpers.php';

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for findVerifiedOwnerlessAccounts() and
 * findUnverifiedOwnerlessAccounts().
 *
 * This file merges the former FindVerifiedOwnerlessAccountsIntegrationTest and
 * FindUnverifiedOwnerlessAccountsIntegrationTest — the two finders share nearly
 * identical eligibility rules (protected accounts, 'noowner' sentinel, cars
 * ownership, pending transfer requests, threshold boundaries), differing only
 * in which column drives the inactivity window (last_login/email_verified vs.
 * join_date) and their default threshold (365 vs 30 days). Shared behaviour is
 * covered once via #[DataProvider('finderProvider')]; behaviour unique to one
 * finder remains a standalone test.
 *
 * Each test creates real database fixtures (users, cars) and asserts that the
 * function's SQL filters include or exclude those fixtures correctly. All
 * fixtures are cleaned up automatically in tearDown().
 *
 * Tests assert presence/absence of a specific user ID in results; they never
 * assert row counts because the live database contains other accounts.
 *
 * @see OwnerlessAccountsFinderTest  (unit tests — SQL-agnostic)
 */
#[Group('integration')]
#[Group('admin')]
final class OwnerlessAccountsFinderIntegrationTest extends IntegrationTestCase
{
    /** @var int[] car_transfer_requests row IDs inserted directly by tests — cleaned in tearDown */
    private array $createdTransferRequestIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdTransferRequestIds as $requestId) {
            try {
                $this->db->query("DELETE FROM car_transfer_requests WHERE id = ?", [$requestId]);
            } catch (\Throwable $e) {
                // Safety net — parent tearDown also cleans these up via DELETE WHERE existing_car_id.
            }
        }
        $this->createdTransferRequestIds = [];

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return a date string exactly $days days ago at midnight (00:00:00).
     * Midnight keeps DATEDIFF deterministic regardless of the time the test runs.
     */
    private function daysAgo(int $days): string
    {
        return date('Y-m-d', strtotime("-{$days} days")) . ' 00:00:00';
    }

    /**
     * Extract user IDs from results as strings.
     *
     * DB::query() returns integer columns as strings (PDO default behaviour).
     * PHPUnit assertContains/assertNotContains use strict (===) comparison, so
     * comparing an int $userId against a string '5003' would silently give the
     * wrong answer. Casting here keeps assertions correct regardless of the
     * underlying PDO fetch mode.
     *
     * @param array<object> $results
     * @return string[]
     */
    private function idsFrom(array $results): array
    {
        return array_map('strval', array_column($results, 'id'));
    }

    // -------------------------------------------------------------------------
    // Data provider
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: int, 2: callable(string): array<string, mixed>}>
     */
    public static function finderProvider(): array
    {
        return [
            'verified' => [
                'findVerifiedOwnerlessAccounts',
                365,
                fn (string $daysAgo) => ['email_verified' => 1, 'last_login' => $daysAgo],
            ],
            'unverified' => [
                'findUnverifiedOwnerlessAccounts',
                30,
                fn (string $daysAgo) => ['join_date' => $daysAgo],
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Shared exclusion tests — the user must NOT appear in results
    // -------------------------------------------------------------------------

    /**
     * A user who owns a car (row in the `cars` table via user_id) must be
     * excluded even though they otherwise qualify as ownerless.
     */
    #[DataProvider('finderProvider')]
    public function testExcludesAccountWithCarsRow(string $functionName, int $threshold, callable $fixtureBuilder): void
    {
        $userId = $this->createTestUser($fixtureBuilder($this->daysAgo($threshold + 10)));
        $this->createTestCar($userId);

        $results = $functionName($this->db, $threshold);
        $ids     = $this->idsFrom($results);

        $this->assertNotContains((string) $userId, $ids, 'User with a cars row must be excluded');
    }

    /**
     * A protected account (protected = 1) must be excluded regardless of
     * verification status, login history, or join date.
     */
    #[DataProvider('finderProvider')]
    public function testExcludesProtectedAccount(string $functionName, int $threshold, callable $fixtureBuilder): void
    {
        $overrides = $fixtureBuilder($this->daysAgo($threshold + 10));
        $overrides['protected'] = 1;
        $userId = $this->createTestUser($overrides);

        $results = $functionName($this->db, $threshold);
        $ids     = $this->idsFrom($results);

        $this->assertNotContains((string) $userId, $ids, 'Protected user must be excluded');
    }

    /**
     * An account with username 'noowner' must be excluded because the query
     * uses it as the "unassigned cars" sentinel user.
     */
    #[DataProvider('finderProvider')]
    public function testExcludesNoownerUsername(string $functionName, int $threshold, callable $fixtureBuilder): void
    {
        $overrides = $fixtureBuilder($this->daysAgo($threshold + 10));
        $overrides['username'] = 'noowner';
        $userId = $this->createTestUser($overrides);

        $results = $functionName($this->db, $threshold);
        $ids     = $this->idsFrom($results);

        $this->assertNotContains((string) $userId, $ids, "User with username 'noowner' must be excluded");
    }

    /**
     * A user who has a pending car transfer request must be excluded even if
     * they have no car ownership records.
     *
     * Setup:
     *   1. Create the test user (otherwise-eligible, no cars).
     *   2. Create a dummy user and a car for them to satisfy the FK on existing_car_id.
     *   3. Insert a car_transfer_requests row with the test user as requested_by_user_id
     *      and status = 'pending'.
     *   4. Assert the test user is absent from results.
     *
     * Cleanup: the child tearDown() deletes the request row by ID; parent tearDown()
     * also removes any remaining transfer requests via DELETE WHERE existing_car_id.
     */
    #[DataProvider('finderProvider')]
    public function testExcludesUserWithPendingTransferRequest(string $functionName, int $threshold, callable $fixtureBuilder): void
    {
        $testUserId  = $this->createTestUser($fixtureBuilder($this->daysAgo($threshold + 10)));
        $dummyUserId = $this->createTestUser();
        $carId       = $this->createTestCar($dummyUserId);

        $this->db->insert('car_transfer_requests', [
            'existing_car_id'      => $carId,
            'requested_by_user_id' => $testUserId,
            'created_by'           => $testUserId,
            'security_token'       => bin2hex(random_bytes(16)),
            'status'               => 'pending',
            'expires_at'           => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $row = $this->db->query(
            "SELECT id FROM car_transfer_requests WHERE requested_by_user_id = ? ORDER BY id DESC LIMIT 1",
            [$testUserId]
        )->first();
        if ($row) {
            $this->createdTransferRequestIds[] = (int) $row->id;
        }

        $results = $functionName($this->db, $threshold);
        $ids     = $this->idsFrom($results);

        $this->assertNotContains((string) $testUserId, $ids, 'User with a pending transfer request must be excluded from cleanup eligibility');
    }

    // -------------------------------------------------------------------------
    // Shared inclusion tests
    // -------------------------------------------------------------------------

    /**
     * A user whose only transfer request is in a terminal state (denied) must
     * still be included in results — the NOT EXISTS guard applies only to
     * 'pending' requests.
     *
     * This prevents a regression where dropping the status condition from the
     * NOT EXISTS clause would permanently shield any user who ever submitted a
     * transfer request.
     */
    #[DataProvider('finderProvider')]
    public function testIncludesUserWithDeniedTransferRequest(string $functionName, int $threshold, callable $fixtureBuilder): void
    {
        $testUserId  = $this->createTestUser($fixtureBuilder($this->daysAgo($threshold + 10)));
        $dummyUserId = $this->createTestUser();
        $carId       = $this->createTestCar($dummyUserId);

        $this->db->insert('car_transfer_requests', [
            'existing_car_id'      => $carId,
            'requested_by_user_id' => $testUserId,
            'created_by'           => $testUserId,
            'security_token'       => bin2hex(random_bytes(16)),
            'status'               => 'denied',
            'expires_at'           => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);

        $row = $this->db->query(
            "SELECT id FROM car_transfer_requests WHERE requested_by_user_id = ? ORDER BY id DESC LIMIT 1",
            [$testUserId]
        )->first();
        if ($row) {
            $this->createdTransferRequestIds[] = (int) $row->id;
        }

        $results = $functionName($this->db, $threshold);
        $ids     = $this->idsFrom($results);

        $this->assertContains((string) $testUserId, $ids, 'User with only a denied transfer request must still be eligible for cleanup');
    }

    // -------------------------------------------------------------------------
    // Shared threshold boundary tests
    // -------------------------------------------------------------------------

    /**
     * A user whose inactivity window equals the threshold exactly
     * (DATEDIFF = threshold >= threshold) must be included — the boundary is
     * inclusive.
     *
     * The fixture date is set to midnight exactly $threshold days ago so
     * DATEDIFF is stable regardless of the time the test runs.
     */
    #[DataProvider('finderProvider')]
    public function testThresholdBoundaryExactMatch(string $functionName, int $threshold, callable $fixtureBuilder): void
    {
        $userId = $this->createTestUser($fixtureBuilder($this->daysAgo($threshold)));

        $results = $functionName($this->db, $threshold);
        $ids     = $this->idsFrom($results);

        $this->assertContains((string) $userId, $ids, 'User whose inactivity equals the threshold must be included');
    }

    /**
     * A user whose inactivity window is one day short of the threshold
     * (DATEDIFF = threshold - 1 < threshold) must NOT be included.
     *
     * The fixture date is set to midnight exactly ($threshold - 1) days ago so
     * DATEDIFF is stable regardless of the time the test runs.
     */
    #[DataProvider('finderProvider')]
    public function testThresholdBoundaryOneDayShort(string $functionName, int $threshold, callable $fixtureBuilder): void
    {
        $userId = $this->createTestUser($fixtureBuilder($this->daysAgo($threshold - 1)));

        $results = $functionName($this->db, $threshold);
        $ids     = $this->idsFrom($results);

        $this->assertNotContains((string) $userId, $ids, 'User whose inactivity is one day below the threshold must be excluded');
    }

    // -------------------------------------------------------------------------
    // findVerifiedOwnerlessAccounts()-only tests
    // -------------------------------------------------------------------------

    /**
     * A verified user who has never logged in (last_login IS NULL) and has no
     * car associations must be included — null last_login satisfies the OR branch.
     */
    public function testFindsVerifiedOwnerlessAccountWithNullLastLogin(): void
    {
        $userId = $this->createTestUser(['email_verified' => 1]);

        $results = findVerifiedOwnerlessAccounts($this->db, 365);
        $ids     = $this->idsFrom($results);

        $this->assertContains((string) $userId, $ids, 'Verified user with NULL last_login should appear in results');
    }

    /**
     * A verified user whose last_login is the zero-date sentinel must be
     * included — '0000-00-00 00:00:00' satisfies the second OR branch.
     */
    public function testFindsVerifiedOwnerlessAccountWithZeroLastLogin(): void
    {
        $userId = $this->createTestUser([
            'email_verified' => 1,
            'last_login'     => '0000-00-00 00:00:00',
        ]);

        $results = findVerifiedOwnerlessAccounts($this->db, 365);
        $ids     = $this->idsFrom($results);

        $this->assertContains((string) $userId, $ids, "Verified user with '0000-00-00' last_login should appear in results");
    }

    /**
     * An unverified user (email_verified = 0) must be excluded — this function
     * is specifically for the verified-but-inactive cohort.
     */
    public function testExcludesUnverifiedAccount(): void
    {
        $userId = $this->createTestUser(['email_verified' => 0]);

        $results = findVerifiedOwnerlessAccounts($this->db, 365);
        $ids     = $this->idsFrom($results);

        $this->assertNotContains((string) $userId, $ids, 'Unverified user must be excluded from the verified-account query');
    }

    /**
     * A verified user who logged in recently must be excluded.
     */
    public function testExcludesUserWithRecentLogin(): void
    {
        $userId = $this->createTestUser([
            'email_verified' => 1,
            'last_login'     => $this->daysAgo(1),
        ]);

        $results = findVerifiedOwnerlessAccounts($this->db, 365);
        $ids     = $this->idsFrom($results);

        $this->assertNotContains((string) $userId, $ids, 'Verified user with a recent last_login must be excluded');
    }

    // -------------------------------------------------------------------------
    // findUnverifiedOwnerlessAccounts()-only tests
    // -------------------------------------------------------------------------

    /**
     * A user who is active, unverified, unprotected, not named 'noowner',
     * has no cars, and joined more than 30 days ago must be included.
     */
    public function testFindsUnverifiedOwnerlessAccount(): void
    {
        $userId = $this->createTestUser(['join_date' => $this->daysAgo(31)]);

        $results = findUnverifiedOwnerlessAccounts($this->db, 30);
        $ids     = $this->idsFrom($results);

        $this->assertContains((string) $userId, $ids, 'User with no cars and old enough join date should appear in results');
    }

    /**
     * A user whose email is already verified must be excluded even if they
     * have no cars and joined long ago.
     */
    public function testExcludesEmailVerifiedAccount(): void
    {
        $userId = $this->createTestUser([
            'email_verified' => 1,
            'join_date'      => $this->daysAgo(31),
        ]);

        $results = findUnverifiedOwnerlessAccounts($this->db, 30);
        $ids     = $this->idsFrom($results);

        $this->assertNotContains((string) $userId, $ids, 'Email-verified user must be excluded');
    }
}
