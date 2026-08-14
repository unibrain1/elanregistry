<?php

declare(strict_types=1);

use ElanRegistry\Car\CarShowcaseService;
use ElanRegistry\DatabaseInterface;
use PHPUnit\Framework\TestCase;

use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for CarShowcaseService
 */
#[Group('fast')]
final class CarShowcaseServiceTest extends TestCase
{
    public function testGetNewCarIdsReturnsArrayOfIntsFromResults(): void
    {
        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('error')->willReturn(false);
        $db->method('results')->willReturn([(object) ['id' => 5], (object) ['id' => '7']]);

        $service = new CarShowcaseService($db);
        $ids = $service->getNewCarIds();

        $this->assertSame([5, 7], $ids);
    }

    public function testGetNewCarIdsReturnsEmptyArrayOnError(): void
    {
        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('error')->willReturn(true);

        $service = new CarShowcaseService($db);

        $this->assertSame([], $service->getNewCarIds());
    }

    public function testBuildShowcasePoolMergesResultsAndStampsIsNew(): void
    {
        $recent = [(object) ['id' => 1, 'year' => 1967, 'series' => 'S3', 'variant' => 'DHC', 'type' => '26', 'ctime' => '2026-01-01']];
        $random = [(object) ['id' => 2, 'year' => 1968, 'series' => 'S4', 'variant' => 'FHC', 'type' => '36', 'ctime' => '2025-01-01']];
        $newIdRows = [(object) ['id' => 2]];

        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('error')->willReturn(false);
        $db->method('results')->willReturnOnConsecutiveCalls($recent, $random, $newIdRows);

        $service = new CarShowcaseService($db);
        $pool = $service->buildShowcasePool();

        $this->assertCount(2, $pool);

        $byId = [];
        foreach ($pool as $car) {
            $byId[(int) $car->id] = $car;
        }

        $this->assertFalse($byId[1]->is_new, 'Car not present in getNewCarIds() results must be stamped is_new = false');
        $this->assertTrue($byId[2]->is_new, 'Car present in getNewCarIds() results must be stamped is_new = true');
    }

    public function testBuildShowcasePoolReturnsEmptyArrayWhenRecentQueryErrors(): void
    {
        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('results')->willReturn([]);
        $db->method('error')->willReturn(true);

        $service = new CarShowcaseService($db);

        $this->assertSame([], $service->buildShowcasePool());
    }

    public function testBuildShowcasePoolReturnsRecentPoolWithIsNewStampedWhenRandomQueryErrors(): void
    {
        $recent = [(object) ['id' => 1, 'year' => 1967, 'series' => 'S3', 'variant' => 'DHC', 'type' => '26', 'ctime' => '2026-01-01']];
        $newIdRows = [(object) ['id' => 1]];

        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('results')->willReturnOnConsecutiveCalls($recent, [], $newIdRows);
        $db->method('error')->willReturnOnConsecutiveCalls(false, true, false);

        $service = new CarShowcaseService($db);
        $pool = $service->buildShowcasePool();

        $this->assertSame($recent, $pool, 'Recent pool array must be returned when the random query errors');
        $this->assertObjectHasProperty('is_new', $pool[0], 'is_new must still be stamped on the random-query-error fallback, per the documented return shape');
        $this->assertTrue($pool[0]->is_new);
    }
}
