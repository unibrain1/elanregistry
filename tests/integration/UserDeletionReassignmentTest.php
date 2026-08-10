<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\LogCategories;
use PHPUnit\Framework\Attributes\Group;

/**
 * End-to-end test for the user deletion → car reassignment flow.
 *
 * Verifies that after_user_deletion.php correctly reassigns cars.user_id to
 * the noowner account when a user is deleted, without relying on any FK
 * constraint to do the work; that it deletes the user's profile row and
 * expires their pending transfer requests in the same transaction; and that
 * it logs the reassignment (one completion summary plus one entry per car).
 *
 * This test closes the gap that allowed the #1279 race condition to reach
 * production: the previous FK test exercised the constraint in isolation
 * (bypassing the hook), so the hook was never tested end-to-end.
 */
#[Group('integration')]
final class UserDeletionReassignmentTest extends IntegrationTestCase
{
    private $savedGlobalUser;

    /**
     * @var int[] user_ids of profile rows this test inserted directly (bypassing
     *     IntegrationTestCase's fixtures, which don't track profiles). The hook is
     *     expected to delete these itself, but a failed assertion or an early-return
     *     in the hook (e.g. no session, a DB error on the noowner lookup, or a
     *     transaction rollback — the no-noowner fallback branch still deletes the
     *     profile, so that alone isn't a leak scenario) would otherwise leak the row
     *     into the persistent integration schema forever.
     */
    private array $createdProfileUserIds = [];

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

        // Belt-and-suspenders: the hook is expected to have already deleted these
        // (that's what the test asserts), so this is normally a 0-row no-op. It only
        // matters if an assertion failed partway through and left a row behind.
        foreach ($this->createdProfileUserIds as $userId) {
            $this->db->query('DELETE FROM profiles WHERE user_id = ?', [$userId]);
        }

        parent::tearDown();
    }

    /**
     * Full hook path: delete user → require after_user_deletion.php → cars go to noowner,
     * profile is deleted, pending transfer requests expire.
     *
     * Arrange: create a test user with two cars (proves the hook's per-car reassignment
     *          loop, not just a single-iteration pass), a profile row, and a pending
     *          transfer request they initiated. Also create an unrelated second user's
     *          pending transfer request, to prove the hook's expiry UPDATE is scoped to
     *          the deleted user and doesn't over-match.
     * Act:     delete the user row (as deleteUsers() would), then run the hook.
     * Assert:  cars.user_id equals the noowner ID for both cars (never NULL); the profile
     *          row is gone; the pending transfer request is expired; the other user's
     *          transfer request is untouched; the hook logs one UserDeletion completion
     *          summary and one CarActions entry per car.
     */
    public function test_afterUserDeletionHook_cleansUpUserDataAndLogs(): void
    {
        $noOwnerRow = $this->db->query("SELECT id FROM users WHERE username = ?", ['noowner'])->first();
        $this->assertNotEmpty($noOwnerRow, 'noowner system account missing — run composer seed:run (NoownerSeed)');
        $noOwnerId = (int) $noOwnerRow->id;

        $userId = $this->createTestUser();
        $carIds = [$this->createTestCar($userId), $this->createTestCar($userId)];

        $profileInserted = $this->db->insert('profiles', [
            'user_id' => $userId,
            'bio'     => 'Integration test fixture profile',
            'city'    => 'Hethel',
            'state'   => 'Norfolk',
            'country' => 'United Kingdom',
        ]);
        $this->assertTrue((bool) $profileInserted, 'Test fixture: profiles insert must succeed');
        $this->createdProfileUserIds[] = $userId;

        // Transfer request the deleted user initiated for someone else's car — the hook
        // must expire it (not leave it pointing at a now-deleted requester). existing_car_id
        // has no FK to cars.id (see #1547), so any car ID would satisfy the schema; this
        // test reuses one of its own tracked cars instead so IntegrationTestCase's
        // tearDown() (which deletes car_transfer_requests by existing_car_id) cleans this
        // row up automatically rather than leaking it into the persistent test schema.
        $transferRequestId = $this->insertTransferRequest($carIds[0], $userId);

        // An unrelated user's pending transfer request — must survive the hook untouched.
        // Without this, a regression that widened the hook's WHERE clause (e.g. dropping
        // the requested_by_user_id filter) would silently expire every pending request in
        // the schema and nothing here would catch it.
        $otherUserId = $this->createTestUser();
        $otherCarId = $this->createTestCar($otherUserId);
        $otherTransferRequestId = $this->insertTransferRequest($otherCarId, $otherUserId);

        // Simulate deleteUsers() having removed the user row.
        // With fk_cars_user_id gone, this does NOT touch cars.user_id.
        $this->db->delete('users', ['id', '=', $userId]);
        // Leave $userId in createdUserIds — tearDown's redundant DELETE is a 0-row no-op.

        // Pre-condition: cars still carry the deleted user's ID (no FK to NULL them).
        foreach ($carIds as $carId) {
            $before = $this->db->query("SELECT user_id FROM cars WHERE id = ?", [$carId])->first();
            $this->assertSame($userId, (int) $before->user_id, "Pre-condition: car $carId's user_id intact after user deleted (no FK)");
        }

        // Pre-condition: profile and both pending transfer requests still exist.
        $this->assertSame(1, $this->profileRowCount($userId), 'Pre-condition: profile row exists before hook runs');
        $this->assertSame(
            'pending',
            $this->transferRequestStatus($transferRequestId),
            'Pre-condition: transfer request still pending before hook runs'
        );
        $this->assertSame(
            'pending',
            $this->transferRequestStatus($otherTransferRequestId),
            'Pre-condition: other user\'s transfer request still pending before hook runs'
        );

        // logs is never truncated between runs against the persistent integration schema
        // (tearDown() only deletes cars, cars_hist, and car_transfer_requests rows tied to
        // those cars, plus users — never logs). The completion-summary message is
        // identical across runs (it carries only the noowner ID and car count, both
        // stable), so an absolute assertSame(1, ...) would only pass on a freshly
        // provisioned schema and fail on every rerun. The per-car messages embed
        // fresh auto-increment IDs and are usually unique per run, but IDs can repeat
        // across runs if the schema's AUTO_INCREMENT is ever reset — before/after
        // deltas make both assertions robust to that regardless.
        $completionLogMessage = sprintf(
            'Complete cleanup: reassigned %d cars to noowner user (ID: %d)',
            count($carIds),
            $noOwnerId
        );
        $perCarLogMessages = array_map(
            fn (int $carId): string => sprintf(
                'User deletion: car ID %d reassigned from user %d to noowner (ID: %d)',
                $carId,
                $userId,
                $noOwnerId
            ),
            $carIds
        );
        $completionLogBefore = $this->countMatchingLogs(
            LogCategories::LOG_CATEGORY_USER_DELETION,
            $completionLogMessage
        );
        $perCarLogsBefore = array_map(
            fn (string $message): int => $this->countMatchingLogs(LogCategories::LOG_CATEGORY_CAR_ACTIONS, $message),
            $perCarLogMessages
        );

        // Run the hook exactly as deleteUsers() does: inject $id and $db, then require.
        $id = $userId;
        $db = $this->db;
        require TESTING_ROOT . '/usersc/scripts/after_user_deletion.php';

        // Assert: both cars are now owned by noowner, not the deleted user and not NULL.
        foreach ($carIds as $carId) {
            $after = $this->db->query("SELECT user_id FROM cars WHERE id = ?", [$carId])->first();
            $this->assertNotNull($after->user_id, "cars.user_id must not be NULL after hook runs (car $carId)");
            $this->assertSame($noOwnerId, (int) $after->user_id, "cars.user_id must equal noowner ID after hook (car $carId)");
        }

        // Assert: the profile row was deleted (GDPR erasure).
        $this->assertSame(0, $this->profileRowCount($userId), 'profiles row must be deleted after hook runs');

        // Assert: the pending transfer request was expired, not left pointing at a
        // deleted requester.
        $transferAfter = $this->db->query(
            "SELECT status, completed_date, admin_notes FROM car_transfer_requests WHERE id = ?",
            [$transferRequestId]
        )->first();
        $this->assertSame('expired', $transferAfter->status, 'Pending transfer request must be expired after hook runs');
        $this->assertNotNull($transferAfter->completed_date, 'Expired transfer request must have a completed_date set');
        $this->assertStringContainsString(
            'Account deleted',
            (string) $transferAfter->admin_notes,
            'Expired transfer request must note the account deletion'
        );

        // Assert: the expiry UPDATE is scoped to the deleted user — an unrelated
        // pending request must survive untouched.
        $this->assertSame(
            'pending',
            $this->transferRequestStatus($otherTransferRequestId),
            "Other user's unrelated transfer request must remain pending after hook runs"
        );

        // Assert: the hook logged the completion summary exactly once.
        $this->assertLoggedExactlyOnceSince(
            LogCategories::LOG_CATEGORY_USER_DELETION,
            $completionLogMessage,
            $completionLogBefore,
            'Hook must log exactly one UserDeletion completion summary'
        );

        // Assert: the hook logged exactly one CarActions entry per car — proves the
        // per-car loop fires for each car, not just once regardless of car count.
        foreach ($carIds as $index => $carId) {
            $this->assertLoggedExactlyOnceSince(
                LogCategories::LOG_CATEGORY_CAR_ACTIONS,
                $perCarLogMessages[$index],
                $perCarLogsBefore[$index],
                "Hook must log exactly one CarActions entry for car $carId"
            );
        }
    }

    /**
     * Count profile rows for a user — used before/after the hook to confirm the
     * profile is deleted (GDPR erasure) rather than left behind or double-deleted.
     */
    private function profileRowCount(int $userId): int
    {
        $row = $this->db->query('SELECT COUNT(*) AS cnt FROM profiles WHERE user_id = ?', [$userId])->first();

        return (int) $row->cnt;
    }

    /**
     * Insert a pending car_transfer_requests row and return its ID. Explicitly supplies
     * every NOT NULL column with no default (the six submitted_* fields, plus
     * existing_car_id, requested_by_user_id, security_token, created_by) rather than
     * relying on the DB class's sql_mode='' connection setting to silently tolerate
     * missing them.
     */
    private function insertTransferRequest(int $carId, int $userId): int
    {
        $inserted = $this->db->insert('car_transfer_requests', [
            'existing_car_id'      => $carId,
            'requested_by_user_id' => $userId,
            'created_by'           => $userId,
            'security_token'       => bin2hex(random_bytes(16)),
            'status'               => 'pending',
            'expires_at'           => date('Y-m-d H:i:s', strtotime('+30 days')),
            'submitted_model'      => 'Elan',
            'submitted_series'     => 'S4',
            'submitted_variant'    => 'SE',
            'submitted_year'       => '1973',
            'submitted_type'       => 'FHC',
            'submitted_chassis'    => 'T' . substr(uniqid(), -10),
        ]);
        $this->assertTrue((bool) $inserted, 'Test fixture: car_transfer_requests insert must succeed');

        return (int) $this->db->lastId();
    }

    /**
     * Fetch a car_transfer_requests row's current status by ID.
     */
    private function transferRequestStatus(int $transferRequestId): string
    {
        $row = $this->db->query(
            'SELECT status FROM car_transfer_requests WHERE id = ?',
            [$transferRequestId]
        )->first();

        return (string) $row->status;
    }

    /**
     * Assert a logs table match count grew by exactly one since $countBefore was
     * captured. See the before-capture comment in the test method for why deltas
     * (not absolute counts) are required against the persistent integration schema.
     *
     * $lognote is passed to countMatchingLogs() as a LIKE pattern — safe today since
     * none of this file's log messages contain '%' or '_', but a future message that
     * does would silently widen the match instead of failing loudly.
     */
    private function assertLoggedExactlyOnceSince(string $logtype, string $lognote, int $countBefore, string $failureMessage): void
    {
        $this->assertSame($countBefore + 1, $this->countMatchingLogs($logtype, $lognote), $failureMessage);
    }
}
