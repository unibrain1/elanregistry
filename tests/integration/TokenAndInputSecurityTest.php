<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Real CSRF and input-sanitization behavior of the upstream UserSpice Token/Input classes.
 *
 * These assertions live in the integration tier because they cannot live anywhere else:
 * the whole users/ tree is .gitignore'd (`users/**`), so users/classes/Token.php and
 * users/classes/Input.php are absent from every CI checkout and from `composer install`.
 * tests/bootstrap-unit.php therefore declares raw stubs for both, and the unit tier can
 * only ever assert those stubs' own contract. This is a file-availability constraint, not
 * a runtime one — Token and Input touch nothing but superglobals.
 *
 * tests/bootstrap-integration.php loads the full framework via users/init.php, so the
 * classes exercised here are the genuine ones the application runs against in production.
 */
#[Group('integration')]
#[Group('security')]
final class TokenAndInputSecurityTest extends IntegrationTestCase
{
    /** @var array<string, mixed> */
    private array $originalPost;

    /** @var array<string, mixed> */
    private array $originalGet;

    /** @var array<string, mixed> */
    private array $originalSession;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        // IntegrationTestCase manages database fixtures, not superglobals, so this test
        // saves and restores the request/session state it manipulates itself.
        $this->originalPost = $_POST;
        $this->originalGet = $_GET;
        $this->originalSession = $_SESSION ?? [];
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;
        $_GET = $this->originalGet;
        // Token::generate()/check() write $_SESSION['token'] — restore it so a test that
        // clears the session token cannot leak into the next test.
        $_SESSION = $this->originalSession;

        parent::tearDown();
    }

    /**
     * A freshly generated token is accepted by Token::check().
     */
    public function testGeneratedTokenIsAccepted(): void
    {
        $token = Token::generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertTrue(Token::check($token));
    }

    /**
     * A single-character mutation is rejected.
     *
     * The substituted character stays hex so the tampered token still clears
     * Token::check()'s format guard and actually reaches the hash_equals() comparison —
     * proving the comparison covers the full token, not a prefix or substring.
     */
    public function testSingleCharacterTamperIsRejected(): void
    {
        $token = Token::generate();

        $lastChar = $token[strlen($token) - 1];
        $tampered = substr($token, 0, -1) . ($lastChar === 'a' ? 'b' : 'a');

        $this->assertNotSame($token, $tampered);
        $this->assertSame(strlen($token), strlen($tampered));
        $this->assertFalse(Token::check($tampered));
    }

    /**
     * A well-formed token is rejected when no token exists in the session.
     *
     * Exercises Token::check()'s Session::exists() branch: format alone must never be
     * enough to pass CSRF validation.
     */
    public function testWellFormedTokenIsRejectedWhenSessionHasNoToken(): void
    {
        unset($_SESSION['token']);

        $this->assertFalse(Token::check(bin2hex(random_bytes(32))));
    }

    /**
     * Missing, empty, and malformed tokens are rejected by the format guard.
     */
    public function testMissingOrMalformedTokenIsRejected(): void
    {
        Token::generate();

        $this->assertFalse(Token::check(null));
        $this->assertFalse(Token::check(''));
        $this->assertFalse(Token::check('not-a-token'));
        // Right length, wrong alphabet — fails ctype_xdigit()
        $this->assertFalse(Token::check(str_repeat('z', 64)));
    }

    /**
     * Input::get() HTML-encodes XSS payloads on the way out.
     *
     * Real htmlspecialchars($value, ENT_QUOTES, 'UTF-8'), so markup in the request body
     * is already inert by the time a caller sees it.
     */
    public function testInputGetEncodesXssPayloads(): void
    {
        $_POST = [
            'comments' => '<script>alert("xss")</script>Safe comment',
            'website' => 'javascript:alert("xss")',
            'color' => '<img src=x onerror=alert("xss")>Red',
        ];

        $comments = Input::get('comments');
        $website = Input::get('website');
        $color = Input::get('color');

        // Angle brackets and quotes come back HTML-encoded, never raw
        $this->assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;Safe comment', $comments);
        $this->assertStringNotContainsString('<', $comments);

        $this->assertSame('&lt;img src=x onerror=alert(&quot;xss&quot;)&gt;Red', $color);
        $this->assertStringNotContainsString('<img', $color);

        // htmlspecialchars() has no HTML-special character to encode in a javascript:
        // URL, so the scheme survives — protocol allowlisting, not encoding, is what
        // defends this field.
        $this->assertSame('javascript:alert(&quot;xss&quot;)', $website);
        $this->assertStringContainsString('javascript:', $website);
    }

    /**
     * Input::get() encodes quotes in SQL-injection payloads but is not a SQL escaper.
     *
     * Quotes come back as &#039; while keywords pass through untouched. Prepared
     * statements and integer casts are what actually keep these payloads out of a query.
     */
    public function testInputGetEncodesQuotesButNotSqlKeywords(): void
    {
        $_POST = [
            'chassis' => "'; DROP TABLE cars; --",
            'year' => "1970' OR '1'='1",
            'user_id' => "1; DELETE FROM users; --",
        ];

        $chassis = Input::get('chassis');
        $year = Input::get('year');
        $userId = Input::get('user_id');

        // Single quotes are HTML-encoded; the SQL keywords themselves are unchanged
        $this->assertSame('&#039;; DROP TABLE cars; --', $chassis);
        $this->assertStringContainsString('DROP TABLE', $chassis);

        $this->assertSame('1970&#039; OR &#039;1&#039;=&#039;1', $year);
        $this->assertStringNotContainsString("'", $year);

        // No HTML-special characters here, so this one passes through verbatim —
        // Input::get() is not what keeps this payload out of a query; the integer cast
        // at the call site and the prepared statement below it are.
        $this->assertSame('1; DELETE FROM users; --', $userId);
        $this->assertStringContainsString('DELETE FROM', $userId);
    }

    /**
     * Input::get() recurses into arrays and encodes every leaf value.
     *
     * DataTables posts its search term as a nested array, so the endpoint must receive
     * the array shape intact with its leaves already neutralized.
     */
    public function testInputGetEncodesNestedArrayValues(): void
    {
        $_POST = [
            'search' => ['value' => '<script>alert("xss")</script>test'],
        ];

        $searchData = Input::get('search');

        $this->assertIsArray($searchData);
        $this->assertArrayHasKey('value', $searchData);
        $this->assertSame(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;test',
            $searchData['value']
        );
    }

    /**
     * A CSRF token round-trips through Input::get() and still validates.
     *
     * Token values are hex, so encoding leaves them untouched — the pairing used by every
     * POST handler (Token::check(Input::get('csrf'))) works end to end.
     */
    public function testCsrfTokenRoundTripsThroughInputGet(): void
    {
        $token = Token::generate();

        $_POST = ['csrf' => $token];
        $this->assertSame($token, Input::get('csrf'));
        $this->assertTrue(Token::check(Input::get('csrf')));

        // Absent token: Input::get() returns its default, which the format guard rejects
        $_POST = [];
        $this->assertFalse(Token::check(Input::get('csrf')));
    }
}
