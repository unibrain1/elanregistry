<?php

declare(strict_types=1);

use ElanRegistry\RegistrationRecoveryNotifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

// Test double for \User — kept in its own file, excluded from PHPStan's scan
// path (see phpstan.neon and the file's own docblock), so it doesn't leak
// its narrow 2-method surface into PHPStan's understanding of \User
// project-wide (#1566).
require_once __DIR__ . '/_User_test_double.php';

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
    /** @var DB&MockObject */
    private $db;

    protected function setUp(): void
    {
        parent::setUp();

        global $mockSentEmails, $mockEmailSendResult, $mockEmailBodyResult, $mockLogEntries;
        $mockSentEmails = [];
        $mockEmailSendResult = null;
        $mockEmailBodyResult = null;
        $mockLogEntries = [];

        $this->db = $this->createMock(DB::class);
    }

    protected function tearDown(): void
    {
        global $mockSentEmails, $mockEmailSendResult, $mockEmailBodyResult, $mockLogEntries;
        unset($mockSentEmails, $mockEmailSendResult, $mockEmailBodyResult, $mockLogEntries);

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

        $this->db->expects($this->once())
            ->method('update')
            ->with(
                'users',
                $this->identicalTo(42),
                $this->callback(static fn(array $data): bool => isset($data['vericode'], $data['vericode_expiry']))
            )
            ->willReturn(true);

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, $email, $this->settings());

        $this->assertTrue($result);

        global $mockSentEmails;

        $this->assertCount(1, $mockSentEmails, 'Exactly one email should be sent');
        $this->assertSame($email, $mockSentEmails[0][0], 'Email must be sent to the submitted address');
    }

    /**
     * PDO/mysqli can return database INTEGER columns as numeric strings (see
     * docs/development/STRICT_TYPE_HANDLING.md) — \User::data()->id is not
     * guaranteed to already be a native int. This file (and DB::update()'s
     * mock signature in tests/bootstrap-unit.php) declares strict_types=1,
     * so passing an uncast numeric string to $this->db->update()'s int $id
     * parameter throws a TypeError. That TypeError would be silently caught
     * by this method's own catch(\Throwable), logged, and swallowed — the
     * caller (join.php) sees no difference, but no recovery email is ever
     * sent in production. This test uses a string 'id' specifically so a
     * regression here fails loudly instead of passing silently.
     */
    public function testNotifyIfAccountExistsCastsStringIdToInt(): void
    {
        $fuser = new User(true, (object) ['id' => '42', 'fname' => 'Jane']);

        // identicalTo() is a strict (===) match, so a string '42' reaching
        // DB::update() fails the expectation rather than passing on ==.
        $this->db->expects($this->once())
            ->method('update')
            ->with('users', $this->identicalTo(42), $this->anything())
            ->willReturn(true);

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, 'jane@example.com', $this->settings());

        $this->assertTrue($result, 'A string account ID must not cause a silent TypeError failure');
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

        $this->db->expects($this->never())->method('update');

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, 'nobody@example.com', $this->settings());

        $this->assertFalse($result);

        global $mockSentEmails;
        $this->assertEmpty($mockSentEmails, 'No email should be sent when the account does not exist');
    }

    public function testVericodeIsHashedNotStoredAsPlaintext(): void
    {
        $fuser = new User(true, (object) ['id' => 42, 'fname' => 'Jane']);

        // Capture the persisted value rather than asserting inside the callback:
        // notifyIfAccountExists() catches \Throwable, so an assertion failure
        // raised mid-call would be swallowed and reported as an unrelated failure.
        $storedVericode = null;
        $this->db->expects($this->once())
            ->method('update')
            ->willReturnCallback(
                function (string $table, int $id, array $data) use (&$storedVericode): bool {
                    $storedVericode = $data['vericode'] ?? null;
                    return true;
                }
            );

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $notifier->notifyIfAccountExists($fuser, 'jane@example.com', $this->settings());

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
        $this->db->expects($this->once())->method('update')->willReturn(false);

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
        $this->db->expects($this->once())->method('update')->willReturn(true);
        $GLOBALS['mockEmailSendResult'] = false;

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, 'jane@example.com', $this->settings());

        $this->assertFalse($result);
    }

    public function testEmptyRenderedBodyReturnsFalseWithoutSendingEmail(): void
    {
        $fuser = new User(true, (object) ['id' => 42, 'fname' => 'Jane']);
        $this->db->expects($this->once())->method('update')->willReturn(true);
        $GLOBALS['mockEmailBodyResult'] = '';

        $notifier = new RegistrationRecoveryNotifier($this->db);
        $result = $notifier->notifyIfAccountExists($fuser, 'jane@example.com', $this->settings());

        $this->assertFalse($result, 'An empty rendered template body must be treated as a failure');

        global $mockSentEmails;
        $this->assertEmpty($mockSentEmails, 'No email should be sent when the template body is empty');
    }
}
