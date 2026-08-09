<?php

declare(strict_types=1);

require_once __DIR__ . '/../IntegrationTestCase.php';

use ElanRegistry\Car\Car;
use ElanRegistry\Exceptions\CarValidationException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for Car's basic lifecycle/accessor behavior against the
 * real class — find(), exists(), history(), findByOwner() edge cases that
 * were previously only implicitly exercised as test setup elsewhere, never
 * explicitly asserted (see issue #1440).
 */
#[Group('integration')]
final class CarCoreAccessorsTest extends IntegrationTestCase
{
    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->testUserId = $this->createTestUser();
    }

    /**
     * find() on a nonexistent car ID returns false
     */
    #[Group('integration')]
    public function testFindReturnsFalseForNonexistentId(): void
    {
        $car = new Car();
        $result = $car->find(999999999);

        $this->assertFalse($result);
    }

    /**
     * exists() is false before any find() has succeeded
     */
    #[Group('integration')]
    public function testExistsReturnsFalseBeforeFind(): void
    {
        $car = new Car();

        $this->assertFalse($car->exists());
    }

    /**
     * exists() is true after a successful find() via the constructor
     */
    #[Group('integration')]
    public function testExistsReturnsTrueAfterFind(): void
    {
        $carId = $this->createTestCar($this->testUserId);
        $car = new Car($carId);

        $this->assertTrue($car->exists());
    }

    /**
     * history() defaults to an empty array for a Car instance with no loaded data
     */
    #[Group('integration')]
    public function testHistoryDefaultsToEmptyArrayForNewCarInstance(): void
    {
        $car = new Car();

        $this->assertSame([], $car->history());
    }

    /**
     * history() returns populated records after a real DB-trigger-backed update
     */
    #[Group('integration')]
    public function testHistoryReturnsPopulatedRecordsAfterUpdate(): void
    {
        $carId = $this->createTestCar($this->testUserId);
        $car = new Car($carId);

        // Car::update() reloads via find() internally, so history() reflects the
        // trigger-inserted cars_hist row without a separate find() call.
        $car->update([
            'id'    => $carId,
            'token' => Token::generate(),
            'color' => 'History Test Color',
        ]);

        $history = $car->history();

        $this->assertNotEmpty($history, 'Expected at least one history record after update()');
        $operations = array_map(fn($record) => $record->operation, $history);
        $this->assertContains('UPDATE', $operations, 'Expected an UPDATE operation record in history');
    }

    /**
     * findByOwner() returns an empty array for a user with zero cars
     */
    #[Group('integration')]
    public function testFindByOwnerReturnsEmptyArrayWhenNoCars(): void
    {
        $ownerWithNoCars = $this->createTestUser();

        $cars = Car::findByOwner($ownerWithNoCars);

        $this->assertSame([], $cars);
    }

    /**
     * findByOwner() returns populated, correctly-owned Car instances for a real owner
     */
    #[Group('integration')]
    public function testFindByOwnerReturnsPopulatedCarsForOwner(): void
    {
        // A decoy car owned by a different user proves the WHERE clause actually
        // filters by owner — without it, an implementation that ignored $ownerId
        // and returned every car would pass just as easily.
        $otherOwnerId = $this->createTestUser();
        $decoyCarId = $this->createTestCar($otherOwnerId);

        $carId1 = $this->createTestCar($this->testUserId);
        $carId2 = $this->createTestCar($this->testUserId);

        $cars = Car::findByOwner($this->testUserId);

        $this->assertCount(2, $cars);

        $returnedIds = [];
        foreach ($cars as $car) {
            $this->assertInstanceOf(Car::class, $car);
            $this->assertTrue($car->exists());
            $this->assertSame($this->testUserId, (int) $car->data()->user_id);
            $returnedIds[] = (int) $car->data()->id;
        }

        $this->assertNotContains($decoyCarId, $returnedIds, 'findByOwner() must not return another owner\'s car');

        $expectedIds = [$carId1, $carId2];
        sort($returnedIds);
        sort($expectedIds);
        $this->assertSame($expectedIds, $returnedIds);
    }

    /**
     * findByOwner() throws CarValidationException for a non-positive owner ID
     */
    #[Group('integration')]
    public function testFindByOwnerThrowsOnInvalidOwnerId(): void
    {
        $this->expectException(CarValidationException::class);

        Car::findByOwner(0);
    }
}
