<?php

declare(strict_types=1);

use ElanRegistry\ChassisValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Branch-coverage tests for ChassisValidator's private validation logic.
 * @see usersc/classes/ChassisValidator.php
 */
#[Group('fast')]
#[Group('unit')]
#[Group('chassis')]
final class ChassisValidatorTest extends TestCase
{
    private const RACE_MODEL  = '26|Race|Roadster';
    private const ELAN_MODEL  = 'S1|Standard|Roadster';
    private const PLUS2_MODEL = 'Elan+2|Standard|Coupe';

    /**
     * @return array{valid: bool, chassis: string, error_reason: string, format_type: string, override_used: bool}
     */
    private function validate(
        string $chassis,
        int    $year,
        string $model,
        bool   $allowOverride = false
    ): array {
        return (new ChassisValidator())->validate($chassis, $year, $model, $allowOverride);
    }

    // -------------------------------------------------------------------------
    // Race car detection — RACE_PATTERNS by year
    // -------------------------------------------------------------------------

    public function testRaceCar1963AcceptsOnlyRFormat(): void
    {
        $pass = $this->validate('26-R-01', 1963, self::RACE_MODEL);
        $this->assertTrue($pass['valid']);

        $fail = $this->validate('26-S2-01', 1963, self::RACE_MODEL);
        $this->assertFalse($fail['valid']);
        $this->assertStringContainsString('1963 race cars must use format 26-R-xx', $fail['error_reason']);
    }

    public function testRaceCar1964AcceptsBothFormats(): void
    {
        $rFormat = $this->validate('26-R-01', 1964, self::RACE_MODEL);
        $this->assertTrue($rFormat['valid']);

        $s2Format = $this->validate('26-S2-01', 1964, self::RACE_MODEL);
        $this->assertTrue($s2Format['valid']);

        $fail = $this->validate('26-X-01', 1964, self::RACE_MODEL);
        $this->assertFalse($fail['valid']);
        $this->assertStringContainsString(
            '1964 race cars must use format 26-R-xx or 26-S2-xx',
            $fail['error_reason']
        );
    }

    public function testRaceCar1965And1966AcceptOnlyS2Format(): void
    {
        foreach ([1965, 1966] as $year) {
            $pass = $this->validate('26-S2-01', $year, self::RACE_MODEL);
            $this->assertTrue($pass['valid']);

            $fail = $this->validate('26-R-01', $year, self::RACE_MODEL);
            $this->assertFalse($fail['valid']);
            $this->assertStringContainsString('race cars must use format 26-S2-xx', $fail['error_reason']);
        }
    }

    public function testRaceCarOffListYearUsesDefaultPattern(): void
    {
        $pass = $this->validate('26-R-01', 1967, self::RACE_MODEL);
        $this->assertTrue($pass['valid']);

        $fail = $this->validate('26-S2-01', 1967, self::RACE_MODEL);
        $this->assertFalse($fail['valid']);
        $this->assertStringContainsString('race cars must use format 26-R-xx', $fail['error_reason']);
    }

    // -------------------------------------------------------------------------
    // Pre-1970 production cars
    // -------------------------------------------------------------------------

    public function testPre1970ValidFourDigitChassis(): void
    {
        $result = $this->validate('1234', 1966, self::ELAN_MODEL);

        $this->assertTrue($result['valid']);
        $this->assertSame('pre_1970', $result['format_type']);
    }

    public function testPre1970NonNumericChassisFails(): void
    {
        $result = $this->validate('12AB', 1966, self::ELAN_MODEL);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('must be numeric', $result['error_reason']);
    }

    public function testPre1970WrongLengthChassisFails(): void
    {
        $tooShort = $this->validate('123', 1966, self::ELAN_MODEL);
        $this->assertFalse($tooShort['valid']);
        $this->assertStringContainsString('must be exactly 4 digits', $tooShort['error_reason']);

        $tooLong = $this->validate('12345', 1966, self::ELAN_MODEL);
        $this->assertFalse($tooLong['valid']);
        $this->assertStringContainsString('must be exactly 4 digits', $tooLong['error_reason']);
    }

    // -------------------------------------------------------------------------
    // Post-1970 routing
    // -------------------------------------------------------------------------

    public function testPost1970Year1970RoutesFiveCharToFiveCharFormat(): void
    {
        $result = $this->validate('1234A', 1970, self::ELAN_MODEL);

        $this->assertSame('post_1970', $result['format_type']);
        $this->assertTrue($result['valid']);
    }

    public function testPost1970Year1970RoutesElevenCharToElevenCharFormat(): void
    {
        $result = $this->validate('7001019999A', 1970, self::ELAN_MODEL);

        $this->assertSame('post_1970', $result['format_type']);
        $this->assertTrue($result['valid']);
    }

    public function testPost1970Year1970WrongLengthFails(): void
    {
        $result = $this->validate('1234567', 1970, self::ELAN_MODEL);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('must be 5 characters', $result['error_reason']);
        $this->assertStringContainsString('or 11 characters', $result['error_reason']);
    }

    public function testPost1970NonTransitionYearWrongLengthFails(): void
    {
        $result = $this->validate('1234567', 1973, self::ELAN_MODEL);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('must be 11 characters', $result['error_reason']);
    }

    // -------------------------------------------------------------------------
    // Eleven-char format
    // -------------------------------------------------------------------------

    public function testElevenCharFormatValidElanSuffixPasses(): void
    {
        $result = $this->validate('7301019999A', 1973, self::ELAN_MODEL);

        $this->assertTrue($result['valid']);
    }

    public function testElevenCharFormatValidPlus2SuffixPasses(): void
    {
        $result = $this->validate('7301019999L', 1973, self::PLUS2_MODEL);

        $this->assertTrue($result['valid']);
    }

    public function testElevenCharFormatNonNumericBaseFails(): void
    {
        $result = $this->validate('73010199ABA', 1973, self::ELAN_MODEL);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('must be numeric', $result['error_reason']);
    }

    public function testElevenCharFormatInvalidSuffixFails(): void
    {
        $iSuffix = $this->validate('7301019999I', 1973, self::ELAN_MODEL);
        $this->assertFalse($iSuffix['valid']);
        $this->assertStringContainsString('require letter codes', $iSuffix['error_reason']);

        $zSuffix = $this->validate('7301019999Z', 1973, self::ELAN_MODEL);
        $this->assertFalse($zSuffix['valid']);
        $this->assertStringContainsString('require letter codes', $zSuffix['error_reason']);
    }

    // -------------------------------------------------------------------------
    // Five-char format (1970 transition)
    // -------------------------------------------------------------------------

    public function testFiveCharFormatValidSuffixPasses(): void
    {
        $result = $this->validate('1234A', 1970, self::ELAN_MODEL);

        $this->assertTrue($result['valid']);
    }

    public function testFiveCharFormatNonNumericBaseFails(): void
    {
        $result = $this->validate('12ABA', 1970, self::ELAN_MODEL);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('must be numeric', $result['error_reason']);
    }

    public function testFiveCharFormatInvalidSuffixFails(): void
    {
        $result = $this->validate('1234I', 1970, self::ELAN_MODEL);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('require letter codes', $result['error_reason']);
    }

    // -------------------------------------------------------------------------
    // getValidSuffixes distinction (Elan vs +2)
    // -------------------------------------------------------------------------

    public function testGetValidSuffixesElanRange(): void
    {
        $aSuffix = $this->validate('7301019999A', 1973, self::ELAN_MODEL);
        $this->assertTrue($aSuffix['valid']);

        $kSuffix = $this->validate('7301019999K', 1973, self::ELAN_MODEL);
        $this->assertTrue($kSuffix['valid']);

        $lSuffix = $this->validate('7301019999L', 1973, self::ELAN_MODEL);
        $this->assertFalse($lSuffix['valid']);
        $this->assertStringContainsString('A-K (excluding I)', $lSuffix['error_reason']);
    }

    public function testGetValidSuffixesPlus2Range(): void
    {
        foreach (['L', 'M', 'N'] as $suffix) {
            $result = $this->validate('7301019999' . $suffix, 1973, self::PLUS2_MODEL);
            $this->assertTrue($result['valid']);
        }

        $aSuffix = $this->validate('7301019999A', 1973, self::PLUS2_MODEL);
        $this->assertFalse($aSuffix['valid']);
        $this->assertStringContainsString('L, M, N', $aSuffix['error_reason']);
    }

    // -------------------------------------------------------------------------
    // Empty/required chassis
    // -------------------------------------------------------------------------

    public function testEmptyChassisFails(): void
    {
        $result = $this->validate('', 1973, self::ELAN_MODEL);

        $this->assertFalse($result['valid']);
        $this->assertSame('Chassis number is required', $result['error_reason']);
    }

    public function testWhitespaceOnlyChassisFails(): void
    {
        $result = $this->validate('   ', 1973, self::ELAN_MODEL);

        $this->assertFalse($result['valid']);
        $this->assertSame('Chassis number is required', $result['error_reason']);
    }
}
