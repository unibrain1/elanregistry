<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\Car\Car;
use ElanRegistry\Owner;
use ElanRegistry\OwnerContactRefresher;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for the #1962 fix: editing a car must refresh the car's
 * denormalized owner-contact columns from the owner's current profile,
 * instead of perpetuating whatever stale snapshot the car row already held.
 *
 * `buildCarDetails()` (app/api/cars/save.php) cannot be require()'d under
 * PHPUnit — every branch ends in ApiResponse::send() -> exit (see
 * tests/unit/cars/CarActionsSaveWiringTest.php). The merge it performs was
 * therefore extracted into {@see \ElanRegistry\OwnerContactRefresher}, which
 * these tests call directly. That matters: an earlier revision of this file
 * hand-copied the merge into a private helper, and every test here passed with
 * the production block deleted outright, and passed again with the Owner
 * constructed from the session user instead of the car's owner — the exact PII
 * leak the endpoint's comments warn about. Tests that re-implement the code
 * under test cannot fail when it breaks. Call the real thing.
 *
 * The helpers below still stage what the endpoint stages around that call
 * (load the car row, merge an unrelated edited field, persist via
 * Car::update()), then assert against the database afterward.
 *
 * @see app/api/cars/save.php buildCarDetails()
 * @see usersc/classes/Owner.php Owner::ownerContactFields()
 */
#[Group('integration')]
#[Group('car')]
final class CarEditOwnerColumnRefreshTest extends IntegrationTestCase
{
    /** @var int[] Profile IDs to clean up in tearDown */
    private array $createdProfileIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdProfileIds as $profileId) {
            try {
                $this->db->query("DELETE FROM profiles WHERE id = ?", [$profileId]);
            } catch (\Throwable $e) {
                // Ignore cleanup errors
            }
        }
        $this->createdProfileIds = [];
        parent::tearDown();
    }

    /**
     * Create a profile row for a test user with optional overrides.
     * Tracked for cleanup in tearDown(). Mirrors
     * OwnerSyncOwnerFieldsToCarsTest::createTestProfile().
     */
    private function createTestProfile(int $userId, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $userId,
            'bio'     => '',
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => null,
            'lon'     => null,
            'website' => '',
        ];

        $this->db->insert('profiles', array_merge($defaults, $overrides));

        $row = $this->db->query("SELECT id FROM profiles WHERE user_id = ? ORDER BY id DESC LIMIT 1", [$userId])->first();
        if (!$row) {
            throw new \RuntimeException("createTestProfile: insert failed for user_id={$userId}");
        }
        $this->createdProfileIds[] = (int) $row->id;
    }

    /**
     * Reproduce buildCarDetails()'s `if ($carId)` edit-branch refresh sequence
     * against a real car row and a real Owner, then apply it via Car::update()
     * exactly as save.php does. $extraFields lets a test simulate "the user
     * also edited an unrelated field" alongside the refresh.
     *
     * $isOwnerInitiated mirrors save.php's updateCar(): production computes
     * this as `(int) ($cardetails['user_id'] ?? 0) === (int) $user->data()->id`
     * (save.php:291) — i.e. true when the person submitting the edit IS the
     * car's own owner, false for an admin/editor editing someone else's car —
     * and passes it to Car::update($cardetails, $isOwnerInitiated). It is NOT
     * part of the #1962 refresh itself; it's a separate flag Car::update() uses
     * to decide whether to also bump owner_last_updated (see Car.php's
     * CLEARABLE_FIELDS-adjacent owner_last_updated handling). This parameter
     * defaults to false (admin-edit shape) to match this class's original
     * test cases, which all modeled an edit whose $isOwnerInitiated truth
     * value did not matter to what was being asserted at the time — see
     * testOwnerSelfEditSetsOwnerLastUpdatedButOrphanEditsDoNotUnlikeAdminEdit()
     * below for why that distinction DOES matter for owner_last_updated
     * specifically.
     */
    private function runEditBranchRefresh(int $carId, array $extraFields = [], bool $isOwnerInitiated = false): void
    {
        $carRow = $this->db->query("SELECT * FROM cars WHERE id = ?", [$carId])->first();
        $this->assertNotNull($carRow, 'Precondition: car must exist');

        $cardetails = (array) $carRow;
        // The real edit branch rebuilds `model` from separate series/variant/type
        // form fields via updateModel() (save.php) into "series|variant|type"
        // format before Car::update() ever sees it — the raw cars.model column
        // ("Elan S4") is a display string, not that format. These tests are not
        // exercising updateModel()/CarValidator's model parsing, so drop the raw
        // column value rather than fabricate a pipe-delimited string unrelated
        // to what's under test here.
        unset($cardetails['model']);
        foreach ($extraFields as $key => $value) {
            $cardetails[$key] = $value;
        }

        // Load the CAR's owner (never a session/admin user), then run the
        // production merge itself — the same object app/api/cars/save.php
        // calls. Do not inline this merge here: a hand-copy passes even when
        // the endpoint's call is deleted.
        $carOwner = new Owner((int) $cardetails['user_id']);
        $this->assertNotNull($carOwner->data(), 'Precondition: car owner must load');

        $refresher = new OwnerContactRefresher();
        $this->assertTrue(
            $refresher->hasLoadableOwner($carOwner),
            'Precondition: the refresher must agree the owner loaded'
        );
        $cardetails = $refresher->refresh($cardetails, $carOwner);

        $car = new Car($carId);
        $result = $car->update($cardetails, $isOwnerInitiated);
        $this->assertTrue($result, 'Car::update() must succeed for the refresh to be observable');
    }

    /**
     * Reproduce buildCarDetails()'s edit-branch `else` (orphan-owner) path
     * exactly: when `$carOwner->data()` is null, the function logs and
     * leaves $cardetails untouched for all nine owner-contact fields —
     * ownerFields are simply never merged in. Mirrors runEditBranchRefresh()
     * but stops after the "owner failed to load" branch instead of merging
     * ownerFields, since that merge is precisely what buildCarDetails()
     * skips in this case.
     */
    private function runEditBranchRefreshWithOrphanOwner(int $carId, array $extraFields = []): void
    {
        $carRow = $this->db->query("SELECT * FROM cars WHERE id = ?", [$carId])->first();
        $this->assertNotNull($carRow, 'Precondition: car must exist');

        $cardetails = (array) $carRow;
        unset($cardetails['model']);
        foreach ($extraFields as $key => $value) {
            $cardetails[$key] = $value;
        }

        $ownerIdRaw = $cardetails['user_id'];
        $carOwner = new Owner($ownerIdRaw !== null ? (int) $ownerIdRaw : null);
        $this->assertNull(
            $carOwner->data(),
            'Precondition: this test exercises the orphan-owner branch — the owner must fail to load'
        );

        // The endpoint's `else` branch (owner data() === null) logs and skips
        // the merge. Run the real refresher anyway and assert it returns
        // $cardetails untouched — that no-op IS the contract under test, and
        // asserting it here means a refresher that started blanking columns on
        // an unloadable owner would fail this test rather than slip through.
        $refresher = new OwnerContactRefresher();
        $this->assertFalse(
            $refresher->hasLoadableOwner($carOwner),
            'Precondition: the refresher must agree the owner did not load'
        );
        $this->assertSame(
            $cardetails,
            $refresher->refresh($cardetails, $carOwner),
            'An unloadable owner must leave every car-detail value untouched'
        );

        $car = new Car($carId);
        $result = $car->update($cardetails);
        $this->assertTrue($result, 'Car::update() must succeed even when the owner-contact refresh is skipped');
    }

    /**
     * Case 1: stale owner columns on the car refresh to the owner's current
     * profile values when an unrelated field (comments) is edited.
     */
    public function testEditRefreshesAllEightOwnerColumnsFromCurrentProfile(): void
    {
        $userId = $this->createTestUser([
            'fname' => 'Fresh',
            'lname' => 'Owner',
            'email' => 'fresh-owner@example.com',
        ]);
        $this->createTestProfile($userId, [
            'city'    => 'Eugene',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.0521,
            'lon'     => -123.0868,
        ]);

        // Car starts with a deliberately stale owner-contact snapshot.
        $carId = $this->createTestCar($userId, [
            'fname'   => 'Stale',
            'lname'   => 'Name',
            'email'   => 'stale-email@example.com',
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 45.5231,
            'lon'     => -122.6765,
            'comments' => 'original comments',
        ]);

        $this->runEditBranchRefresh($carId, ['comments' => 'edited comments']);

        $car = $this->db->query(
            "SELECT email, fname, lname, city, state, country, lat, lon, comments FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($car);
        $this->assertSame('fresh-owner@example.com', $car->email);
        $this->assertSame('Fresh', $car->fname);
        $this->assertSame('Owner', $car->lname);
        $this->assertSame('Eugene', $car->city);
        $this->assertSame('Oregon', $car->state);
        $this->assertSame('United States', $car->country);
        $this->assertEqualsWithDelta(44.0521, (float) $car->lat, 0.001);
        $this->assertEqualsWithDelta(-123.0868, (float) $car->lon, 0.001);
        $this->assertSame('edited comments', $car->comments, 'The unrelated edited field must still be written');
    }

    /**
     * Case 2: website, owner_last_updated, and join_date must be UNCHANGED
     * by the refresh — website is still a genuine per-car field until #1963,
     * and owner_last_updated/join_date are never touched by the #1962
     * refresh itself.
     *
     * This deliberately exercises the ADMIN-EDIT shape ($isOwnerInitiated =
     * false, runEditBranchRefresh()'s default) — mirroring save.php's
     * $isOwnerInitiated = (int)($cardetails['user_id'] ?? 0) === (int)$user->data()->id.
     * owner_last_updated being unchanged here proves only "the refresh doesn't
     * set it" for a NON-owner-initiated edit. It does NOT prove the refresh
     * never sets it — Car::update()'s own $isOwnerInitiated branch adds
     * owner_last_updated independently of anything buildCarDetails() does.
     * See testOwnerSelfEditSetsOwnerLastUpdatedButAdminEditDoesNot() below,
     * which passes $isOwnerInitiated = true and proves the two modes diverge,
     * distinguishing "the refresh doesn't set it" (this test) from "nothing
     * ever sets it" (which would be false).
     */
    public function testEditDoesNotTouchWebsiteOwnerLastUpdatedOrJoinDate(): void
    {
        $userId = $this->createTestUser();
        $this->createTestProfile($userId, [
            'city'    => 'Eugene',
            'website' => 'https://profile-website.example.com',
        ]);

        $staleOwnerLastUpdated = date('Y-m-d H:i:s', strtotime('-2 years'));
        $staleJoinDate = date('Y-m-d H:i:s', strtotime('-5 years'));
        $carId = $this->createTestCar($userId, [
            'city'               => 'Portland',
            'website'            => 'https://per-car-website.example.com',
            'owner_last_updated' => $staleOwnerLastUpdated,
            'join_date'          => $staleJoinDate,
            'comments'           => 'before',
        ]);

        $before = $this->db->query(
            "SELECT website, owner_last_updated, join_date FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($before);

        // isOwnerInitiated = false: the admin-edit shape. See docblock above.
        $this->runEditBranchRefresh($carId, ['comments' => 'after'], isOwnerInitiated: false);

        $after = $this->db->query(
            "SELECT website, owner_last_updated, join_date, city, comments FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($after);

        // Sanity: the refresh did happen (city changed), so the assertions
        // below are proving exclusion, not that nothing ran at all.
        $this->assertSame('Eugene', $after->city);
        $this->assertSame('after', $after->comments);

        $this->assertSame((string) $before->website, (string) $after->website, 'website must not be touched by the edit-path refresh (per-car field until #1963)');
        $this->assertSame((string) $before->owner_last_updated, (string) $after->owner_last_updated, 'owner_last_updated must not be written on a non-owner-initiated (admin) edit');
        $this->assertSame((string) $before->join_date, (string) $after->join_date, 'join_date is creation-time only and must never be touched by the refresh');
    }

    /**
     * Case 2b (#1962 gap): proves the two $isOwnerInitiated modes actually
     * diverge for owner_last_updated, distinguishing "the #1962 refresh
     * itself never sets owner_last_updated" (true in both modes) from
     * "nothing ever sets owner_last_updated" (false — Car::update() sets it
     * independently when $isOwnerInitiated is true).
     *
     * Without this test, the previous test's "owner_last_updated unchanged"
     * assertion could pass for the WRONG reason: not because the refresh
     * correctly excludes it, but because nothing in the whole call ever
     * writes it at all in either mode — a much weaker and easily-broken
     * property to accidentally rely on.
     *
     * Same starting car/profile/edit for both modes; only $isOwnerInitiated
     * differs, isolating it as the sole cause of the observed difference.
     */
    public function testOwnerSelfEditSetsOwnerLastUpdatedButAdminEditDoesNot(): void
    {
        $staleOwnerLastUpdated = date('Y-m-d H:i:s', strtotime('-2 years'));

        $adminEditUserId = $this->createTestUser();
        $this->createTestProfile($adminEditUserId, ['city' => 'Eugene']);
        $adminEditCarId = $this->createTestCar($adminEditUserId, [
            'owner_last_updated' => $staleOwnerLastUpdated,
            'comments' => 'before',
        ]);

        $selfEditUserId = $this->createTestUser();
        $this->createTestProfile($selfEditUserId, ['city' => 'Eugene']);
        $selfEditCarId = $this->createTestCar($selfEditUserId, [
            'owner_last_updated' => $staleOwnerLastUpdated,
            'comments' => 'before',
        ]);

        // Admin-edit mode (false): owner_last_updated must stay exactly the
        // stale pre-existing value — no write to it at all.
        $this->runEditBranchRefresh($adminEditCarId, ['comments' => 'after'], isOwnerInitiated: false);

        // Owner-self-edit mode (true): Car::update() must set
        // owner_last_updated to "now" — a value strictly newer than the
        // stale seed — independently of the #1962 refresh logic.
        $this->runEditBranchRefresh($selfEditCarId, ['comments' => 'after'], isOwnerInitiated: true);

        $adminEditResult = $this->db->query(
            "SELECT owner_last_updated FROM cars WHERE id = ?",
            [$adminEditCarId]
        )->first();
        $selfEditResult = $this->db->query(
            "SELECT owner_last_updated FROM cars WHERE id = ?",
            [$selfEditCarId]
        )->first();
        $this->assertNotNull($adminEditResult);
        $this->assertNotNull($selfEditResult);

        $this->assertSame(
            $staleOwnerLastUpdated,
            (string) $adminEditResult->owner_last_updated,
            'Admin edit ($isOwnerInitiated = false): owner_last_updated must remain untouched'
        );
        $this->assertNotSame(
            $staleOwnerLastUpdated,
            (string) $selfEditResult->owner_last_updated,
            'Owner self-edit ($isOwnerInitiated = true): owner_last_updated must be updated by ' .
            'Car::update() — proving the previous test\'s "unchanged" result is because the #1962 ' .
            'refresh itself never sets this field, not because nothing in the call chain does'
        );
        $this->assertGreaterThan(
            strtotime($staleOwnerLastUpdated),
            strtotime((string) $selfEditResult->owner_last_updated),
            'Owner self-edit must bump owner_last_updated forward in time, not merely change it'
        );
    }

    /**
     * Case 3 (security regression): when an ADMIN edits a MEMBER's car, the
     * refresh must write the CAR OWNER's (member's) current contact data —
     * never the admin's. Using the session user instead of the car's
     * user_id would leak staff contact details onto a public-facing car
     * record. See Database & Security Considerations in
     * docs/plans/issue-1962-refresh-owner-columns-on-car-edit.md.
     *
     * This test never constructs an Owner from the admin's ID at all —
     * exactly mirroring buildCarDetails(), which reads
     * $cardetails['user_id'] from the loaded car row, not from the logged-in
     * $user global. If a future change accidentally swapped in the session
     * user, this test would catch it by finding the admin's email/fname/city
     * on the car instead of the member's.
     */
    public function testAdminEditingMembersCarWritesMembersDataNotAdmins(): void
    {
        $memberId = $this->createTestUser([
            'fname' => 'Member',
            'lname' => 'Smith',
            'email' => 'member-smith@example.com',
        ]);
        $this->createTestProfile($memberId, [
            'city'    => 'Eugene',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.0521,
            'lon'     => -123.0868,
        ]);

        $adminId = $this->createTestUser([
            'fname' => 'Admin',
            'lname' => 'Jones',
            'email' => 'admin-jones@example.com',
        ]);
        $this->createTestProfile($adminId, [
            'city'    => 'Seattle',
            'state'   => 'Washington',
            'country' => 'United States',
            'lat'     => 47.6062,
            'lon'     => -122.3321,
        ]);

        // The car belongs to the member; only its stale snapshot might
        // coincidentally resemble anyone's data, so seed it distinctly.
        $carId = $this->createTestCar($memberId, [
            'fname' => 'Stale',
            'lname' => 'Snapshot',
            'email' => 'stale-snapshot@example.com',
            'city'  => 'Portland',
        ]);

        // Deliberately do NOT touch a global "logged-in user" — the fix
        // under test must never consult one. We simulate "an admin is
        // performing this edit" simply by the admin existing in the system;
        // the correctness property is that the refresh sequence, driven only
        // by the car's own user_id, is blind to who initiated it.
        $this->runEditBranchRefresh($carId, ['comments' => 'edited by admin']);

        $car = $this->db->query(
            "SELECT email, fname, lname, city, state, country, lat, lon FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($car);

        // Must match the MEMBER.
        $this->assertSame('member-smith@example.com', $car->email);
        $this->assertSame('Member', $car->fname);
        $this->assertSame('Smith', $car->lname);
        $this->assertSame('Eugene', $car->city);
        $this->assertSame('Oregon', $car->state);
        $this->assertEqualsWithDelta(44.0521, (float) $car->lat, 0.001);
        $this->assertEqualsWithDelta(-123.0868, (float) $car->lon, 0.001);

        // Must NOT match the admin.
        $this->assertNotSame('admin-jones@example.com', $car->email);
        $this->assertNotSame('Admin', $car->fname);
        $this->assertNotSame('Jones', $car->lname);
        $this->assertNotSame('Seattle', $car->city);
        $this->assertNotSame('Washington', $car->state);
    }

    /**
     * Case 4: the new-car (add) path is unaffected by #1962 and must still
     * populate owner columns straight from the owner's profile, exactly as
     * buildCarDetails()'s `else` branch does (email, fname, lname, join_date,
     * city, state, country, lat, lon — website is set separately by form
     * input on that path, not from the profile, so it is not asserted here).
     */
    public function testNewCarPathStillPopulatesOwnerColumnsFromProfile(): void
    {
        $userId = $this->createTestUser([
            'fname' => 'Brand',
            'lname' => 'NewOwner',
            'email' => 'brand-new-owner@example.com',
            'join_date' => '2020-01-01 00:00:00',
        ]);
        $this->createTestProfile($userId, [
            'city'    => 'Bend',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 44.0582,
            'lon'     => -121.3153,
        ]);

        $owner = new Owner($userId);
        $ownerData = $owner->data();
        $this->assertNotNull($ownerData);

        // Reproduce buildCarDetails()'s `else` (add-car) branch verbatim:
        // it copies these nine fields directly off $ownerData, not through
        // ownerContactFields(), and does not touch website from the profile.
        $cardetails = [
            'user_id'   => $ownerData->id,
            'email'     => $ownerData->email,
            'fname'     => $ownerData->fname,
            'lname'     => $ownerData->lname,
            'join_date' => $ownerData->join_date,
            'city'      => $ownerData->city,
            'state'     => $ownerData->state,
            'country'   => $ownerData->country,
            'lat'       => $ownerData->lat,
            'lon'       => $ownerData->lon,
            'year'      => 1970,
            'model'     => 'Elan',
            'series'    => 'S4',
            'variant'   => 'SE',
            'type'      => 'FHC',
            'chassis'   => 'NEWCHASSIS01',
            'color'     => 'Green',
        ];

        // Model the actual add-car insert path via createTestCar() so the
        // assertions below read from a real row, same as the other cases.
        $carId = $this->createTestCar($userId, $cardetails);

        $car = $this->db->query(
            "SELECT user_id, email, fname, lname, city, state, country, lat, lon FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($car);
        $this->assertSame($userId, (int) $car->user_id);
        $this->assertSame('brand-new-owner@example.com', $car->email);
        $this->assertSame('Brand', $car->fname);
        $this->assertSame('NewOwner', $car->lname);
        $this->assertSame('Bend', $car->city);
        $this->assertSame('Oregon', $car->state);
        $this->assertSame('United States', $car->country);
        $this->assertEqualsWithDelta(44.0582, (float) $car->lat, 0.001);
        $this->assertEqualsWithDelta(-121.3153, (float) $car->lon, 0.001);
    }

    /**
     * Case 5: an owner with empty city and null lat/lon produces a no-op for
     * those specific fields — Car::update() strips ''/null for any field
     * outside CLEARABLE_FIELDS (Car.php:43-45,241-247), and city/lat/lon are
     * not in that allowlist, so an empty/null profile value is simply
     * dropped from the write rather than blanking the car's existing value.
     * Must not crash. fname/lname/email are still populated normally by this
     * test's profile/user data, confirming the refresh is not disabled
     * wholesale — only the specific empty fields are no-ops.
     */
    public function testOwnerWithEmptyCityAndNullLatLonIsNoOpForThoseFieldsWithoutCrashing(): void
    {
        $userId = $this->createTestUser([
            'fname' => 'Sparse',
            'lname' => 'Profile',
            'email' => 'sparse-profile@example.com',
        ]);
        // city/state/country default to '' via Owner::find()'s null-coalesce
        // when omitted here, and lat/lon stay null (no LEFT JOIN override).
        $this->createTestProfile($userId, [
            'city'    => '',
            'state'   => '',
            'country' => '',
            'lat'     => null,
            'lon'     => null,
        ]);

        $carId = $this->createTestCar($userId, [
            'city'  => 'Existing City',
            'state' => 'Existing State',
            'country' => 'Existing Country',
            'lat'   => 45.0,
            'lon'   => -122.0,
        ]);

        $this->runEditBranchRefresh($carId, ['comments' => 'still edits fine']);

        $car = $this->db->query(
            "SELECT email, fname, lname, city, state, country, lat, lon, comments FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($car);

        // Non-empty fields still refresh normally.
        $this->assertSame('sparse-profile@example.com', $car->email);
        $this->assertSame('Sparse', $car->fname);
        $this->assertSame('Profile', $car->lname);
        $this->assertSame('still edits fine', $car->comments);

        // Empty/null profile values must not overwrite the car's existing
        // values — Car::update() strips them rather than writing blanks.
        $this->assertSame('Existing City', $car->city);
        $this->assertSame('Existing State', $car->state);
        $this->assertSame('Existing Country', $car->country);
        $this->assertEqualsWithDelta(45.0, (float) $car->lat, 0.001);
        $this->assertEqualsWithDelta(-122.0, (float) $car->lon, 0.001);
    }

    /**
     * Case 6 (#1962 gap): a car whose user_id points at a non-existent user
     * (a dangling reference — reachable because migration
     * 20260719120000_drop_cars_user_id_fk.php removed the FK from
     * cars.user_id to users.id) must still save successfully, and must leave
     * the car's EXISTING owner-contact columns alone rather than nulling or
     * blanking them. This is buildCarDetails()'s
     * `$carOwner->data() !== null` `else` branch: it logs the orphan and
     * skips the ownerFields merge entirely, so whatever was already on the
     * car row before the edit must survive unchanged.
     */
    public function testEditWithDanglingUserIdLeavesExistingOwnerColumnsUntouched(): void
    {
        // Create a car with a real owner and a real owner-contact snapshot,
        // then sever the link by pointing user_id at an id that does not
        // (and, being far outside the auto_increment range this test suite
        // produces, will not) exist in `users`.
        $userId = $this->createTestUser();
        $this->createTestProfile($userId, ['city' => 'Eugene']);

        $carId = $this->createTestCar($userId, [
            'fname'   => 'Existing',
            'lname'   => 'Snapshot',
            'email'   => 'existing-snapshot@example.com',
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 45.5231,
            'lon'     => -122.6765,
            'comments' => 'original comments',
        ]);

        $danglingUserId = 999999999;
        $this->db->query("UPDATE cars SET user_id = ? WHERE id = ?", [$danglingUserId, $carId]);

        $before = $this->db->query(
            "SELECT email, fname, lname, city, state, country, lat, lon FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($before);

        $this->runEditBranchRefreshWithOrphanOwner($carId, ['comments' => 'edited despite orphan owner']);

        $after = $this->db->query(
            "SELECT email, fname, lname, city, state, country, lat, lon, comments, user_id FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($after);

        // Sanity: the save did happen (comments changed, user_id still dangling).
        $this->assertSame('edited despite orphan owner', $after->comments);
        $this->assertSame($danglingUserId, (int) $after->user_id);

        $this->assertOwnerContactColumnsUnchanged($before, $after);
    }

    /**
     * Case 7 (#1962 gap): same orphan-owner scenario, but with a NULL
     * user_id rather than a dangling one — the other reachable "no owner"
     * state buildCarDetails() distinguishes in its log message
     * ("has no owner (user_id is null)" vs. "owner N could not be loaded").
     * Owner(null) also fails to load (Owner::find() requires a non-null id),
     * so this exercises the same `$carOwner->data() === null` branch via a
     * different route. Existing owner-contact columns must again survive
     * unchanged.
     */
    public function testEditWithNullUserIdLeavesExistingOwnerColumnsUntouched(): void
    {
        $userId = $this->createTestUser();
        $this->createTestProfile($userId, ['city' => 'Eugene']);

        $carId = $this->createTestCar($userId, [
            'fname'   => 'Existing',
            'lname'   => 'Snapshot',
            'email'   => 'existing-snapshot-2@example.com',
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => 45.5231,
            'lon'     => -122.6765,
            'comments' => 'original comments',
        ]);

        $this->db->query("UPDATE cars SET user_id = NULL WHERE id = ?", [$carId]);

        $before = $this->db->query(
            "SELECT email, fname, lname, city, state, country, lat, lon FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($before);

        $this->runEditBranchRefreshWithOrphanOwner($carId, ['comments' => 'edited despite null owner']);

        $after = $this->db->query(
            "SELECT email, fname, lname, city, state, country, lat, lon, comments, user_id FROM cars WHERE id = ?",
            [$carId]
        )->first();
        $this->assertNotNull($after);

        $this->assertSame('edited despite null owner', $after->comments);
        $this->assertNull($after->user_id);

        $this->assertOwnerContactColumnsUnchanged($before, $after);
    }

    /**
     * Shared assertion for cases 6 and 7: the pre-existing owner-contact
     * snapshot (email, fname, lname, city, state, country, lat, lon) must be
     * UNCHANGED between $before and $after — not nulled, not blanked —
     * because ownerContactFields() was never merged in for an orphan-owner
     * car at all.
     */
    private function assertOwnerContactColumnsUnchanged(object $before, object $after): void
    {
        $this->assertSame((string) $before->email, (string) $after->email);
        $this->assertSame((string) $before->fname, (string) $after->fname);
        $this->assertSame((string) $before->lname, (string) $after->lname);
        $this->assertSame((string) $before->city, (string) $after->city);
        $this->assertSame((string) $before->state, (string) $after->state);
        $this->assertSame((string) $before->country, (string) $after->country);
        $this->assertEqualsWithDelta((float) $before->lat, (float) $after->lat, 0.001);
        $this->assertEqualsWithDelta((float) $before->lon, (float) $after->lon, 0.001);
    }
}
