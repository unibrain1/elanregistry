<?php

declare(strict_types=1);

use ElanRegistry\TypeHelpers;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for type helper conversion logic.
 *
 * dbInt() tests call ElanRegistry\TypeHelpers::toInt() directly — the real
 * production logic (#1599), extracted from usersc/includes/custom_functions.php
 * so it's testable without full framework initialization. custom_functions.php's
 * dbInt() and tests/bootstrap-unit.php's dbInt() stub both delegate to it, so
 * there is exactly one copy of the conversion logic; a source-inspection test
 * below guards that custom_functions.php's dbInt() hasn't reverted to a local
 * reimplementation.
 *
 * currentUserId() is session-coupled (depends on the global $user UserSpice
 * object) and isn't cleanly extractable without breaking its zero-arg calling
 * convention used throughout the app, so it stays in custom_functions.php.
 * Its real-code coverage lives in tests/integration/CurrentUserIdTest.php
 * (#1599), using IntegrationTestCase::loginAsTestUser() to fake an
 * authenticated session — the same pattern UserDeletionReassignmentTest.php
 * already uses for a hook that also calls currentUserId() internally.
 *
 * @issue 1599
 */
#[Group('fast')]
final class TypeHelpersTest extends TestCase
{
    // ============================================================
    // TypeHelpers::toInt() tests
    // ============================================================

    public function testToIntWithObjectProperty(): void
    {
        $obj = (object) ['id' => '42', 'name' => 'test'];
        $this->assertSame(42, TypeHelpers::toInt($obj));
    }

    public function testToIntWithObjectCustomProperty(): void
    {
        $obj = (object) ['user_id' => '7', 'name' => 'test'];
        $this->assertSame(7, TypeHelpers::toInt($obj, 'user_id'));
    }

    public function testToIntWithIntegerValue(): void
    {
        $this->assertSame(5, TypeHelpers::toInt(5));
    }

    public function testToIntWithNumericString(): void
    {
        $this->assertSame(123, TypeHelpers::toInt('123'));
    }

    public function testToIntWithObjectIntProperty(): void
    {
        $obj = (object) ['id' => 99];
        $this->assertSame(99, TypeHelpers::toInt($obj));
    }

    public function testToIntWithZeroInteger(): void
    {
        $this->assertSame(0, TypeHelpers::toInt(0));
    }

    public function testToIntWithZeroString(): void
    {
        $this->assertSame(0, TypeHelpers::toInt('0'));
    }

    public function testToIntWithDecimalStringTruncates(): void
    {
        $this->assertSame(12, TypeHelpers::toInt('12.9'));
    }

    public function testToIntThrowsOnBooleanTrue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TypeHelpers::toInt(true);
    }

    public function testToIntWithObjectNullProperty_throwsPropertyDoesNotExist(): void
    {
        // isset() returns false for a property that exists but is null — dbInt()
        // deliberately cannot distinguish "property is null" from "property is
        // missing" and reports the latter message either way. This pins that as
        // known, intentional behavior rather than an accidental regression target.
        $obj = (object) ['id' => null];
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Property 'id' does not exist on object");
        TypeHelpers::toInt($obj);
    }

    public function testToIntThrowsOnNull(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TypeHelpers::toInt(null);
    }

    public function testToIntThrowsOnEmptyString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TypeHelpers::toInt('');
    }

    public function testToIntThrowsOnNonNumericString(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TypeHelpers::toInt('abc');
    }

    public function testToIntThrowsOnMissingProperty(): void
    {
        $obj = (object) ['name' => 'test'];
        $this->expectException(InvalidArgumentException::class);
        TypeHelpers::toInt($obj, 'id');
    }

    // ============================================================
    // Source inspection: dbInt() must delegate, not reimplement
    // ============================================================

    /**
     * custom_functions.php's dbInt() must call TypeHelpers::toInt() rather than
     * reimplementing the conversion logic inline — the drift risk this issue
     * (#1599) exists to close. Captures and exact-matches the function body
     * between its braces, rather than checking independent substrings, which
     * would still pass against a dbInt() that reimplements the logic inline
     * elsewhere in the file while TypeHelpers::toInt() is called from
     * unrelated dead code.
     */
    public function testCustomFunctionsDbIntDelegatesToTypeHelpers(): void
    {
        $file = dirname(__DIR__, 3) . '/usersc/includes/custom_functions.php';
        $content = (string) file_get_contents($file);

        $matched = preg_match(
            '/function dbInt\(mixed \$value, string \$property = \'id\'\): int\s*\{(.*?)\}/s',
            $content,
            $matches
        );

        $this->assertSame(1, $matched, 'custom_functions.php must define dbInt() with the expected signature');
        $this->assertSame(
            'return TypeHelpers::toInt($value, $property);',
            trim($matches[1]),
            'custom_functions.php dbInt() body must be exactly a delegating call to TypeHelpers::toInt() — no other logic'
        );
    }
}
