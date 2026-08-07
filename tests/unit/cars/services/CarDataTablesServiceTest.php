<?php

declare(strict_types=1);

use ElanRegistry\Car\CarDataTablesService;
use ElanRegistry\Exceptions\CarValidationException;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for CarDataTablesService service class
 */
#[Group('fast')]
final class CarDataTablesServiceTest extends TestCase
{
    private CarDataTablesService $service;

    protected function setUp(): void
    {
        $this->service = new CarDataTablesService();
    }

    /**
     * Invoke the private validateColumnName() method via reflection.
     */
    private function invokeValidateColumnName(string $columnName, string $tableName): string|false
    {
        $method = new ReflectionMethod(CarDataTablesService::class, 'validateColumnName');
        return $method->invoke($this->service, $columnName, $tableName);
    }

    // ============================================================
    // validateColumnName tests
    // ============================================================

    public function testValidateColumnNameAcceptsValidCarsColumn(): void
    {
        $result = $this->invokeValidateColumnName('chassis', 'cars');
        $this->assertEquals('chassis', $result);
    }

    public function testValidateColumnNameAcceptsValidFactoryColumn(): void
    {
        $result = $this->invokeValidateColumnName('serial', 'elan_factory_info');
        $this->assertEquals('serial', $result);
    }

    public function testValidateColumnNameRejectsInvalidColumn(): void
    {
        $result = $this->invokeValidateColumnName('password', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsInvalidTable(): void
    {
        $result = $this->invokeValidateColumnName('id', 'users');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsSqlInjection(): void
    {
        $result = $this->invokeValidateColumnName('id; DROP TABLE cars', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsRemovedModifiedByColumn(): void
    {
        $result = $this->invokeValidateColumnName('ModifiedBy', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsEmail(): void
    {
        $result = $this->invokeValidateColumnName('email', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsLname(): void
    {
        $result = $this->invokeValidateColumnName('lname', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsVericode(): void
    {
        $result = $this->invokeValidateColumnName('vericode', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsLastVerified(): void
    {
        $result = $this->invokeValidateColumnName('last_verified', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsUserId(): void
    {
        $result = $this->invokeValidateColumnName('user_id', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsLat(): void
    {
        $result = $this->invokeValidateColumnName('lat', 'cars');
        $this->assertFalse($result);
    }

    public function testValidateColumnNameRejectsLon(): void
    {
        $result = $this->invokeValidateColumnName('lon', 'cars');
        $this->assertFalse($result);
    }

    // ============================================================
    // getDataTablesData tests
    // ============================================================

    public function testGetDataTablesDataThrowsOnInvalidTable(): void
    {
        $this->expectException(CarValidationException::class);

        $request = ['draw' => 1, 'start' => 0, 'length' => 10];
        $this->service->getDataTablesData($request, 'invalid_table', DB::getInstance());
    }

    // ============================================================
    // getDataTablesData — invalid pagination
    // ============================================================

    /**
     * @return array<string, array{int, int}>
     */
    public static function invalidPaginationProvider(): array
    {
        return [
            'length zero'          => [0,    0],
            'length negative one'  => [0,   -1],
            'length negative two'  => [0,   -2],
            'length negative 99'   => [0,  -99],
            'length over cap'      => [0,  101],
            'length way over cap'  => [0, 9999],
            'negative start'       => [-1,  25],
            'large negative start' => [-100, 25],
        ];
    }

    #[DataProvider('invalidPaginationProvider')]
    public function testGetDataTablesDataThrowsOnInvalidPagination(int $start, int $length): void
    {
        $this->expectException(CarValidationException::class);

        $request = ['draw' => 1, 'start' => $start, 'length' => $length, 'search' => ['value' => ''], 'order' => [], 'columns' => []];
        $this->service->getDataTablesData($request, 'cars', DB::getInstance());
    }

    // ============================================================
    // getDataTablesData — valid pagination passes validation
    // ============================================================

    /**
     * @return array<string, array{int, int}>
     */
    public static function validPaginationProvider(): array
    {
        return [
            'minimum length (start=0, length=1)'  => [0,    1],
            'first page (start=0, length=25)'      => [0,   25],
            'maximum length (start=50, length=100)' => [50, 100],
        ];
    }

    #[DataProvider('validPaginationProvider')]
    public function testGetDataTablesDataAcceptsValidPagination(int $start, int $length): void
    {
        $caught = null;
        try {
            $request = ['draw' => 1, 'start' => $start, 'length' => $length, 'search' => ['value' => ''], 'order' => [], 'columns' => []];
            $this->service->getDataTablesData($request, 'cars', DB::getInstance());
        } catch (CarValidationException $e) {
            $caught = $e;
        } catch (\Throwable) {
            // DB not available in unit context — validation passed
        }

        $this->assertNull($caught, 'Valid pagination parameters should not throw CarValidationException');
    }

}
