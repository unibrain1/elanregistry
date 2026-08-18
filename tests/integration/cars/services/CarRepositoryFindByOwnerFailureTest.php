<?php

declare(strict_types=1);

namespace Tests\Integration\Cars\Services;

use PHPUnit\Framework\TestCase;
use ElanRegistry\Car\CarRepository;
use ElanRegistry\Exceptions\CarDatabaseException;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration test for CarRepository::findByOwner()'s DB-error propagation
 *
 * Verifies CarDatabaseException is thrown when the underlying query errors
 * (found as an untested gap while working #1440). Stubs DatabaseInterface —
 * CarRepository's declared collaborator type since #1585 — so nothing here
 * touches an actual database connection.
 *
 * Extends plain TestCase, not IntegrationTestCase — it needs no DB fixtures
 * or connection (fully stubbed), so IntegrationTestCase::setUp()'s connection
 * check/requireDatabase() would be pure overhead. Don't "fix" this to extend
 * IntegrationTestCase; it's deliberate.
 */
#[Group('integration')]
final class CarRepositoryFindByOwnerFailureTest extends TestCase
{
    public function testFindByOwnerThrowsCarDatabaseExceptionOnQueryError(): void
    {
        $stubDb = $this->createStub(\ElanRegistry\DatabaseInterface::class);
        $stubDb->method('error')->willReturn(true);
        $stubDb->method('errorString')->willReturn('mock query failure');

        $repo = new CarRepository($stubDb);

        $this->expectException(CarDatabaseException::class);
        $this->expectExceptionMessage('CarRepository::findByOwner failed for user=1: mock query failure');

        $repo->findByOwner(1);
    }
}
