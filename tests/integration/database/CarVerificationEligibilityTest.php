<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use ElanRegistry\Car\CarRepository;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for CarRepository::findVerificationEligible() (issue #1155).
 *
 * The existing unit tests for this method only string-match the SQL against a
 * mocked DB — nothing runs it against real, populated data to confirm the
 * WHERE clause actually includes/excludes the correct rows. These tests
 * create one real `cars` row per condition and assert on that row's presence
 * (or absence) in the result set, rather than on total row counts, since the
 * shared integration DB may contain other rows that would make count-based
 * assertions flaky.
 *
 * Eligibility (per CarRepository::findVerificationEligible()):
 *   solddate IS NULL
 *   AND email_bounced = 0
 *   AND email IS NOT NULL AND email != ''
 *   AND (last_verified IS NULL OR last_verified < NOW() - INTERVAL 2 YEAR)
 *   AND COALESCE(owner_last_updated, mtime) < NOW() - INTERVAL 2 YEAR
 */
#[Group('integration')]
#[Group('car-verification')]
final class CarVerificationEligibilityTest extends IntegrationTestCase
{
    private int $testUserId;
    private CarRepository $repo;

    /** More than 2 years ago — satisfies both the last_verified and owner_last_updated staleness checks. */
    private const STALE_DATE = '-3 years';

    /** Within the last 2 years — fails the staleness checks. */
    private const RECENT_DATE = '-1 day';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        foreach (['owner_last_updated', 'email_bounced', 'last_verified'] as $column) {
            $this->assertColumnExists('cars', $column);
        }

        $this->testUserId = $this->createTestUser();
        $this->loginAsTestUser($this->testUserId);
        $this->repo = new CarRepository($this->db);
    }

    /**
     * Skips the test (rather than failing with a DB error) if the given
     * column is not yet present — mirrors CarVerificationColumnsHistTest's
     * pattern for a migration that may not have run yet.
     */
    private function assertColumnExists(string $table, string $column): void
    {
        $check = $this->db->query(
            "SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = ?
               AND COLUMN_NAME  = ?
             LIMIT 1",
            [$table, $column]
        );

        if (!$check || $check->count() === 0) {
            $this->markTestSkipped(
                "{$table}.{$column} not yet available — run `composer migrate`"
            );
        }
    }

    /** @return int[] */
    private function eligibleIds(): array
    {
        $results = $this->repo->findVerificationEligible(1000, 0);
        return array_map(static fn ($row) => (int) $row->id, $results);
    }

    private function staleDate(): string
    {
        return date('Y-m-d H:i:s', strtotime(self::STALE_DATE));
    }

    private function recentDate(): string
    {
        return date('Y-m-d H:i:s', strtotime(self::RECENT_DATE));
    }

    #[Group('fast')]
    public function testSoldCarIsExcluded(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => 'sold-owner@example.com',
            'email_bounced'      => 0,
            'last_verified'      => null,
            'owner_last_updated' => $this->staleDate(),
            'mtime'              => $this->staleDate(),
            'solddate'           => date('Y-m-d', strtotime(self::STALE_DATE)),
        ]);

        $this->assertNotContains(
            $carId,
            $this->eligibleIds(),
            'A car with a non-null solddate must be excluded from verification eligibility'
        );
    }

    #[Group('fast')]
    public function testBouncedEmailCarIsExcluded(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => 'bounced-owner@example.com',
            'email_bounced'      => 1,
            'last_verified'      => null,
            'owner_last_updated' => $this->staleDate(),
            'mtime'              => $this->staleDate(),
            'solddate'           => null,
        ]);

        $this->assertNotContains(
            $carId,
            $this->eligibleIds(),
            'A car with email_bounced = 1 must be excluded from verification eligibility'
        );
    }

    #[Group('fast')]
    public function testEmptyEmailCarIsExcluded(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => '',
            'email_bounced'      => 0,
            'last_verified'      => null,
            'owner_last_updated' => $this->staleDate(),
            'mtime'              => $this->staleDate(),
            'solddate'           => null,
        ]);

        $this->assertNotContains(
            $carId,
            $this->eligibleIds(),
            'A car with an empty email must be excluded from verification eligibility'
        );
    }

    #[Group('fast')]
    public function testNullEmailCarIsExcluded(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => null,
            'email_bounced'      => 0,
            'last_verified'      => null,
            'owner_last_updated' => $this->staleDate(),
            'mtime'              => $this->staleDate(),
            'solddate'           => null,
        ]);

        $this->assertNotContains(
            $carId,
            $this->eligibleIds(),
            'A car with a NULL email must be excluded from verification eligibility'
        );
    }

    /**
     * The specific fix made during this issue's implementation: a car that has
     * never been verified (last_verified IS NULL) must still be eligible, as
     * long as it is otherwise stale — NULL must not silently exclude the row.
     */
    #[Group('fast')]
    public function testNeverVerifiedCarIsEligible(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => 'never-verified-owner@example.com',
            'email_bounced'      => 0,
            'last_verified'      => null,
            'owner_last_updated' => $this->staleDate(),
            'mtime'              => $this->staleDate(),
            'solddate'           => null,
        ]);

        $this->assertContains(
            $carId,
            $this->eligibleIds(),
            'A car with last_verified IS NULL and a stale owner_last_updated must be eligible for verification'
        );
    }

    #[Group('fast')]
    public function testRecentlyVerifiedCarIsExcluded(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => 'recently-verified-owner@example.com',
            'email_bounced'      => 0,
            'last_verified'      => $this->recentDate(),
            'owner_last_updated' => $this->staleDate(),
            'mtime'              => $this->staleDate(),
            'solddate'           => null,
        ]);

        $this->assertNotContains(
            $carId,
            $this->eligibleIds(),
            'A car verified within the last 2 years must be excluded from verification eligibility'
        );
    }

    /** The fully-eligible case: stale last_verified AND stale owner_last_updated. */
    #[Group('fast')]
    public function testStaleCarIsEligible(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => 'stale-owner@example.com',
            'email_bounced'      => 0,
            'last_verified'      => $this->staleDate(),
            'owner_last_updated' => $this->staleDate(),
            'mtime'              => $this->staleDate(),
            'solddate'           => null,
        ]);

        $this->assertContains(
            $carId,
            $this->eligibleIds(),
            'A car with both last_verified and owner_last_updated more than 2 years old must be eligible'
        );
    }

    /**
     * Confirms the owner_last_updated freshness check gates eligibility
     * independent of last_verified — a car that was never verified but was
     * recently touched by its owner must NOT be considered eligible.
     */
    #[Group('fast')]
    public function testRecentOwnerUpdateExcludesEvenWhenNeverVerified(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => 'recent-owner-update@example.com',
            'email_bounced'      => 0,
            'last_verified'      => null,
            'owner_last_updated' => $this->recentDate(),
            'mtime'              => $this->staleDate(),
            'solddate'           => null,
        ]);

        $this->assertNotContains(
            $carId,
            $this->eligibleIds(),
            'A car with last_verified IS NULL but a recent owner_last_updated must be excluded'
        );
    }

    /**
     * COALESCE(owner_last_updated, mtime) fallback: when owner_last_updated
     * has never been set, eligibility must fall back to mtime — a car whose
     * row was last touched more than 2 years ago must be eligible.
     */
    #[Group('fast')]
    public function testNullOwnerLastUpdatedFallsBackToStaleMtimeAndIsEligible(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => 'null-owner-updated-stale-mtime@example.com',
            'email_bounced'      => 0,
            'last_verified'      => null,
            'owner_last_updated' => null,
            'mtime'              => $this->staleDate(),
            'solddate'           => null,
        ]);

        $this->assertContains(
            $carId,
            $this->eligibleIds(),
            'A car with NULL owner_last_updated must fall back to a stale mtime and be eligible'
        );
    }

    /**
     * COALESCE(owner_last_updated, mtime) fallback, excluded case: when
     * owner_last_updated has never been set and mtime is recent, the car must
     * NOT be eligible.
     */
    #[Group('fast')]
    public function testNullOwnerLastUpdatedFallsBackToRecentMtimeAndIsExcluded(): void
    {
        $carId = $this->createTestCar($this->testUserId, [
            'email'              => 'null-owner-updated-recent-mtime@example.com',
            'email_bounced'      => 0,
            'last_verified'      => null,
            'owner_last_updated' => null,
            'mtime'              => $this->recentDate(),
            'solddate'           => null,
        ]);

        $this->assertNotContains(
            $carId,
            $this->eligibleIds(),
            'A car with NULL owner_last_updated and a recent mtime must be excluded'
        );
    }
}
