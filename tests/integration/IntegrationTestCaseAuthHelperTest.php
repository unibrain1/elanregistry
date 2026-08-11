<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for IntegrationTestCase's own loginAsTestUser()/restoreGlobalUser() helpers.
 *
 * The snapshot/restore bookkeeping (snapshot-on-first-call-only, idempotent restore,
 * unset-vs-restore branching) is the only non-trivial logic in the helper, and none of
 * the call sites that use it exercise those branches — each logs in exactly once and
 * lets tearDown() restore. This file covers them directly, since a regression there
 * reintroduces exactly the cross-test session leak #1572 fixed.
 *
 * Every test here deliberately manipulates $GLOBALS['user'] itself to set up the
 * precondition it needs, so setUp()/tearDown() snapshot and restore the process-wide
 * ambient value around each test — otherwise this file would leak the very state it
 * exists to protect.
 */
#[Group('integration')]
final class IntegrationTestCaseAuthHelperTest extends IntegrationTestCase
{
    /** Car ID no fixture ever creates — only ever handed to the cleanup probe below. */
    private const UNUSED_CAR_ID = PHP_INT_MAX;

    /** Value of $GLOBALS['user'] before this test ran (meaningless if $ambientUserWasSet is false). */
    private mixed $ambientUser = null;

    /** Whether $GLOBALS['user'] existed at all before this test ran. */
    private bool $ambientUserWasSet = false;

    /**
     * Set by test_tearDownRestoresGlobalBeforeRunningCleanup() only: whether the ambient
     * session was already back in $GLOBALS['user'] when tearDown()'s cleanup phase ran.
     * Null means the probe was never armed, so tearDown() has nothing to assert.
     */
    private ?bool $globalWasRestoredDuringCleanup = null;

    /** Real DB handle, stashed while the cleanup probe stands in for it. */
    private mixed $realDb = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Snapshot BEFORE requireDatabase(): markTestSkipped() returns from setUp() early,
        // but tearDown() still runs — with no snapshot taken it would fall into the
        // unset($GLOBALS['user']) branch and wipe the ambient session for the rest of
        // the process, which is the exact leak this file exists to guard against.
        $this->ambientUserWasSet = array_key_exists('user', $GLOBALS);
        $this->ambientUser = $GLOBALS['user'] ?? null;

        $this->requireDatabase();
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            if ($this->realDb !== null) {
                $this->db = $this->realDb;
                $this->realDb = null;
            }

            // Restore after parent::tearDown(), since the helper's own restore runs there
            // and would otherwise put this test's sentinel back into the global.
            if ($this->ambientUserWasSet) {
                $GLOBALS['user'] = $this->ambientUser;
            } else {
                unset($GLOBALS['user']);
            }
        }

        if ($this->globalWasRestoredDuringCleanup !== null) {
            $this->assertTrue(
                $this->globalWasRestoredDuringCleanup,
                'tearDown() must restore $GLOBALS[\'user\'] before it starts deleting fixtures'
            );
        }
    }

    /**
     * A second loginAsTestUser() call must not re-snapshot: restore returns the session
     * that was ambient before the *first* login, not the intermediate one.
     */
    public function test_secondLoginDoesNotResnapshot_restoreReturnsPreFirstLoginSession(): void
    {
        $ambientSentinel = new User();
        $GLOBALS['user'] = $ambientSentinel;

        $firstUserId  = $this->createTestUser();
        $secondUserId = $this->createTestUser();

        $firstSession = $this->loginAsTestUser($firstUserId);
        $this->assertSame($firstSession, $GLOBALS['user'], 'First login must publish to the global');

        $secondSession = $this->loginAsTestUser($secondUserId);
        $this->assertNotSame($firstSession, $secondSession, 'Each login must build a fresh User instance');
        $this->assertSame($secondSession, $GLOBALS['user'], 'Second login must publish to the global');

        $this->restoreGlobalUser();

        $this->assertSame(
            $ambientSentinel,
            $GLOBALS['user'],
            'Restore must return the session ambient before the FIRST login, not the intermediate one'
        );
    }

    /**
     * restoreGlobalUser() is idempotent: once it has restored, a second call must leave
     * $GLOBALS['user'] alone rather than re-applying a stale snapshot.
     */
    public function test_secondRestoreIsNoOp(): void
    {
        $ambientSentinel = new User();
        $GLOBALS['user'] = $ambientSentinel;

        $this->loginAsTestUser($this->createTestUser());
        $this->restoreGlobalUser();
        $this->assertSame($ambientSentinel, $GLOBALS['user'], 'First restore must put the ambient session back');

        // Simulate whatever runs next (another test, or tearDown) owning the global now.
        $laterSentinel = new User();
        $GLOBALS['user'] = $laterSentinel;

        $this->restoreGlobalUser();

        $this->assertSame(
            $laterSentinel,
            $GLOBALS['user'],
            'A second restore must be a no-op, not clobber the current session with a stale snapshot'
        );
    }

    /**
     * When nothing was ambient before the login, restore must remove the key entirely —
     * leaving it set to null would make `isset($GLOBALS['user'])` behave the same but
     * `array_key_exists()` checks see a different state than they did before the test.
     */
    public function test_restoreUnsetsGlobalWhenNothingWasAmbient(): void
    {
        // Create the fixture first: the "no ambient session" window must be as narrow
        // as possible, since DB fixture helpers may consult the global.
        $userId = $this->createTestUser();

        // tests/bootstrap-integration.php seeds a non-null $GLOBALS['user'] once per
        // process, so the no-ambient-value case has to be forced here. setUp() captured
        // the real ambient value and tearDown() puts it back.
        unset($GLOBALS['user']);

        $this->loginAsTestUser($userId);
        $this->assertArrayHasKey('user', $GLOBALS, 'Login must publish to the global');

        $this->restoreGlobalUser();

        $this->assertFalse(
            array_key_exists('user', $GLOBALS),
            'Restore must unset $GLOBALS[\'user\'] entirely when nothing was ambient, not set it to null'
        );
    }

    /**
     * A user ID with no `users` row must fail loudly. Silently returning a User that
     * find() left unpopulated would publish a hollow session to $GLOBALS['user'] and
     * surface later as a confusing null-property error in whatever the test asserts.
     */
    public function test_loginAsTestUserThrowsWhenUserIdDoesNotExist(): void
    {
        $missingUserId = $this->firstUnusedUserId();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("loginAsTestUser(): no users row with id {$missingUserId}");

        $this->loginAsTestUser($missingUserId);
    }

    /**
     * tearDown() must restore the global session *before* it deletes fixtures, so a
     * cleanup failure cannot strand the fake session in $GLOBALS['user'] for every test
     * that follows. The cleanup loops swallow their own RuntimeExceptions, so the failure
     * is invisible from outside: the DB handle is swapped for a probe that records what
     * $GLOBALS['user'] holds the moment cleanup starts and then fails the delete.
     * tearDown() above asserts on the recording.
     */
    public function test_tearDownRestoresGlobalBeforeRunningCleanup(): void
    {
        $ambientSentinel = new User();
        $GLOBALS['user'] = $ambientSentinel;

        $userId  = $this->createTestUser();
        $session = $this->loginAsTestUser($userId);
        $this->assertSame($session, $GLOBALS['user'], 'Login must publish to the global');

        // Delete the fixture through the real handle now — the probe below intercepts
        // every query tearDown() would otherwise use to clean it up.
        $this->deleteTestUser($userId);

        // Give the car cleanup loop one iteration to run. The ID is deliberately one no
        // fixture created; the probe never reaches the database with it anyway.
        $this->trackCarId(self::UNUSED_CAR_ID);

        $recordGlobal = function () use ($ambientSentinel): void {
            $this->globalWasRestoredDuringCleanup = (($GLOBALS['user'] ?? null) === $ambientSentinel);
        };

        $this->realDb = $this->db;
        $this->db     = new class ($recordGlobal) {
            public function __construct(private Closure $recordGlobal)
            {
            }

            public function query(string $sql, array $params = []): never
            {
                $this->recordAndFail();
            }

            public function delete(string $table, array $where): never
            {
                $this->recordAndFail();
            }

            private function recordAndFail(): never
            {
                ($this->recordGlobal)();
                throw new RuntimeException('intentional cleanup failure (tearDown ordering probe)');
            }
        };
    }

    /**
     * Lowest `users.id` guaranteed not to exist, with enough headroom that a concurrent
     * fixture insert cannot claim it mid-test.
     */
    private function firstUnusedUserId(): int
    {
        $result = $this->db->query('SELECT COALESCE(MAX(id), 0) + 1000000 AS unused_id FROM users');
        if ($result->error()) {
            throw new RuntimeException("Could not determine an unused user ID: {$result->errorString()}");
        }

        return (int) $result->first()->unused_id;
    }
}
