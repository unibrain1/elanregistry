<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for currentUserId() (usersc/includes/custom_functions.php).
 *
 * currentUserId() is session-coupled (reads the global $user UserSpice User
 * object) and was deliberately left un-extracted in #1599 — unlike dbInt(),
 * which was extracted to ElanRegistry\TypeHelpers because it's pure. The
 * unit-tier bootstrap (tests/bootstrap-unit.php) still defines a hand-written
 * stand-in with identical logic, but it is never exercised against the real
 * function; these tests fill that gap by running against the real
 * implementation, loaded via the full framework bootstrap
 * (tests/bootstrap-integration.php -> users/init.php).
 *
 * Uses IntegrationTestCase::loginAsTestUser() (#1630) to fake an
 * authenticated session without a real HTTP request — the same pattern
 * tests/integration/UserDeletionReassignmentTest.php already uses for a hook
 * that also calls currentUserId() internally.
 *
 * Throughout this file, `global $user` and `$GLOBALS['user']` are written
 * together for clarity even though they're the same storage slot — see
 * IntegrationTestCase::loginAsTestUser()'s docblock for why.
 *
 * @issue 1599
 */
#[Group('integration')]
final class CurrentUserIdTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    /**
     * With no ambient session, currentUserId() must throw rather than return
     * a silently wrong ID (e.g. 0) — a quiet wrong answer would be worse than
     * a loud failure for anything gating on "who is the acting user".
     */
    public function test_currentUserId_noSession_throwsRuntimeException(): void
    {
        global $user;
        $previousUser = $GLOBALS['user'] ?? null;
        $GLOBALS['user'] = null;
        $user = null;

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('No user is currently logged in');
            currentUserId();
        } finally {
            $GLOBALS['user'] = $previousUser;
            $user = $previousUser;
        }
    }

    /**
     * The !isset($user) half of currentUserId()'s guard is effectively dead in
     * production — users/init.php always constructs a $user instance — so the
     * branch that actually fires live is !$user->isLoggedIn(). This test drives
     * that branch directly with a real, never-logged-in User instance, rather
     * than relying on the noSession test above (which only exercises !isset).
     */
    public function test_currentUserId_userPresentButNotLoggedIn_throws(): void
    {
        global $user;
        $previousUser = $GLOBALS['user'] ?? null;
        $user = new User();
        $GLOBALS['user'] = $user;

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('No user is currently logged in');
            currentUserId();
        } finally {
            $GLOBALS['user'] = $previousUser;
            $user = $previousUser;
        }
    }

    /**
     * After loginAsTestUser() fakes an authenticated session, currentUserId()
     * must return that user's real id, cast to int — proving the function
     * reads $user->data()->id (which UserSpice returns as a string) through
     * the real isLoggedIn()/data() code path, not a mock.
     */
    public function test_currentUserId_loggedIn_returnsUserId(): void
    {
        $userId = $this->createTestUser();
        $this->loginAsTestUser($userId);

        $this->assertSame($userId, currentUserId());
    }
}
