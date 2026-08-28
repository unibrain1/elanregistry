<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/IntegrationTestCase.php';

use ElanRegistry\Car\Car;
use ElanRegistry\Car\CarRepository;
use ElanRegistry\Exceptions\CarDatabaseException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration regression test for Car::find()'s subordinate-lookup failure handling
 *
 * Verifies the #1505 fix: CarRepository::getHistory() and getFactoryInfo()
 * now throw CarDatabaseException on a DB failure (previously silently
 * returned [] / null). find()'s own contract is that only the primary
 * findById() lookup legitimately throws — so find() must catch a
 * CarDatabaseException from either subordinate lookup, log it under
 * LOG_CATEGORY_DATABASE_ERROR, and still return true with degraded data
 * (empty history / null factory info) rather than let the exception
 * propagate out of find().
 *
 * This test lives in the integration suite (not unit) because it needs a
 * real DB-backed logging path (countMatchingLogs() queries the real `logs`
 * table) and a real car row for findById() to return. It loads the real
 * Car class and injects a PHPUnit stub via Reflection for the single
 * repository property we need to control — findById() delegates to a real
 * CarRepository so the primary lookup succeeds with genuine data, while
 * getHistory()/getFactoryInfo() are stubbed to throw. Sibling to
 * CarCreateRepositoryFailureTest / CarUpdateRepositoryFailureTest, which use
 * the same Reflection-injection technique for Car::create()/update().
 *
 * @see usersc/classes/Car/Car.php Car::find()
 */
#[Group('integration')]
#[Group('car-find')]
final class CarFindSubordinateLookupFailureTest extends IntegrationTestCase
{
    private \ReflectionProperty $repositoryProp;
    private int $testUserId;
    private int $testCarId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->testUserId = $this->createTestUser();
        $this->testCarId = $this->createTestCar($this->testUserId, [
            'chassis' => 'FIND' . substr(uniqid(), -8),
        ]);

        $this->repositoryProp = (new \ReflectionClass(Car::class))
            ->getProperty('repository');
    }

    /**
     * Build a Car whose repository delegates findById() to a real
     * CarRepository (so the primary lookup returns genuine data) while
     * getHistory() and getFactoryInfo() are stubbed to throw
     * CarDatabaseException, simulating a DB failure isolated to those two
     * subordinate queries.
     */
    private function carWithFailingSubordinateLookups(): Car
    {
        $realRepo = new CarRepository($this->db);

        $stubRepo = $this->createStub(CarRepository::class);
        $stubRepo->method('findById')->willReturnCallback(
            fn(int $carId) => $realRepo->findById($carId)
        );
        $stubRepo->method('getHistory')->willThrowException(
            new CarDatabaseException('mock getHistory failure')
        );
        $stubRepo->method('getFactoryInfo')->willThrowException(
            new CarDatabaseException('mock getFactoryInfo failure')
        );

        $car = new Car();
        $this->repositoryProp->setValue($car, $stubRepo);

        return $car;
    }

    /**
     * Core assertion: find() still returns true and degrades to empty
     * history / null factory info when both subordinate lookups fail.
     */
    public function testFindStillSucceedsWithDegradedDataWhenSubordinateLookupsFail(): void
    {
        $car = $this->carWithFailingSubordinateLookups();

        $result = $car->find($this->testCarId);

        $this->assertTrue($result, 'find() must still return true when only the subordinate lookups fail');
        $this->assertSame([], $car->history(), 'history() must degrade to an empty array on getHistory() failure');
        $this->assertNull($car->factory(), 'factory() must degrade to null on getFactoryInfo() failure');
        $this->assertNotNull($car->data(), 'the primary car data must still be loaded');
    }

    /**
     * Regression guard: both failures are logged under DATABASE_ERROR so the
     * degraded result is not silent to operators.
     */
    public function testFindLogsBothSubordinateLookupFailuresUnderDatabaseError(): void
    {
        $historyBefore = $this->countMatchingLogs('DatabaseError', '%getHistory failed for car ' . $this->testCarId . '%');
        $factoryBefore = $this->countMatchingLogs('DatabaseError', '%getFactoryInfo failed for car ' . $this->testCarId . '%');

        $this->carWithFailingSubordinateLookups()->find($this->testCarId);

        $historyAfter = $this->countMatchingLogs('DatabaseError', '%getHistory failed for car ' . $this->testCarId . '%');
        $factoryAfter = $this->countMatchingLogs('DatabaseError', '%getFactoryInfo failed for car ' . $this->testCarId . '%');

        $this->assertSame(
            $historyBefore + 1,
            $historyAfter,
            'find() must log under DatabaseError when getHistory() fails'
        );
        $this->assertSame(
            $factoryBefore + 1,
            $factoryAfter,
            'find() must log under DatabaseError when getFactoryInfo() fails'
        );
    }
}
