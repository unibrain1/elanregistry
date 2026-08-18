<?php

declare(strict_types=1);

use ElanRegistry\DatabaseInterface;
use ElanRegistry\Exceptions\OwnerSearchException;
use ElanRegistry\Owner;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('fast')]
#[Group('unit')]
#[Group('owner')]
final class OwnerSearchTest extends TestCase
{
    /**
     * Database double for Owner::searchOwners().
     *
     * query() returns the double itself, mirroring the real \DB contract
     * (query() always returns $this for chaining), so the result set each test
     * needs is shaped with the count()/results() stubs on the same double.
     *
     * A stub, not a mock — these tests assert on searchOwners()' return value
     * and its error branch, never on how the database was called.
     *
     * @param int $rowCount Rows the search query reports
     * @param bool $hasError Whether the search query failed
     * @param array<int, \stdClass> $rows Rows returned by results()
     * @return \PHPUnit\Framework\MockObject\Stub&DatabaseInterface
     */
    private function createMockDb(int $rowCount = 0, bool $hasError = false, array $rows = []): object
    {
        $db = $this->createStub(DatabaseInterface::class);
        $db->method('query')->willReturnSelf();
        $db->method('error')->willReturn($hasError);
        $db->method('errorString')->willReturn('mock error');
        $db->method('count')->willReturn($rowCount);
        $db->method('results')->willReturn($rows);

        return $db;
    }

    public function testSearchOwnersReturnsEmptyArrayWhenNoResults(): void
    {
        $db      = $this->createMockDb(0);
        $owner   = new Owner(null, $db);
        $results = $owner->searchOwners('Portland');

        $this->assertSame([], $results);
    }

    public function testSearchOwnersReturnsResultsFromDb(): void
    {
        $row     = (object)['id' => 1, 'fname' => 'Alice', 'lname' => 'Smith'];
        $db      = $this->createMockDb(1, false, [$row]);
        $owner   = new Owner(null, $db);
        $results = $owner->searchOwners('Alice');

        $this->assertCount(1, $results);
        $this->assertSame($row, $results[0]);
    }

    public function testSearchOwnersThrowsOnDbError(): void
    {
        $db    = $this->createMockDb(0, true);
        $owner = new Owner(null, $db);

        $this->expectException(OwnerSearchException::class);
        $owner->searchOwners('Portland');
    }
}
