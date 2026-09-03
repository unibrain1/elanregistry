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
 * be correct on production. A freshly-provisioned environment (the
 * RegisterNoownerAccount migration) deliberately lets the ID fall out of
 * AUTO_INCREMENT, so any test that hardcodes 83 would pass by coincidence
 * and mask the bug. This test asserts against the *actual* seeded ID
 * instead.
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
     * transferring a car to that ID must land on the real noowner
     * account, not the literal integer 83 (which is not guaranteed to be
     * the noowner account's ID on any environment other than the original
     * production database).
     */
    public function testNoOwnerResolutionTransfersCarToSeededNoownerAccount(): void
    {
        $noOwnerRow = $this->db->query("SELECT id FROM users WHERE username = ?", ['noowner'])->first();
        $this->assertNotEmpty($noOwnerRow, 'noowner system account missing — run composer migrate (RegisterNoownerAccount)');
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
     * Not-found path: app/admin/index.php gates its "noowner account
     * missing" error on User::find('noowner')'s boolean return. find()
     * only ever assigns $_data inside its "row found" branch (users/classes/user.php),
     * so on a failed lookup $_data is left at its uninitialized default
     * (null) and data() returns null. This proves find()'s return value —
     * not a truthiness check on data() — is the correct not-found signal to
     * key error handling off of.
     */
    public function testFindReturnsFalseForNonexistentUsername(): void
    {
        $missingUser = new User();
        $found = $missingUser->find('this-username-does-not-exist-1562');
        $this->assertFalse($found, 'User::find() must return false for a nonexistent username');
        $this->assertEmpty($missingUser->data(), 'User::data() must be empty/null after a failed find()');
    }

    /**
     * Reassigning a sold car to the seeded noowner account is not a change
     * of owner (issue #1878) — CarAdministrationService::transfer() must
     * leave solddate untouched when the target username is 'noowner',
     * unlike a transfer to a real owner, which clears it.
     */
    public function testReassignToNoownerPreservesSoldDate(): void
    {
        $noOwnerRow = $this->db->query("SELECT id FROM users WHERE username = ?", ['noowner'])->first();
        $this->assertNotEmpty($noOwnerRow, 'noowner system account missing — run composer migrate (RegisterNoownerAccount)');
        $noOwnerId = (int) $noOwnerRow->id;

        $ownerId = $this->createTestUser();
        $carId = $this->createTestCar($ownerId, ['solddate' => '2020-01-01']);

        $noOwnerUser = new User();
        $found = $noOwnerUser->find('noowner');
        $this->assertTrue($found, 'User::find(\'noowner\') must resolve the seeded account');

        $noOwnerData = $noOwnerUser->data();
        $this->assertNotEmpty($noOwnerData, 'User::data() must return the resolved noowner account');

        $car = new Car($carId);
        $car->transfer((int) $noOwnerData->id, 'Integration test: noowner reassignment preserves solddate', 'NEWOWNER', $ownerId);

        $after = $this->db->query('SELECT solddate FROM cars WHERE id = ?', [$carId])->first();
        $this->assertSame(
            '2020-01-01',
            $after->solddate,
            'Reassigning to the seeded noowner account must not clear solddate — it is not a change of owner'
        );
    }
}
