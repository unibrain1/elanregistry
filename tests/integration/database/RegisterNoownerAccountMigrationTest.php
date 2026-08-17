<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for migration 20260817035200_register_noowner_account
 *
 * The `noowner` account is the GDPR reassignment target: when an owner deletes
 * their account, `usersc/scripts/after_user_deletion.php` moves their cars to
 * this user so the registry records survive while the PII goes away. That makes
 * it a permanently-live account nobody may ever authenticate as.
 *
 * The migration's own `assertInvariants()` enforces the security columns at
 * write time, but only on the environment where the migration actually runs.
 * These tests are the standing guard: they re-verify the same invariants against
 * whatever state the database is in now, so drift introduced by a later
 * migration, a manual production edit, or an edit to the migration's `EMAIL`
 * const fails the suite rather than silently opening a login path.
 *
 * Each assertion below maps to a numbered gate in the migration's SECURITY MODEL
 * docblock — read that first if one of these fails.
 *
 * Issue #1679.
 */
#[Group('integration')]
#[Group('migration')]
#[Group('regression')]
final class RegisterNoownerAccountMigrationTest extends IntegrationTestCase
{
    private const USERNAME = 'noowner';

    /**
     * Must match RegisterNoownerAccount::EMAIL. Deliberately duplicated rather
     * than imported: Phinx migration classes are not Composer-autoloaded, and
     * a literal here means changing the migration's const without updating this
     * test breaks the build — which is the point.
     */
    private const EXPECTED_EMAIL = 'noowner@invalid';

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $applied = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM phinxlog WHERE version = 20260817035200"
        )->first();

        if (!$applied || (int) $applied->cnt === 0) {
            $this->markTestSkipped(
                'Migration 20260817035200 has not been applied. Run: composer migrate'
            );
        }
    }

    /**
     * @return object The single `noowner` users row.
     */
    private function fetchNoowner(): object
    {
        $row = $this->db->query(
            "SELECT id, password, email, protected FROM users WHERE username = ?",
            [self::USERNAME]
        )->first();

        $this->assertNotFalse(
            $row,
            'The noowner account does not exist. Account deletion would fall back to ' .
            'cars.user_id = NULL and silently orphan car records.'
        );

        return $row;
    }

    /**
     * Gate 1 — `password = NULL` makes password login fail closed by
     * construction. `User::loginEmail()` tests `password !== null` *before*
     * calling `password_verify()`, so a NULL here is categorically stronger
     * than a random hash (impossible vs. merely improbable).
     */
    #[Group('integration')]
    #[Group('regression')]
    public function test_noownerHasNullPasswordSoPasswordLoginIsImpossible(): void
    {
        $this->assertNull(
            $this->fetchNoowner()->password,
            'noowner has a non-NULL password. Password login is no longer impossible ' .
            'by construction — see gate 1 of RegisterNoownerAccount::class docblock.'
        );
    }

    /**
     * Gates 2 and 3 — both recovery paths depend on this exact address, for
     * two *different* reasons:
     *
     *   - Password reset is closed by validation: `forgot_password.php` requires
     *     the submitted address to clear Validate's `valid_email` rule
     *     (FILTER_VALIDATE_EMAIL), which a bare-label domain fails.
     *   - Passwordless login is closed by delivery: `passwordless.php` applies
     *     no format check, so this address *does* match and *does* create a
     *     pending `us_email_logins` row. That row is inert only because
     *     `.invalid` (RFC 2606) cannot resolve, so the emailed code reaches
     *     nobody and the row expires after 15 minutes.
     *
     * Any routable address here re-opens the passwordless path. This test is the
     * only automated thing standing between that and production.
     */
    #[Group('integration')]
    #[Group('regression')]
    public function test_noownerEmailIsUnroutableSoNeitherRecoveryPathCanReachIt(): void
    {
        $email = $this->fetchNoowner()->email;

        $this->assertSame(
            self::EXPECTED_EMAIL,
            $email,
            'noowner email changed. Both account-recovery gates depend on this ' .
            'exact value — see gates 2 and 3 of RegisterNoownerAccount::class docblock.'
        );

        $this->assertFalse(
            (bool) filter_var($email, FILTER_VALIDATE_EMAIL),
            'noowner email now passes FILTER_VALIDATE_EMAIL, so it can be submitted ' .
            'through the password-reset form and match this row (gate 2).'
        );

        $this->assertStringEndsWith(
            '@invalid',
            $email,
            'noowner email no longer uses the RFC 2606 reserved .invalid TLD. Passwordless ' .
            'login has no validation gate, so an address that can actually receive mail ' .
            'would deliver a working login code to whoever controls it (gate 3).'
        );
    }

    /**
     * `protected = 1` excludes the account from admin and automated
     * account-deletion cleanup (`app/admin/includes/account-cleanup-helpers.php`).
     * Without it the GDPR reassignment target can itself be deleted, at which
     * point the deletion hook starts orphaning cars.
     */
    #[Group('integration')]
    #[Group('regression')]
    public function test_noownerIsProtectedFromAccountCleanup(): void
    {
        $this->assertSame(
            1,
            (int) $this->fetchNoowner()->protected,
            'noowner has protected != 1, so account-cleanup would not exclude it.'
        );
    }

    /**
     * The deletion hook resolves the target with a username lookup that takes
     * the first match. A duplicate row would make which account receives a
     * deleted owner's cars depend on row order — and only one of them is
     * guaranteed to carry the locked-down security columns above.
     */
    #[Group('integration')]
    #[Group('regression')]
    public function test_exactlyOneNoownerAccountExists(): void
    {
        $count = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM users WHERE username = ?",
            [self::USERNAME]
        )->first();

        $this->assertSame(
            1,
            (int) $count->cnt,
            'Expected exactly one noowner account. Duplicates make the GDPR ' .
            'reassignment target ambiguous.'
        );
    }
}
