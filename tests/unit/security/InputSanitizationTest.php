<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Input::get() value retrieval across endpoint request shapes
 *
 * \Input here is the raw-passthrough stub declared in tests/bootstrap-unit.php, not the
 * upstream UserSpice class — users/ is .gitignore'd, so nothing under users/classes/ is
 * loadable in the unit tier. These tests therefore assert only how endpoints read and
 * coerce request values (keys, integer casts, array shapes), never sanitization.
 *
 * Real htmlspecialchars() sanitization and real CSRF crypto are verified in
 * tests/integration/TokenAndInputSecurityTest.php, where users/init.php loads the
 * genuine Input and Token classes.
 */
#[Group('fast')]
#[Group('unit')]
#[Group('security')]
class InputSanitizationTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalPost;

    /** @var array<string, mixed> */
    private array $originalGet;

    protected function setUp(): void
    {
        $this->originalPost = $_POST;
        $this->originalGet = $_GET;
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;
        $_GET = $this->originalGet;
    }

    /**
     * Test that the app/api/cars/ endpoints read DataTables search input as an array
     */
    public function testCarsListEndpointSearchInputSanitization(): void
    {
        $mockSearchData = [
            'value' => 'S4 coupe'
        ];

        $_POST = [
            'csrf' => 'valid_token',
            'draw' => '1',
            'start' => '0',
            'length' => '10',
            'search' => $mockSearchData
        ];

        // The endpoint receives DataTables' nested search array intact
        $searchData = Input::get('search');
        $this->assertIsArray($searchData);
        $this->assertArrayHasKey('value', $searchData);
        $this->assertSame('S4 coupe', $searchData['value']);
    }

    /**
     * Test chassis validation uses Input::get() instead of $_POST
     */
    public function testChassisCheckInputSanitization(): void
    {
        $_POST = [
            'csrf' => 'valid_token',
            'command' => 'chassis_check',
            'year' => '1969',
            'model' => 'S1|Standard|26R',
            'chassis' => 'TEST123'
        ];

        // Verify Input::get() returns expected values
        $this->assertEquals('chassis_check', Input::get('command'));
        $this->assertEquals('1969', Input::get('year'));
        $this->assertEquals('TEST123', Input::get('chassis'));

        // Verify model can be safely exploded
        $modelData = Input::get('model');
        list($series, $variant, $type) = explode('|', $modelData);
        $this->assertEquals('S1', $series);
        $this->assertEquals('Standard', $variant);
        $this->assertEquals('26R', $type);
    }

    /**
     * Test car editing uses Input::get() for all fields
     */
    public function testCarEditInputSanitization(): void
    {
        $_POST = [
            'csrf' => 'valid_token',
            'year' => '1970',
            'model' => 'S2',
            'chassis' => 'ABC123',
            'color' => 'Red',
            'engine' => 'TC123456',
            'purchasedate' => '2020-01-01',
            'solddate' => '2021-01-01',
            'website' => 'https://example.com',
            'comments' => 'Test comments',
            'filenames' => 'file1.jpg,file2.jpg'
        ];

        // Test all car edit fields use Input::get()
        $this->assertEquals('1970', Input::get('year'));
        $this->assertEquals('S2', Input::get('model'));
        $this->assertEquals('ABC123', Input::get('chassis'));
        $this->assertEquals('Red', Input::get('color'));
        $this->assertEquals('TC123456', Input::get('engine'));
        $this->assertEquals('2020-01-01', Input::get('purchasedate'));
        $this->assertEquals('2021-01-01', Input::get('solddate'));
        $this->assertEquals('https://example.com', Input::get('website'));
        $this->assertEquals('Test comments', Input::get('comments'));

        // Test filename array processing
        $filenames = Input::get('filenames');
        $requestedOrder = array_filter(explode(',', $filenames));
        $this->assertCount(2, $requestedOrder);
        $this->assertEquals(['file1.jpg', 'file2.jpg'], $requestedOrder);
    }

    /**
     * Test contact owner uses secure user lookup instead of unserialize
     */
    public function testContactOwnerSecureUserLookup(): void
    {
        $_POST = [
            'csrf' => 'valid_token',
            'action' => 'send_message',
            'from_user_id' => '123',
            'to_user_id' => '456',
            'message' => 'Test message'
        ];

        // Verify user IDs are properly cast to integers
        $fromUserId = (int) Input::get('from_user_id');
        $toUserId = (int) Input::get('to_user_id');

        $this->assertEquals(123, $fromUserId);
        $this->assertEquals(456, $toUserId);

        // Verify message is retrieved safely
        $this->assertEquals('Test message', Input::get('message'));
    }

    /**
     * Test manage cars uses Input::get() for all operations
     */
    public function testManageCarsInputSanitization(): void
    {
        $_POST = [
            'csrf' => 'valid_token',
            'command' => 'reassign',
            'user_id' => '789',
            'car_id' => '101'
        ];

        // Test reassign operation
        $command = Input::get('command');
        $this->assertEquals('reassign', $command);

        // Test integer casting for IDs
        $userId = (int) Input::get('user_id');
        $carId = (int) Input::get('car_id');

        $this->assertEquals(789, $userId);
        $this->assertEquals(101, $carId);
    }

    /**
     * Test merge operation uses Input::get()
     */
    public function testMergeOperationInputSanitization(): void
    {
        $_POST = [
            'csrf' => 'valid_token',
            'command' => 'merge',
            'cars' => ['car1', 'car2'],
            'reason' => ['duplicate']
        ];

        $command = Input::get('command');
        $cars = Input::get('cars');
        $reason = Input::get('reason');

        $this->assertEquals('merge', $command);
        $this->assertIsArray($cars);
        $this->assertIsArray($reason);
        $this->assertCount(2, $cars);
        $this->assertCount(1, $reason);
    }

    // XSS encoding, SQL-injection payload encoding, and CSRF token checking used to be
    // asserted here against a stub that does none of those things. They now live in
    // tests/integration/TokenAndInputSecurityTest.php, against the real Input and Token.
}
