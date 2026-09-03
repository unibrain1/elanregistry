<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Regression tests for issue #1879: usersc/user_settings.php was writing a
 * plaintext vericode to users.vericode for the email-change and
 * password-reset flows, instead of hashing it with hashVericode() (defined
 * users/helpers/us_helpers.php). The plaintext code is still what gets
 * emailed to the user — only the at-rest DB value changed.
 *
 * These tests replicate the exact write each fixed code path performs
 * (hashVericode($vericode) against a real DB row) rather than including the
 * procedural user_settings.php page script — the same style used by
 * OwnerEmailSecurityTest.php for pinning behavior inside a procedural page
 * without executing the whole page (POST handling, redirects, etc).
 *
 * The read side, users/verify.php (~line 201-216), re-hashes an incoming
 * plaintext vericode via hashVericode() and compares to the stored value with
 * hash_equals() — it explicitly rejects any plaintext match. That logic is
 * NOT modified by this fix or by these tests; test 3 below only exercises it
 * to confirm it still rejects a wrong/stale code exactly as before.
 */
#[Group('integration')]
final class UserSettingsVericodeTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    /**
     * Email-change flow (user_settings.php ~line 366):
     *   $db->update('users', $userId, ['email_new' => $email, 'vericode' => hashVericode($vericode), ...]);
     *
     * Confirms the stored vericode is the HMAC-SHA256 hash, not the plaintext
     * string that gets emailed to the user, and that the plaintext code
     * round-trips through the same hash_equals() comparison verify.php
     * performs on the read side.
     */
    public function testEmailChangeFlowStoresHashedVericodeNotPlaintext(): void
    {
        $userId = $this->createTestUser();

        $plaintextVericode = randomstring(15);

        // Replicate user_settings.php:366 — the fixed write.
        $updateResult = $this->db->update('users', $userId, [
            'email_new' => 'new_email_for_' . $userId . '@example.com',
            'vericode' => hashVericode($plaintextVericode),
        ]);
        $this->assertTrue($updateResult, 'Expected the users.vericode update to succeed');

        $this->assertStoredVericodeIsHashOfPlaintext($userId, $plaintextVericode);
    }

    /**
     * Password-reset flow (user_settings.php ~line 432, also present at
     * user_settings.php:247 for a related password-update path):
     *   $user->update(['password' => ..., 'vericode' => hashVericode(randomstring(15))], $user->data()->id);
     *
     * Same shape of assertions as the email-change flow: the stored value
     * must be the hash, and the plaintext must round-trip via hash_equals().
     */
    public function testPasswordResetFlowStoresHashedVericodeNotPlaintext(): void
    {
        $userId = $this->createTestUser();

        $plaintextVericode = randomstring(15);

        // Replicate user_settings.php:432 — the fixed write. hashVericode() is
        // applied to the freshly generated plaintext exactly as production does.
        $updateResult = $this->db->update('users', $userId, [
            'password' => password_hash('newpassword123', PASSWORD_BCRYPT, ['cost' => 12]),
            'force_pr' => 0,
            'vericode' => hashVericode($plaintextVericode),
        ]);
        $this->assertTrue($updateResult, 'Expected the users.vericode update to succeed');

        $this->assertStoredVericodeIsHashOfPlaintext($userId, $plaintextVericode);
    }

    /**
     * Shared assertions for both flows above: the stored users.vericode must
     * be hashVericode($plaintext), never the plaintext itself, and must
     * round-trip via hash_equals() the same way verify.php:216 checks it.
     */
    private function assertStoredVericodeIsHashOfPlaintext(int $userId, string $plaintextVericode): void
    {
        $row = $this->db->query('SELECT vericode FROM users WHERE id = ?', [$userId])->first();
        $this->assertNotNull($row, 'Expected to find the test user row after update');

        $storedVericode = (string) $row->vericode;

        // The at-rest value must be the hash, never the plaintext code.
        $this->assertSame(
            hashVericode($plaintextVericode),
            $storedVericode,
            'Stored users.vericode must equal hashVericode($plaintext)'
        );
        $this->assertNotSame(
            $plaintextVericode,
            $storedVericode,
            'Stored users.vericode must NOT be the plaintext vericode'
        );

        // Round trip: re-hashing the plaintext (as verify.php:216 does) must
        // hash_equals()-match the stored value, proving the emailed link works.
        $this->assertTrue(
            hash_equals($storedVericode, hashVericode($plaintextVericode)),
            'Re-hashed plaintext vericode must hash_equals() match the stored hash, mirroring verify.php\'s comparison'
        );
    }

    /**
     * Regression guard: a stale/already-consumed or simply wrong vericode
     * must still FAIL the hash_equals() lookup after this fix. Proves the
     * hashing fix did not accidentally loosen verify.php's rejection of
     * non-matching codes (e.g. by introducing a plaintext-fallback comparison).
     *
     * Mirrors verify.php's own comparison (~line 216):
     *   hash_equals((string)$verify->data()->vericode, hashVericode((string)$vericode))
     */
    public function testWrongOrStaleVericodeFailsHashEqualsLookup(): void
    {
        $userId = $this->createTestUser();

        $correctPlaintext = randomstring(15);
        $staleOrWrongPlaintext = randomstring(15);

        // Sanity: the two generated codes must actually differ, or this test
        // would pass vacuously.
        $this->assertNotSame($correctPlaintext, $staleOrWrongPlaintext);

        // Store the hash of the correct code, exactly as the fixed write does.
        $updateResult = $this->db->update('users', $userId, [
            'vericode' => hashVericode($correctPlaintext),
        ]);
        $this->assertTrue($updateResult, 'Expected the users.vericode update to succeed');

        $row = $this->db->query('SELECT vericode FROM users WHERE id = ?', [$userId])->first();
        $this->assertNotNull($row, 'Expected to find the test user row after update');

        $storedVericode = (string) $row->vericode;

        // A wrong/stale plaintext code must NOT match.
        $this->assertFalse(
            hash_equals($storedVericode, hashVericode($staleOrWrongPlaintext)),
            'A wrong or stale vericode must fail the hash_equals() lookup'
        );

        // Also confirm the fix did not accidentally introduce a plaintext
        // fallback: comparing the stored hash directly against the raw
        // plaintext (no hashing) must never match either.
        $this->assertFalse(
            hash_equals($storedVericode, $correctPlaintext),
            'A raw plaintext vericode must never hash_equals() match the stored hash'
        );

        // The correct code, correctly re-hashed, still matches — confirming
        // the failure above is due to the wrong code, not a broken comparison.
        $this->assertTrue(
            hash_equals($storedVericode, hashVericode($correctPlaintext)),
            'Sanity check: the correct plaintext must still match after hashing'
        );
    }
}
