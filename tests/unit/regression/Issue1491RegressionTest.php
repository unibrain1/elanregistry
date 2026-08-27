<?php

declare(strict_types=1);

use ElanRegistry\Car\CarValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for Issue #1491: fname/lname not trimmed by CarValidator
 *
 * CarValidator::validateAndSanitizeFields() had no case for 'fname'/'lname',
 * so those fields fell through to the default handling and passed through
 * unmodified — leading/trailing whitespace was never trimmed, unlike the
 * sibling 'city'/'state'/'country' fields which were already normalized.
 *
 * Fix: Added a dedicated case 'fname': case 'lname': block (mirroring the
 * existing city/state/country case) guarded by if (!empty($value)), calling
 * InputSanitizer::normalize($value, 100).
 *
 * @issue 1491
 * @link https://github.com/elan-registry/registry/issues/1491
 * @category regression
 */
#[Group('regression')]
final class Issue1491RegressionTest extends TestCase
{
    private CarValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new CarValidator();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function nameFieldProvider(): array
    {
        return [
            'fname with leading/trailing whitespace' => ['fname', '  John  ', 'John'],
            'lname with leading/trailing whitespace' => ['lname', '  Smith  ', 'Smith'],
            'fname with tabs and newlines'           => ['fname', "\tJohn\n", 'John'],
            'lname with tabs and newlines'           => ['lname', "\tSmith\n", 'Smith'],
        ];
    }

    #[DataProvider('nameFieldProvider')]
    public function testValidateAndSanitizeFieldsTrimsNameField(string $field, string $input, string $expected): void
    {
        $result = $this->validator->validateAndSanitizeFields([$field => $input], false);

        $this->assertSame($expected, $result[$field]);
    }

    /**
     * Empty fname/lname must not be stored — matches the !empty($value) guard
     * shared with city/state/country.
     */
    public function testValidateAndSanitizeFieldsDropsEmptyFname(): void
    {
        $result = $this->validator->validateAndSanitizeFields(['fname' => ''], false);

        $this->assertArrayNotHasKey('fname', $result);
    }

    public function testValidateAndSanitizeFieldsDropsEmptyLname(): void
    {
        $result = $this->validator->validateAndSanitizeFields(['lname' => ''], false);

        $this->assertArrayNotHasKey('lname', $result);
    }

    /**
     * fname/lname share the 100-char cap InputSanitizer::normalize() applies
     * to city/state/country, even though the underlying cars.fname/lname
     * columns are varchar(155) — inherited from the sibling case, not
     * re-derived from this column's own width.
     */
    #[DataProvider('longNameFieldProvider')]
    public function testValidateAndSanitizeFieldsTruncatesLongNameField(string $field, string $input, int $expectedLength): void
    {
        $result = $this->validator->validateAndSanitizeFields([$field => $input], false);

        $this->assertSame($expectedLength, mb_strlen($result[$field]));
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public static function longNameFieldProvider(): array
    {
        return [
            'fname longer than 100 chars' => ['fname', str_repeat('a', 150), 100],
            'lname longer than 100 chars' => ['lname', str_repeat('b', 150), 100],
        ];
    }
}
