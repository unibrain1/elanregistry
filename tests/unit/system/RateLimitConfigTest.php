<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit test for the 'registration_recovery_email' rate-limit entry (issue #1406).
 *
 * The registration-enumeration fix in usersc/join.php calls
 * checkRateLimit('registration_recovery_email', null, $email) before sending a
 * recovery notification, so an entry must exist in the *actually active*
 * rate-limit config or that call silently no-ops at runtime.
 *
 * IMPORTANT: at runtime, users/includes/rate_limits.php (upstream framework
 * defaults — gitignored, environment-local, NOT present in a fresh checkout)
 * conditionally `include`s the project-owned usersc/includes/rate_limits.php,
 * which reassigns `$rateLimits = [...]` wholesale (not a merge) — so whatever
 * is defined there completely replaces the framework defaults regardless of
 * what the framework file itself contains. This test requires
 * usersc/includes/rate_limits.php directly (the tracked file, always present
 * in CI) rather than going through the untracked framework wrapper, since
 * that wrapper's only relevant effect — the wholesale $rateLimits
 * reassignment — is what this test actually needs to exercise.
 */
#[Group('fast')]
final class RateLimitConfigTest extends TestCase
{
    public function testRegistrationRecoveryEmailActionIsConfigured(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        /** @var array<string, array<string, int>> $rateLimits */
        $rateLimits = [];
        require $projectRoot . '/usersc/includes/rate_limits.php';

        $this->assertIsArray($rateLimits);
        $this->assertArrayHasKey(
            'registration_recovery_email',
            $rateLimits,
            'registration_recovery_email must be configured in usersc/includes/rate_limits.php '
                . '(the project override, which wholesale-replaces the framework defaults) — '
                . 'usersc/join.php calls checkRateLimit() with this action name and will silently '
                . 'no-op if it is missing.'
        );
        // Mirrors the project's actual active password_reset_request limits
        // (usersc/includes/rate_limits.php), not the framework's inflated
        // defaults in users/includes/rate_limits.php.
        $this->assertSame(3, $rateLimits['registration_recovery_email']['email_max']);
        $this->assertSame(3600, $rateLimits['registration_recovery_email']['email_window']);
    }
}
