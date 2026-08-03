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
 * IMPORTANT: users/includes/rate_limits.php (upstream framework defaults) is
 * NOT the live config. Its final lines conditionally `include` the
 * project-owned usersc/includes/rate_limits.php, which reassigns
 * `$rateLimits = [...]` wholesale (not a merge) — so whatever is defined
 * there completely replaces the framework defaults. This test loads the
 * base file with $abs_us_root/$us_url_root pointed at the real project root
 * so that include actually fires, exercising the same resolution order the
 * app uses at runtime, and asserts against the resulting *merged* (in
 * practice, fully-overridden) array — i.e. usersc/includes/rate_limits.php's
 * values, since that's what a real request actually sees.
 */
#[Group('fast')]
final class RateLimitConfigTest extends TestCase
{
    public function testRegistrationRecoveryEmailActionIsConfigured(): void
    {
        $projectRoot = dirname(__DIR__, 3);

        $abs_us_root = $projectRoot . '/';
        $us_url_root = '';

        /** @var array<string, array<string, int>> $rateLimits */
        $rateLimits = [];
        require $projectRoot . '/users/includes/rate_limits.php';

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
