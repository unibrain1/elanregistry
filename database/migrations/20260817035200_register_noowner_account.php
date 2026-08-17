<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Create the `noowner` system account used as the GDPR reassignment target.
 *
 * When an owner deletes their account, `usersc/scripts/after_user_deletion.php`
 * looks the account up by username (`SELECT id FROM users WHERE username = ?`,
 * bound to `'noowner'`) and reassigns their cars to it, so the car records
 * survive while the PII goes away. If the account is missing the hook silently
 * falls back to setting `cars.user_id = NULL`, which leaves the registry with
 * genuinely ownerless rows.
 *
 * ADR-010 originally flagged "No migration script creates noowner" as a
 * known P2 gap — the account was created by hand on production in 2012 and
 * no provisioning path reproduced it. `database/seeds/NoownerSeed.php`
 * closed that gap first as a seed; this migration replaces it. The noowner
 * account is part of the registry's base configuration and should never
 * need to be replayed.
 *
 * SECURITY MODEL — nobody may log in as this account, and nobody may
 * recover it. Three independent gates, each verified against the code:
 *
 * 1. `password = NULL` blocks every password login. `User::loginEmail()`
 *    (users/classes/User.php) tests `$this->data()->password !== null`
 *    *before* it ever calls `password_verify()`, so the account fails closed
 *    by construction rather than by entropy. A random complex hash would be
 *    strictly weaker here: it would make login improbable instead of
 *    impossible.
 * 2. `email = 'noowner@invalid'` blocks password reset. `users/forgot_password.php`
 *    only issues a vericode when `$fuser->exists()` — an email lookup against
 *    submitted POST input that must first clear Validate's `valid_email` rule
 *    (`filter_var($value, FILTER_VALIDATE_EMAIL)`, users/classes/Validate.php).
 *    A bare-label domain fails that filter, so this address can never be
 *    submitted through the form and the lookup can never match this row.
 *    `.invalid` is the RFC 2606 reserved TLD, so the address is also
 *    guaranteed non-resolvable if it ever escapes into a mail path.
 * 3. Passwordless login is closed by delivery, not by input validation.
 *    Unlike gate 2, `users/passwordless.php` applies no server-side email
 *    format check before its `SELECT * FROM users WHERE email = ?` lookup
 *    (`Input::get()` sanitizes but does not validate), so submitting
 *    `noowner@invalid` does match this row and does insert an
 *    `us_email_logins` row. That row is inert: the vericode and numeric code
 *    are generated server-side, stored only as hashes, and transmitted
 *    exclusively in the email body — `passwordless.php` never renders either
 *    value to the page, and `users/verify.php` accepts only a hashed match.
 *    Because `.invalid` is the RFC 2606 reserved TLD the message cannot be
 *    delivered, so the code has no path to any attacker and the pending row
 *    simply expires after 15 minutes. Note the send failure does *not*
 *    invalidate the row early, so this gate rests entirely on `.invalid`
 *    being non-resolvable: **never point this account at a routable address.**
 *
 * `protected = 1` additionally excludes the account from admin/automated
 * account-deletion cleanup (`app/admin/includes/account-cleanup-helpers.php`),
 * so the GDPR reassignment target cannot itself be deleted.
 *
 * KNOWN GAP, not addressed here: `app/admin/assets/admin-core.js` hardcodes
 * user id 83 for the "Assign to No Owner" reassignment control (also
 * `app/admin/includes/tab-car_mgmt.php`, `app/admin/index.php`). That id is
 * only correct by accident on the existing production database — this
 * migration lets `noowner`'s id fall out of AUTO_INCREMENT, so on any freshly
 * provisioned environment the reassignment control silently targets whichever
 * unrelated account (or no account) holds id 83 instead. Fixing the admin UI
 * to resolve `noowner` by username server-side, like the deletion hook
 * already does, was tracked separately as #1562 (resolved).
 *
 * Idempotent, and self-healing: the account may already exist on environments
 * provisioned before this migration was introduced (created by the seed this
 * migration replaces, or by hand on production in 2012). Existing rows are
 * brought up to the security model above via UPDATE rather than trusted or
 * rejected — the 2012 production account really does carry `protected = 0`
 * and a routable placeholder email, which is exactly the drift this migration
 * exists to close. Only the three security columns are touched; `id`,
 * `fname`, `lname` and `active` are left alone so existing `cars.user_id`
 * references and FK integrity are unaffected.
 *
 * down() deletes the account only when no car references it — the shape a
 * fresh install's up() leaves behind. If cars have already been reassigned to
 * it, down() leaves it in place rather than orphaning them.
 */
final class RegisterNoownerAccount extends AbstractMigration
{
    private const USERNAME = 'noowner';

    /**
     * Deliberately unroutable — see the security model in the class docblock.
     * A bare-label domain fails FILTER_VALIDATE_EMAIL, so this address cannot
     * clear the password-reset form's validation; `.invalid` is RFC
     * 2606-reserved, so it cannot resolve, which is what closes the
     * passwordless path (that form does not validate format). Both properties
     * are load-bearing — do not replace this with a routable address.
     */
    private const EMAIL = 'noowner@invalid';

    public function up(): void
    {
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();

        $existing = $this->findExisting();
        if ($existing !== null) {
            $this->lockDown((int) $existing['id']);
            $this->assertInvariants($this->findExisting());
            $adapter->commitTransaction();
            return;
        }

        $now = date('Y-m-d H:i:s');

        $this->execute(
            "INSERT INTO `users`
                (username, password, email, fname, lname, active, permissions,
                 protected, logins, un_changed, join_date, last_login, created, modified)
             VALUES
                ('" . self::USERNAME . "', NULL, '" . self::EMAIL . "', 'No', 'Owner',
                 1, 0, 1, 0, 0, '{$now}', '{$now}', '{$now}', '{$now}')"
        );

        $created = $this->findExisting();
        if ($created === null) {
            $adapter->rollbackTransaction();
            throw new RuntimeException(
                'RegisterNoownerAccount: the INSERT ran but no user named ' . self::USERNAME . ' exists. ' .
                'Account deletion would silently orphan cars — investigate before going live.'
            );
        }
        $this->assertInvariants($created);

        $adapter->commitTransaction();
    }

    public function down(): void
    {
        $adapter = $this->getAdapter();
        $adapter->beginTransaction();

        // up() may have created the account or merely locked down a
        // pre-existing one, and down() runs in a separate process so it cannot
        // remember which. Decide from the data instead: if any car has been
        // reassigned to this account, deleting it would orphan those rows, so
        // leave it in place. Only a completely unreferenced account — the
        // shape a fresh install's up() produces — is removed.
        $existing = $this->findExisting();
        if ($existing === null) {
            $adapter->commitTransaction();
            return;
        }

        $id = (int) $existing['id'];
        $referenced = $this->fetchRow("SELECT COUNT(*) AS c FROM `cars` WHERE `user_id` = {$id}");
        if ($referenced !== false && (int) $referenced['c'] === 0) {
            $this->execute("DELETE FROM `users` WHERE `id` = {$id}");
        }

        $adapter->commitTransaction();
    }

    /**
     * Force the three security columns to the locked-down state, whatever the
     * existing row currently holds. Idempotent by construction — running it
     * against an already-correct row is a no-op UPDATE.
     */
    private function lockDown(int $id): void
    {
        $this->execute(
            "UPDATE `users`
                SET `password` = NULL,
                    `email` = '" . self::EMAIL . "',
                    `protected` = 1
              WHERE `id` = {$id}"
        );
    }

    /**
     * @return array{id: int, password: string|null, email: string, protected: int}|null
     */
    private function findExisting(): ?array
    {
        $row = $this->fetchRow(
            "SELECT id, password, email, protected FROM `users` WHERE `username` = '" . self::USERNAME . "'"
        );

        return $row !== false ? $row : null;
    }

    /**
     * Post-write verification: the row must actually be unauthenticatable and
     * unrecoverable before this migration reports success. A failure here
     * means the write did not land as intended, not that data merely drifted.
     *
     * @param array{id: int, password: string|null, email: string, protected: int}|null $user
     */
    private function assertInvariants(?array $user): void
    {
        if ($user === null) {
            throw new RuntimeException(
                'RegisterNoownerAccount: no user named ' . self::USERNAME . ' after write.'
            );
        }
        if ($user['password'] !== null) {
            throw new RuntimeException(
                'RegisterNoownerAccount: the noowner account has a non-NULL password, so it is no ' .
                'longer unauthenticatable by construction — investigate before trusting ' .
                'GDPR-deletion reassignment.'
            );
        }
        if ($user['email'] !== self::EMAIL) {
            throw new RuntimeException(
                'RegisterNoownerAccount: the noowner account email is not ' . self::EMAIL . ', so ' .
                'password-reset and passwordless login may be able to reach it.'
            );
        }
        if ((int) $user['protected'] !== 1) {
            throw new RuntimeException(
                'RegisterNoownerAccount: the noowner account has protected != 1, so account-deletion ' .
                'cleanup would not exclude it. Investigate before trusting GDPR-deletion ' .
                'reassignment.'
            );
        }
    }
}
