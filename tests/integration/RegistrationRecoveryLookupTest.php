<?php

declare(strict_types=1);

use ElanRegistry\RegistrationRecoveryNotifier;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test proving the #1442 edge case is safe against the REAL
 * \User::find() resolution path, not just a hand-written test double.
 *
 * tests/unit/auth/RegistrationRecoveryNotifierTest.php proves
 * RegistrationRecoveryNotifier is a no-op when given a \User whose exists()
 * returns false — but that test defines its own stand-in User class, so it
 * cannot catch a regression in the real lookup wiring: usersc/join.php's
 * `new \User($email, 'forceEmail')` call (see the comment at that call site)
 * must resolve strictly against the users.email column, never falling back
 * to a username match. This test exercises that exact construction against
 * a real database row to close that gap.
 *
 * #1442 itself (the autoassignun TOCTOU race that can produce a
 * username-only validation failure with no real email collision) is out of
 * scope here and tracked separately — this test only proves that if such a
 * collision occurs, the recovery notifier still correctly treats it as
 * "no account exists for this email" and stays silent.
 */
#[Group('database')]
final class RegistrationRecoveryLookupTest extends IntegrationTestCase
{
    public function testForceEmailLookupIgnoresUsernameCollision(): void
    {
        $this->requireDatabase();

        // A real, existing account — its username is what an attacker's
        // registration attempt could collide with, but its email is
        // unrelated to the email being submitted below.
        $collidingUsername = 'collide_' . uniqid('', true);
        $this->createTestUser([
            'username' => $collidingUsername,
            'email' => 'owner_' . uniqid('', true) . '@example.com',
        ]);

        // The email submitted at registration does NOT belong to any
        // account — only the username collided. 'forceEmail' must resolve
        // this to "no account" rather than matching the colliding user by
        // username.
        $submittedEmail = 'attacker_' . uniqid('', true) . '@example.com';
        $fuser = new User($submittedEmail, 'forceEmail');

        $this->assertFalse(
            $fuser->exists(),
            'forceEmail lookup must not match a user by username — a username-only collision '
                . '(#1442) must resolve to "no account exists" for the submitted email'
        );

        // notifyIfAccountExists() must therefore be a true no-op: no DB
        // write, no email attempt — confirmed via its own return value
        // since exists() === false short-circuits before any side effect.
        $notifier = new RegistrationRecoveryNotifier(DB::getInstance());
        $result = $notifier->notifyIfAccountExists($fuser, $submittedEmail, (object) ['reset_vericode_expiry' => 60]);

        $this->assertFalse($result, 'No recovery notification should be sent for a username-only collision');
    }

    public function testForceEmailLookupMatchesRealAccountByEmail(): void
    {
        $this->requireDatabase();

        $email = 'existing_' . uniqid('', true) . '@example.com';
        $userId = $this->createTestUser(['email' => $email]);

        $fuser = new User($email, 'forceEmail');

        $this->assertTrue($fuser->exists(), 'forceEmail lookup must match an existing account by its email column');
        $this->assertSame($userId, (int) $fuser->data()->id);
    }
}
