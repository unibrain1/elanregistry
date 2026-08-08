<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/IntegrationTestCase.php';

use ElanRegistry\Car\Car;
use ElanRegistry\Car\CarRepository;
use ElanRegistry\Exceptions\CarDatabaseException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration regression test for Car::update() repository-failure path
 *
 * Verifies the #934 fix: when CarRepository::update() returns false, exactly
 * one log entry is written under LOG_CATEGORY_DATABASE_ERROR and
 * CarDatabaseException is thrown. The old code had two consecutive
 * if (!$updateResult) guards — the first logged under LOG_CATEGORY_CAR_UPDATE
 * ("may indicate no changes") before the second logged the actual failure and
 * threw. That duplicate guard is now removed.
 *
 * This test lives in the integration suite (not unit) because it needs a
 * real DB-backed logging path (countMatchingLogs() queries the real `logs`
 * table). It loads the real Car class and injects a PHPUnit stub via
 * Reflection for the single repository property we need to control.
 *
 * @issue 934
 * @link https://github.com/unibrain1/elanregistry/issues/934
 * @see usersc/classes/Car/Car.php Car::update()
 */
#[Group('integration')]
#[Group('car-update')]
final class CarUpdateRepositoryFailureTest extends IntegrationTestCase
{
    private \ReflectionProperty $repositoryProp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->repositoryProp = (new \ReflectionClass(Car::class))
            ->getProperty('repository');
    }

    /**
     * Helper: build a Car with a stub repository that always returns false
     * from updateCar().
     */
    private function carWithFailingRepo(): Car
    {
        $car = new Car();

        $stubRepo = $this->createStub(CarRepository::class);
        $stubRepo->method('updateCar')->willReturn(false);

        $this->repositoryProp->setValue($car, $stubRepo);

        return $car;
    }

    /**
     * Core assertion: CarDatabaseException is thrown on repo update failure.
     */
    public function testUpdateThrowsCarDatabaseExceptionOnRepositoryFailure(): void
    {
        $this->expectException(CarDatabaseException::class);
        $this->expectExceptionMessage('Database update failed');

        $this->carWithFailingRepo()->update([
            'id'    => 1,
            'token' => Token::generate(),
        ]);
    }

    /**
     * Regression guard: exactly one log entry under DATABASE_ERROR, none under
     * CAR_UPDATE. Previously two guards fired two log() calls; now only one.
     */
    public function testUpdateLogsExactlyOnceUnderDatabaseErrorOnFailure(): void
    {
        $dbErrBefore = $this->countMatchingLogs('DatabaseError', 'Car update failed%');
        $carUpdBefore = $this->countMatchingLogs('CarUpdate', '%');

        try {
            $this->carWithFailingRepo()->update([
                'id'    => 1,
                'token' => Token::generate(),
            ]);
        } catch (CarDatabaseException) {
            // expected
        }

        $dbErrAfter  = $this->countMatchingLogs('DatabaseError', 'Car update failed%');
        $carUpdAfter = $this->countMatchingLogs('CarUpdate', '%');

        $this->assertSame(
            $dbErrBefore + 1,
            $dbErrAfter,
            'Car::update() must log exactly once under DatabaseError when the repository returns false'
        );

        $this->assertSame(
            $carUpdBefore,
            $carUpdAfter,
            'LOG_CATEGORY_CAR_UPDATE must not fire on update failure after #934 fix'
        );
    }

    /**
     * update() still returns true when updateCar() itself succeeds but the
     * post-update reload (find() -> findById()) fails — Car.php:254-257 logs a
     * "state may be stale" warning instead of throwing. Sibling to
     * CarCreateRepositoryFailureTest::testCreateThrowsCarCreationExceptionWhenPostInsertFindFails,
     * except create() throws on this failure and update() does not.
     *
     * Uses createStub() (not createMock()), matching carWithFailingRepo() above.
     */
    public function testUpdateStillSucceedsButLogsWhenPostUpdateReloadFails(): void
    {
        $stubRepo = $this->createStub(CarRepository::class);
        $stubRepo->method('updateCar')->willReturn(true);
        $stubRepo->method('findById')->willReturn(null);

        $car = new Car();
        $this->repositoryProp->setValue($car, $stubRepo);

        $before = $this->countMatchingLogs('DatabaseError', '%reload via find() failed%');

        $result = $car->update([
            'id'    => 999999999,
            'token' => Token::generate(),
            'color' => 'Reload Failure Test',
        ]);

        $this->assertTrue($result, 'update() must still return true even when the post-update reload fails');

        $after = $this->countMatchingLogs('DatabaseError', '%reload via find() failed%');
        $this->assertSame(
            $before + 1,
            $after,
            'update() must log under DatabaseError when the post-update find() fails to reload'
        );
    }
}
