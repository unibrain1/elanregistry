<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\Car\Car;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for the car history endpoint's database-backed behaviour.
 *
 * Covers app/api/cars/history.php, which delegates to Car::exists() /
 * Car::history(). The endpoint file itself cannot be require()'d from PHPUnit:
 * every response path ends in ApiResponse::send(), which calls exit and would
 * terminate the test runner. This file therefore asserts the real data-shape
 * and behaviour by calling the Car facade against a real database, because that
 * class holds all the logic the endpoint delegates to.
 *
 * Only the assertions that genuinely need a database live here. The endpoint
 * wiring coverage for both history.php and chassis-validate.php (routing, CSRF
 * and AJAX guards, parameter guards, catch blocks, log categories, response
 * format consistency) plus the pure ChassisValidator behaviour tests live in
 * the unit tier, where CI actually runs them:
 * tests/unit/cars/CarActionsHistoryAndValidationWiringTest.php
 *
 * @author Elan Registry Development Team
 */
#[Group('integration')]
#[Group('car-actions')]
final class CarActionsHistoryAndValidationTest extends IntegrationTestCase
{
    private int $testUserId = 0;
    private int $testCarId  = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();

        $this->testUserId = $this->createTestUser();
        $this->testCarId  = $this->createTestCar($this->testUserId);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Return a car ID guaranteed not to exist in the cars table.
     *
     * @return int An unused car ID
     */
    private function nonExistentCarId(): int
    {
        $row = $this->db->query('SELECT MAX(id) AS max_id FROM cars')->first();

        return (int) ($row->max_id ?? 0) + 1000;
    }

    // =========================================================================
    // history.php — behaviour (Car::exists() / Car::history())
    // =========================================================================

    /**
     * Car::history() returns the audit rows the history endpoint wraps in its
     * DataTables payload.
     *
     * Arrange: create a real car and seed one cars_hist row for it
     *          (createTestCar() purges trigger-written history rows, so the
     *          seeded row is the only one present and the count is exact).
     * Act:     load the car through the same Car facade the endpoint uses.
     * Assert:  the car exists and history() returns that one audit record,
     *          with the fields the endpoint's DataTables payload relies on.
     */
    public function testHistorySuccessReturnsApiResponseWithDataTablesStructure(): void
    {
        $seeded = $this->db->insert('cars_hist', [
            'operation' => 'UPDATE',
            'car_id'    => $this->testCarId,
            'model'     => 'Elan S4',
            'series'    => 'S4',
            'variant'   => 'SE',
            'type'      => 'FHC',
            'year'      => 1973,
            'chassis'   => 'HIST' . substr(uniqid(), -8),
            'color'     => 'Blue',
        ]);
        $this->assertTrue($seeded, 'Precondition: should be able to seed a cars_hist row');

        $car = new Car($this->testCarId);
        $this->assertTrue($car->exists(), 'Fixture car must be found by the Car facade');

        $history = $car->history();

        // recordsTotal / recordsFiltered in the endpoint are count($carHist).
        $this->assertCount(1, $history, 'History must contain exactly the seeded audit row');

        $row = $history[0];
        $this->assertObjectHasProperty('operation', $row);
        $this->assertObjectHasProperty('car_id', $row);
        $this->assertObjectHasProperty('timestamp', $row);
        $this->assertSame('UPDATE', $row->operation);
        $this->assertSame($this->testCarId, (int) $row->car_id);
    }

    /**
     * A car ID that does not exist produces the state the endpoint turns into
     * its 404 response.
     *
     * Arrange: derive an ID above the current MAX(cars.id).
     * Act:     construct the Car facade with that ID.
     * Assert:  exists() is false and no history is returned, which is exactly
     *          the branch history.php answers with ApiResponse::notFound().
     */
    public function testHistoryCarNotFoundReturnsNotFound(): void
    {
        $car = new Car($this->nonExistentCarId());

        $this->assertFalse($car->exists(), 'Unused car ID must not resolve to a car');
        $this->assertNull($car->data(), 'Unused car ID must not load car data');
        $this->assertEmpty($car->history(), 'Unused car ID must not return history rows');
    }
}
