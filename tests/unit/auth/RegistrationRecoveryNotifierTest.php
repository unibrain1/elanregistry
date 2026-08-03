<?php

declare(strict_types=1);

use ElanRegistry\RegistrationRecoveryNotifier;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Test double for \User.
 *
 * The real users/classes/User.php is not loaded in the unit-test environment
 * (its constructor calls the live DB::getInstance() singleton and is not
 * mockable via constructor injection), and it is not part of this project's
 * PSR-4 autoload map, so \User does not exist here unless something defines
 * it. That makes a plain stand-in class named `User` — implementing only the
 * two methods RegistrationRecoveryNotifier::notifyIfAccountExists() calls,
 * exists() and data() — the simplest and most robust option: it satisfies the
 * \User type hint directly with no reflection or subclassing tricks needed.
 */
if (!class_exists('User')) {
    class User {
        private bool $_exists;
        private object $_data;

        public function __construct(bool $exists = false, ?object $data = null) {
            $this->_exists = $exists;
            $this->_data = $data ?? new \stdClass();
        }

        public function exists(): bool {
            return $this->_exists;
        }

        public function data(): object {
            return $this->_data;
        }
    }
}

/**
 * Unit tests for RegistrationRecoveryNotifier (issue #1406).
 *
 * Covers the account-enumeration fix: when registration validation fails
 * because the submitted email already belongs to an existing account, the
 * notifier sends a private password-recovery-style email to that account
 * instead of revealing the collision in the registration response.
 */
#[Group('fast')]
final class RegistrationRecoveryNotifierTest extends TestCase
{
    /** @var DB */
    private $db;

    protected function setUp(): void
    {
        parent::setUp();

        global $mockSentEmails, $mockDbUpdateCalls, $mockDbUpdateResult, $mockEmailSendResult, $mockEmailBodyResult, $mockLogEntries;
        $mockSentEmails = [];
        $mockDbUpdateCalls = [];
        $mockDbUpdateResult = null;
        $mockEmailSendResult = null;
        $mockEmailBodyResult = null;
        $mockLogEntries = [];

        $this->db = new DB();
    }

    protected function tearDown(): void
    {
        global $mockSentEmails, $mockDbUpdateCalls, $mockDbUpdateResult, $mockEmailSendResult, $mockEmailBodyResult, $mockLogEntries;
        unset($mockSentEmails, $mockDbUpdateCalls, $mockDbUpdateResult, $mockEmailSendResult, $mockEmailBodyResult, $mockLogEntries);

        parent::tearDown();
    }

    private function settings(int $expiryMinutes = 60): object
    {
        return (object) ['reset_vericode_expiry' => $expiryMinutes, 'site_name' => 'Test Registry'];
    }

    public function testNotifyIfAccountExistsSendsEmailAndWritesHashedVericode(): void
    {
        $fuser = new User(true, (object) ['id' => 42, 'fname' => 'Jane']);
        $email = 'jane@example.com';

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, $email, $this->settings());

        $this->assertTrue($result);

        global $mockSentEmails, $mockDbUpdateCalls;

        $this->assertCount(1, $mockSentEmails, 'Exactly one email should be sent');
        $this->assertSame($email, $mockSentEmails[0][0], 'Email must be sent to the submitted address');

        $this->assertCount(1, $mockDbUpdateCalls, 'Exactly one DB update should be written');
        [$table, $id, $data] = $mockDbUpdateCalls[0];
        $this->assertSame('users', $table);
        $this->assertSame(42, $id);
        $this->assertArrayHasKey('vericode', $data);
        $this->assertArrayHasKey('vericode_expiry', $data);
    }

    /**
     * Directly encodes the #1442 edge case: a username-only collision (no real
     * email collision) must not trigger a recovery notification. The fix is
     * correct here by construction — it decides based on $fuser->exists()
     * rather than inferring intent from which validation rule failed, so an
     * account that doesn't exist for the submitted email is always a no-op.
     */
    public function testNotifyIfAccountExistsDoesNothingWhenAccountDoesNotExist(): void
    {
        $fuser = new User(false);

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, 'nobody@example.com', $this->settings());

        $this->assertFalse($result);

        global $mockSentEmails, $mockDbUpdateCalls;
        $this->assertEmpty($mockSentEmails, 'No email should be sent when the account does not exist');
        $this->assertEmpty($mockDbUpdateCalls, 'No DB write should occur when the account does not exist');
    }

    public function testVericodeIsHashedNotStoredAsPlaintext(): void
    {
        $fuser = new User(true, (object) ['id' => 42, 'fname' => 'Jane']);

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $notifier->notifyIfAccountExists($fuser, 'jane@example.com', $this->settings());

        global $mockDbUpdateCalls;
        $this->assertCount(1, $mockDbUpdateCalls);
        $storedVericode = $mockDbUpdateCalls[0][2]['vericode'];

        // bootstrap-unit.php mocks randomstring(15) to a fixed raw value of
        // str_repeat('x', 15); the stored value must not equal that raw output —
        // it must have been passed through hashVericode() first.
        $rawVericode = str_repeat('x', 15);
        $this->assertNotSame($rawVericode, $storedVericode, 'Vericode must not be stored in plaintext');
        $this->assertSame(hashVericode($rawVericode), $storedVericode, 'Stored vericode must be the hashed value');
    }

    public function testDbUpdateFailureReturnsFalseWithoutSendingEmail(): void
    {
        $fuser = new User(true, (object) ['id' => 42, 'fname' => 'Jane']);
        $GLOBALS['mockDbUpdateResult'] = false;

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, 'jane@example.com', $this->settings());

        $this->assertFalse($result, 'A failed vericode write must not be treated as success');

        global $mockSentEmails;
        $this->assertEmpty(
            $mockSentEmails,
            'No email should be sent when the vericode write failed — the link it would contain was never persisted'
        );
    }

    public function testEmailSendFailureReturnsFalseWithoutThrowing(): void
    {
        $fuser = new User(true, (object) ['id' => 42, 'fname' => 'Jane']);
        $GLOBALS['mockEmailSendResult'] = false;

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, 'jane@example.com', $this->settings());

        $this->assertFalse($result);
    }

    public function testEmptyRenderedBodyReturnsFalseWithoutSendingEmail(): void
    {
        $fuser = new User(true, (object) ['id' => 42, 'fname' => 'Jane']);
        $GLOBALS['mockEmailBodyResult'] = '';

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, 'jane@example.com', $this->settings());

        $this->assertFalse($result, 'An empty rendered template body must be treated as a failure');

        global $mockSentEmails;
        $this->assertEmpty($mockSentEmails, 'No email should be sent when the template body is empty');
    }
}
