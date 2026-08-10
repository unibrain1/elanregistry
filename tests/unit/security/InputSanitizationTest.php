<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for Input::get() sanitization
 *
 * Exercises the real upstream UserSpice Input class (loaded by tests/bootstrap-unit.php)
 * against the request shapes the application's endpoints handle, documenting both the
 * values callers receive and the htmlspecialchars() sanitization Input::get() applies
 * on the way out.
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

    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        $this->originalPost = $_POST;
        $this->originalGet = $_GET;
        $this->originalSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;
        $_GET = $this->originalGet;
        // Token::generate()/check() write $_SESSION['token'] — restore it so a test
        // that clears the session token cannot leak into the next test.
        $_SESSION = $this->originalSession;
    }

    /**
     * Test that the app/api/cars/ endpoints properly sanitize search input
     */
    public function testCarsListEndpointSearchInputSanitization(): void
    {
        // Mock search data with potential XSS
        $mockSearchData = [
            'value' => '<script>alert("xss")</script>test'
        ];

        $_POST = [
            'csrf' => 'valid_token',
            'draw' => '1',
            'start' => '0',
            'length' => '10',
            'search' => $mockSearchData
        ];

        // Input::get() recurses into arrays and HTML-encodes every leaf value, so the
        // search term reaches the endpoint already neutralized.
        $searchData = Input::get('search');
        $this->assertIsArray($searchData);
        $this->assertArrayHasKey('value', $searchData);
        $this->assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;test',
            $searchData['value']
        );
        $this->assertStringNotContainsString('<script>', $searchData['value']);
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

    /**
     * Test XSS protection in various inputs
     *
     * Input::get() runs htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), so markup in the
     * request body is already inert by the time a caller sees it.
     */
    public function testXSSProtection(): void
    {
        $_POST = [
            'comments' => '<script>alert("xss")</script>Safe comment',
            'website' => 'javascript:alert("xss")',
            'color' => '<img src=x onerror=alert("xss")>Red'
        ];

        $comments = Input::get('comments');
        $website = Input::get('website');
        $color = Input::get('color');

        // Angle brackets and quotes come back HTML-encoded, never raw
        $this->assertStringNotContainsString('<script>', $comments);
        $this->assertStringNotContainsString('<', $comments);
        $this->assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;Safe comment', $comments);

        $this->assertStringNotContainsString('<img', $color);
        $this->assertSame('&lt;img src=x onerror=alert(&quot;xss&quot;)&gt;Red', $color);

        // htmlspecialchars() has no HTML-special character to encode in a javascript:
        // URL, so the scheme survives — protocol allowlisting, not encoding, is what
        // defends this field.
        $this->assertSame('javascript:alert(&quot;xss&quot;)', $website);
        $this->assertStringContainsString('javascript:', $website);
    }

    /**
     * Test SQL injection protection via Input::get()
     *
     * Input::get() is an output-encoder, not a SQL escaper: quotes come back as
     * &#039; while keywords pass through untouched. Prepared statements and integer
     * casts are what actually keep these payloads out of a query.
     */
    public function testSQLInjectionProtection(): void
    {
        $_POST = [
            'chassis' => "'; DROP TABLE cars; --",
            'year' => "1970' OR '1'='1",
            'user_id' => "1; DELETE FROM users; --"
        ];

        $chassis = Input::get('chassis');
        $year = Input::get('year');
        $userId = Input::get('user_id');

        // Single quotes are HTML-encoded; the SQL keywords themselves are unchanged
        $this->assertSame('&#039;; DROP TABLE cars; --', $chassis);
        $this->assertStringContainsString('DROP TABLE', $chassis);

        $this->assertSame('1970&#039; OR &#039;1&#039;=&#039;1', $year);
        $this->assertStringNotContainsString("'", $year);

        // No HTML-special characters here, so this one passes through verbatim
        $this->assertSame('1; DELETE FROM users; --', $userId);
        $this->assertStringContainsString('DELETE FROM', $userId);

        // But when cast to integer for user_id:
        $safeUserId = (int) $userId;
        $this->assertEquals(1, $safeUserId); // Only the integer part remains
    }

    /**
     * Test CSRF token validation works with Input::get()
     */
    public function testCSRFTokenValidation(): void
    {
        $validToken = Token::generate();

        $_POST = ['csrf' => $validToken];
        $this->assertTrue(Token::check(Input::get('csrf')));

        // Tampered token — a single hex character changed, so it still passes the
        // format guard and reaches the comparison, which checks the full token, not
        // just a prefix or substring.
        $lastChar = $validToken[strlen($validToken) - 1];
        $tamperedToken = substr($validToken, 0, -1) . ($lastChar === 'a' ? 'b' : 'a');
        $_POST = ['csrf' => $tamperedToken];
        $this->assertFalse(Token::check(Input::get('csrf')));

        // Missing token — rejected by the format guard before the session is consulted
        $_POST = [];
        $this->assertFalse(Token::check(Input::get('csrf')));

        // No token in the session at all: a well-formed token clears the format guard
        // and must still be rejected by Token::check()'s Session::exists() branch.
        unset($_SESSION['token']);
        $_POST = ['csrf' => bin2hex(random_bytes(32))];
        $this->assertFalse(Token::check(Input::get('csrf')));
    }
}
