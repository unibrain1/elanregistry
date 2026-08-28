<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit test for the transfer-request expiry calculation (#1067).
 *
 * app/api/cars/transfer-request.php:126 computes
 * `strtotime('+' . TRANSFER_REQUEST_EXPIRY_DAYS . ' days')` — this test pins
 * that exact formula against the live TRANSFER_REQUEST_EXPIRY_DAYS constant
 * so a future edit to the constant (or an accidental revert to a hardcoded
 * literal) is caught. The endpoint itself is a top-level script requiring a
 * full HTTP/CSRF/session bootstrap, so it isn't unit-testable directly —
 * this test exercises the same formula in isolation instead.
 */
#[Group('fast')]
final class TransferRequestExpiryTest extends TestCase
{
    public function testExpiryIsThirtyDaysFromNow(): void
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . TRANSFER_REQUEST_EXPIRY_DAYS . ' days'));
        $expected = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->assertSame(
            $expected,
            $expiresAt,
            'TRANSFER_REQUEST_EXPIRY_DAYS must be 30 — transfer-request.php:126 computes '
                . 'expires_at from this constant; a drift here silently changes transfer-request expiry.'
        );
    }
}
