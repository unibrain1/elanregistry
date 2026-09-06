<?php

declare(strict_types=1);

use ElanRegistry\OwnerSyncResult;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OwnerSyncResult (#1954).
 *
 * Pure value object — no DB, no collaborators. Covers the three-way
 * updated/failed/skipped split: count/phrase helpers for the `skipped`
 * bucket, the updated `totalCount()` denominator, and — the key behavior
 * this issue exists for — that `isCompleteSuccess()` stays keyed on
 * `$failed` alone, so a skip-only result reads as complete success.
 *
 * @see usersc/classes/OwnerSyncResult.php
 * @see https://github.com/elan-registry/registry/issues/1954
 */
#[Group('fast')]
#[Group('unit')]
final class OwnerSyncResultTest extends TestCase
{
    public function testSkippedCount_EmptySkipped_ReturnsZero(): void
    {
        $result = new OwnerSyncResult(updated: [1, 2], failed: [], skipped: []);

        $this->assertSame(0, $result->skippedCount());
    }

    public function testSkippedCount_NonEmptySkipped_ReturnsCount(): void
    {
        $result = new OwnerSyncResult(updated: [], failed: [], skipped: [5, 12, 19]);

        $this->assertSame(3, $result->skippedCount());
    }

    public function testSkippedCarsPhrase_EmptySkipped_ReturnsEmptyString(): void
    {
        $result = new OwnerSyncResult(updated: [1], failed: [], skipped: []);

        $this->assertSame('', $result->skippedCarsPhrase());
    }

    public function testSkippedCarsPhrase_OneSkippedCar_ReturnsSingularPhrase(): void
    {
        $result = new OwnerSyncResult(updated: [], failed: [], skipped: [2107]);

        $this->assertSame('Car 2107 no longer owned; not updated.', $result->skippedCarsPhrase());
    }

    public function testSkippedCarsPhrase_MultipleSkippedCars_ReturnsPluralPhrase(): void
    {
        $result = new OwnerSyncResult(updated: [], failed: [], skipped: [5, 12]);

        $this->assertSame('Cars 5, 12 no longer owned; not updated.', $result->skippedCarsPhrase());
    }

    public function testTotalCount_AllThreeBucketsNonEmpty_SumsAllThree(): void
    {
        $result = new OwnerSyncResult(
            updated: [1, 2],
            failed: [3],
            skipped: [4, 5, 6]
        );

        $this->assertSame(6, $result->totalCount());
    }

    public function testIsCompleteSuccess_EmptyFailedWithNonEmptySkipped_ReturnsTrue(): void
    {
        // The behavior this whole issue is about: a skip-only outcome (car
        // no longer owned mid-sync, not a real error) must read as complete
        // success — isCompleteSuccess() is keyed on $failed alone.
        $result = new OwnerSyncResult(updated: [1], failed: [], skipped: [2, 3]);

        $this->assertTrue($result->isCompleteSuccess());
    }

    public function testIsCompleteSuccess_NonEmptyFailedWithEmptySkipped_ReturnsFalse(): void
    {
        $result = new OwnerSyncResult(updated: [1], failed: [2], skipped: []);

        $this->assertFalse($result->isCompleteSuccess());
    }

    public function testIsCompleteSuccess_NonEmptyFailedWithNonEmptySkipped_ReturnsFalse(): void
    {
        $result = new OwnerSyncResult(updated: [1], failed: [2], skipped: [3]);

        $this->assertFalse($result->isCompleteSuccess());
    }
}
