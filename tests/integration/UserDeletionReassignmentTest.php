<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end test for the user deletion → car reassignment flow.
 *
 * Verifies that after_user_deletion.php correctly reassigns cars.user_id to
 * the noowner account when a user is deleted, without relying on any FK
 * constraint to do the work.
 *
 * This test closes the gap that allowed the #1279 race condition to reach
 * production: the previous FK test exercised the constraint in isolation
 * (bypassing the hook), so the hook was never tested end-to-end.
 */
#[Group('integration')]
final class UserDeletionReassignmentTest extends IntegrationTestCase
{
    private $savedGlobalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        // after_user_deletion.php requires an authenticated session (currentUserId()
        // throws RuntimeException otherwise) — see the ASSUMPTION comment in
        // after_user_deletion.php for which production callers provide one. The hook
        // itself only checks for a session, not admin permission, so this fixture is
        // an authenticated (non-admin) test user, not an actual admin. Without it, the
        // hook aborts before reassigning cars and the final assertion below fails with
        // a confusing "wrong ID" message instead of a clear "no session" error.
        $this->savedGlobalUser = $GLOBALS['user'] ?? null;

        $actingUserId = $this->createTestUser();
        $actingUser = new User();
        $actingUser->find($actingUserId);
        $reflection = new ReflectionClass($actingUser);
        $isLoggedInProperty = $reflection->getProperty('_isLoggedIn');
        $isLoggedInProperty->setValue($actingUser, true);
        $GLOBALS['user'] = $actingUser;
    }

    protected function tearDown(): void
    {
        // Restore rather than unset — bootstrap-integration.php seeds a fallback
        // $GLOBALS['user'] once per process, so $savedGlobalUser should never actually
        // be null here in practice. The unset() branch is defensive: it only matters if
        // some earlier test explicitly unsets the global (none currently do), so that a
        // later test class isn't left with no global $user at all if that ever changes.
        if ($this->savedGlobalUser !== null) {
            $GLOBALS['user'] = $this->savedGlobalUser;
        } else {
            unset($GLOBALS['user']);
        }

        parent::tearDown();
    }

    /**
     * Full hook path: delete user → require after_user_deletion.php → cars go to noowner.
     *
     * Arrange: create a test user with one car.
     * Act:     delete the user row (as deleteUsers() would), then run the hook.
     * Assert:  cars.user_id equals the noowner ID (never NULL).
     */
    public function test_afterUserDeletionHook_reassignsCarsToNoowner(): void
    {
        $noOwnerRow = $this->db->query("SELECT id FROM users WHERE username = ?", ['noowner'])->first();
        $this->assertNotEmpty($noOwnerRow, 'noowner system account missing — run composer seed:run (NoownerSeed)');
        $noOwnerId = (int) $noOwnerRow->id;

        $userId = $this->createTestUser();
        $carId  = $this->createTestCar($userId);

        // Simulate deleteUsers() having removed the user row.
        // With fk_cars_user_id gone, this does NOT touch cars.user_id.
        $this->db->delete('users', ['id', '=', $userId]);
        // Leave $userId in createdUserIds — tearDown's redundant DELETE is a 0-row no-op.

        // Pre-condition: car still carries the deleted user's ID (no FK to NULL it).
        $before = $this->db->query("SELECT user_id FROM cars WHERE id = ?", [$carId])->first();
        $this->assertSame($userId, (int) $before->user_id, 'Pre-condition: cars.user_id intact after user deleted (no FK)');

        // Run the hook exactly as deleteUsers() does: inject $id and $db, then require.
        $id = $userId;
        $db = $this->db;
        require TESTING_ROOT . '/usersc/scripts/after_user_deletion.php';

        // Assert: car is now owned by noowner, not the deleted user and not NULL.
        $after = $this->db->query("SELECT user_id FROM cars WHERE id = ?", [$carId])->first();
        $this->assertNotNull($after->user_id, 'cars.user_id must not be NULL after hook runs');
        $this->assertSame($noOwnerId, (int) $after->user_id, 'cars.user_id must equal noowner ID after hook');
    }
}
