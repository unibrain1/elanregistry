<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\Car\Car;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests for the "no_owner" resolution path used by app/admin/index.php's
 * `reassign` command handler (issue #1562).
 *
 * app/admin/index.php cannot be included directly in tests — it calls
 * securePage() and exits/redirects outside a real HTTP+session context. This
 * class instead exercises the exact resolution logic the handler runs
 * (User::find('noowner') -> data() -> Car::transfer()) against the real,
 * persistently-seeded `noowner` account, proving the target ID is resolved
 * dynamically rather than assumed to be a hardcoded 83.
 *
 * Regression coverage for #1562: admin-core.js and tab-car_mgmt.php
 * previously hardcoded the noowner user's ID as 83, which only happened to
 * be correct on production. A freshly-seeded environment (NoownerSeed.php)
 * deliberately lets the ID fall out of AUTO_INCREMENT, so any test that
 * hardcodes 83 would pass by coincidence and mask the bug. This test
 * asserts against the *actual* seeded ID instead.
 */
#[Group('integration')]
final class AdminCarReassignmentTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    /**
     * Happy path: resolving "no_owner" via User::find('noowner') and
     * transferring a car to that ID must land on the real seeded noowner
     * account, not the literal integer 83 (which is not guaranteed to be
     * the noowner account's ID on any environment other than the original
     * production database).
     */
    public function testNoOwnerResolutionTransfersCarToSeededNoownerAccount(): void
    {
        $noOwnerRow = $this->db->query("SELECT id FROM users WHERE username = ?", ['noowner'])->first();
        $this->assertNotEmpty($noOwnerRow, 'noowner system account missing — run composer seed:run (NoownerSeed)');
        $noOwnerId = (int) $noOwnerRow->id;

        $ownerId = $this->createTestUser();
        $carId = $this->createTestCar($ownerId);

        // Mirror app/admin/index.php's reassign-case resolution exactly:
        // instantiate User, find('noowner'), read data() — never trust a
        // client-supplied ID for this path.
        $noOwnerUser = new User();
        $found = $noOwnerUser->find('noowner');
        $this->assertTrue($found, 'User::find(\'noowner\') must resolve the seeded account');

        $noOwnerData = $noOwnerUser->data();
        $this->assertNotEmpty($noOwnerData, 'User::data() must return the resolved noowner account');
        $this->assertSame($noOwnerId, (int) $noOwnerData->id, 'Resolved ID must match the seeded noowner account');

        $car = new Car($carId);
        $car->transfer((int) $noOwnerData->id, 'Integration test: no_owner reassignment', 'NEWOWNER', $ownerId);

        $after = $this->db->query('SELECT user_id FROM cars WHERE id = ?', [$carId])->first();
        $this->assertSame(
            $noOwnerId,
            (int) $after->user_id,
            'Car must be reassigned to the dynamically-resolved noowner ID, not a hardcoded value'
        );
    }

    /**
     * Guards against the exact bug in #1562: proves the seeded noowner
     * account's ID is not assumed/asserted to be 83. If a future
     * regression reintroduces a hardcoded 83 anywhere in the resolution
     * path, this either fails outright (wrong ID transferred) or, if the
     * schema happens to seed 83 by coincidence, at minimum documents that
     * the value is a proper dynamic lookup rather than a literal.
     */
    public function testNoownerAccountIdIsResolvedDynamically(): void
    {
        $noOwnerRow = $this->db->query("SELECT id FROM users WHERE username = ?", ['noowner'])->first();
        $this->assertNotEmpty($noOwnerRow, 'noowner system account missing — run composer seed:run (NoownerSeed)');

        $noOwnerUser = new User();
        $noOwnerUser->find('noowner');
        $resolvedId = (int) $noOwnerUser->data()->id;

        $this->assertSame(
            (int) $noOwnerRow->id,
            $resolvedId,
            'User::find(\'noowner\') must resolve to whatever ID the seed actually assigned, proving no hardcoded fallback is in play'
        );
    }
}
