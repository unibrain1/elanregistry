<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

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
 * ADR-010 flags "No migration script creates noowner" as a known P2 gap — the
 * account was created by hand on production in 2012 and no provisioning path
 * reproduced it. This seed closes that gap, matching ADR-010's spec exactly:
 * `password = NULL` (unauthenticatable by construction, not just by entropy —
 * `User::loginEmail()` requires a non-null password) and `protected = 1`
 * (excludes the account from admin/automated account-deletion cleanup, per
 * `app/admin/includes/account-cleanup-helpers.php`).
 *
 * KNOWN GAP, not addressed here: `app/admin/assets/admin-core.js` hardcodes
 * user id 83 for the "Assign to No Owner" reassignment control (also
 * `app/admin/includes/tab-car_mgmt.php`, `app/admin/index.php`). That id is
 * only correct by accident on the existing production database — this seed
 * lets `noowner`'s id fall out of AUTO_INCREMENT, so on any freshly
 * provisioned environment the reassignment control silently targets whichever
 * unrelated account (or no account) holds id 83 instead. Fixing the admin UI
 * to resolve `noowner` by username server-side, like the deletion hook
 * already does, is tracked separately — see #1562. `active = 1` and
 * `permissions = 0` are still required so the account isn't excluded from
 * those (and the deletion hook's) queries once id resolution is fixed.
 *
 * Idempotent: if a user named `noowner` already exists, re-checks the same two
 * invariants (password NULL, protected = 1) rather than trusting existence
 * alone — a hand-edited or drifted account would otherwise pass silently.
 */
final class NoownerSeed extends AbstractSeed
{
    private const USERNAME = 'noowner';

    public function run(): void
    {
        $existing = $this->findExisting();
        if ($existing !== null) {
            $this->assertInvariants($existing);
            return;
        }

        $now = date('Y-m-d H:i:s');

        // password = NULL, per ADR-010: unauthenticatable by construction, not
        // just by entropy — User::loginEmail() requires a non-null password
        // before attempting any verification, so this account fails closed on
        // every password-based login path regardless of hash strength.
        $this->insert('users', [
            'username'    => self::USERNAME,
            'password'    => null,
            'email'       => 'noowner@example.com',
            // Rendered as "No Owner" wherever the UI concatenates fname + lname.
            'fname'       => 'No',
            'lname'       => 'Owner',
            // Must stay active: deactivated users are excluded from the queries
            // the deletion hook and admin car-reassignment UI rely on.
            'active'      => 1,
            'permissions' => 0,
            // Excludes the account from admin/automated account-deletion cleanup
            // (app/admin/includes/account-cleanup-helpers.php filters on this),
            // per ADR-010's own risk mitigation for "noowner accidentally deleted."
            'protected'   => 1,
            // The remaining columns are NOT NULL with no default after the
            // baseline migration, so each needs an explicit value.
            'logins'      => 0,
            'un_changed'  => 0,
            'join_date'   => $now,
            'last_login'  => $now,
            'created'     => $now,
            'modified'    => $now,
        ]);

        $created = $this->findExisting();
        if ($created === null) {
            throw new RuntimeException(
                'NoownerSeed: the INSERT ran but no user named ' . self::USERNAME . ' exists. ' .
                'Account deletion would silently orphan cars — investigate before going live.'
            );
        }
        $this->assertInvariants($created);
    }

    /**
     * @return array{id: int, password: string|null, protected: int}|null
     */
    private function findExisting(): ?array
    {
        $row = $this->query(
            'SELECT id, password, protected FROM `users` WHERE `username` = ?',
            [self::USERNAME]
        )->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : null;
    }

    /**
     * @param array{id: int, password: string|null, protected: int} $user
     */
    private function assertInvariants(array $user): void
    {
        if ($user['password'] !== null) {
            throw new RuntimeException(
                'NoownerSeed: the noowner account has a non-NULL password. Per ADR-010 this ' .
                'account must be unauthenticatable by construction — investigate before ' .
                'trusting GDPR-deletion reassignment.'
            );
        }
        if ((int) $user['protected'] !== 1) {
            throw new RuntimeException(
                'NoownerSeed: the noowner account has protected != 1, so account-deletion ' .
                'cleanup would not exclude it. Investigate before trusting GDPR-deletion ' .
                'reassignment.'
            );
        }
    }
}
