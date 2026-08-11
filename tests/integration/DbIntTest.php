<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test proving the real global dbInt() (usersc/includes/custom_functions.php)
 * is actually callable and correct — not just its extracted TypeHelpers::toInt() logic
 * (covered directly in tests/unit/helpers/TypeHelpersTest.php) or the source-inspection
 * guard that dbInt() delegates to it (also in TypeHelpersTest.php, which only reads the
 * file's text and never executes it).
 *
 * Without this test, dropping the `use ElanRegistry\TypeHelpers;` import from
 * custom_functions.php would make the real dbInt() fatal at call time
 * (Class "ElanRegistry\TypeHelpers" not found) while every other test in the
 * suite still passed — this test is what actually closes that gap (#1599).
 *
 * @issue 1599
 */
#[Group('integration')]
final class DbIntTest extends IntegrationTestCase
{
    public function test_dbInt_withObjectProperty_returnsInt(): void
    {
        $obj = (object) ['id' => '42'];
        $this->assertSame(42, dbInt($obj));
    }

    public function test_dbInt_withScalarInt_returnsInt(): void
    {
        $this->assertSame(5, dbInt(5));
    }

    public function test_dbInt_withNullValue_throwsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        dbInt(null);
    }
}
