<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/IntegrationTestCase.php';

use ElanRegistry\Car\Car;
use ElanRegistry\Car\CarRepository;
use ElanRegistry\Exceptions\CarCreationException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration regression test for Car::create()'s repository-failure paths
 *
 * Sibling to CarUpdateRepositoryFailureTest — verifies the two failure
 * branches in Car::create() that only trigger when the repository layer
 * itself fails (not user input validation): CarRepository::insertCar()
 * returning false, and the post-insert find() failing to reload the newly
 * created row. Both were untested against the real Car class before #1440.
 * Unlike the insertCar() failure, the post-insert find() failure does not
 * log anything, so there is no matching log-regression test for that branch.
 *
 * This test lives in the integration suite (not unit) because it needs a
 * real DB-backed logging path (countMatchingLogs() queries the real `logs`
 * table). It loads the real Car class and injects a PHPUnit stub via
 * Reflection for the single repository property we need to control.
 *
 * @see usersc/classes/Car/Car.php Car::create()
 */
#[Group('integration')]
#[Group('car-create')]
final class CarCreateRepositoryFailureTest extends IntegrationTestCase
{
    private \ReflectionProperty $repositoryProp;
    private int $testUserId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->testUserId = $this->createTestUser();

        $this->repositoryProp = (new \ReflectionClass(Car::class))
            ->getProperty('repository');
    }

    /**
     * Fields that pass validateRequiredFields()/validateAndSanitizeFields() cleanly,
     * so the failure paths under test are reached instead of a validation exception.
     */
    private function validCarData(): array
    {
        return [
            'token'   => Token::generate(),
            'user_id' => $this->testUserId,
            'year'    => '1973',
            'model'   => 'Sprint|FHC|36',
            'series'  => 'Sprint',
            'variant' => 'FHC',
            'type'    => '36',
            'chassis' => 'RF' . substr(uniqid(), -8),
            'color'   => 'Repo Failure Test',
        ];
    }

    private function carWithStubRepo(): array
    {
        $car = new Car();
        $stubRepo = $this->createStub(CarRepository::class);
        $this->repositoryProp->setValue($car, $stubRepo);

        return [$car, $stubRepo];
    }

    /**
     * Core assertion: CarCreationException is thrown when insertCar() returns false.
     */
    public function testCreateThrowsCarCreationExceptionWhenInsertFails(): void
    {
        [$car, $stubRepo] = $this->carWithStubRepo();
        $stubRepo->method('insertCar')->willReturn(false);
        $stubRepo->method('errorString')->willReturn('mock insert failure');

        $this->expectException(CarCreationException::class);
        $this->expectExceptionMessage('Database error during car creation: mock insert failure');

        $car->create($this->validCarData());
    }

    /**
     * Regression guard: a DatabaseError log entry is written when insertCar() fails.
     */
    public function testCreateLogsDatabaseErrorWhenInsertFails(): void
    {
        $before = $this->countMatchingLogs('DatabaseError', 'Car creation failed%');

        [$car, $stubRepo] = $this->carWithStubRepo();
        $stubRepo->method('insertCar')->willReturn(false);
        $stubRepo->method('errorString')->willReturn('mock insert failure');

        try {
            $car->create($this->validCarData());
        } catch (CarCreationException) {
            // expected
        }

        $after = $this->countMatchingLogs('DatabaseError', 'Car creation failed%');
        $this->assertSame($before + 1, $after, 'Car::create() must log under DatabaseError when insertCar() fails');
    }

    /**
     * Core assertion: CarCreationException is thrown when the post-insert find() fails
     * to reload the newly created row (insertCar() succeeds, findById() returns null).
     */
    public function testCreateThrowsCarCreationExceptionWhenPostInsertFindFails(): void
    {
        [$car, $stubRepo] = $this->carWithStubRepo();
        $stubRepo->method('insertCar')->willReturn(true);
        $stubRepo->method('lastId')->willReturn(999999999);
        $stubRepo->method('findById')->willReturn(null);

        $this->expectException(CarCreationException::class);
        $this->expectExceptionMessage('Car ID 999999999 not found after insert');

        $car->create($this->validCarData());
    }
}
