<?php
declare(strict_types=1);

namespace ElanRegistry;

/**
 * Per-car outcome of Owner::syncOwnerFieldsToCars().
 *
 * Carries the car IDs that were successfully synchronized, those that were
 * skipped, and those that failed, so a caller can report "updated on 5 of 6
 * cars — car 2107 could not be updated" without re-querying or re-deriving
 * counts.
 *
 * A car counts as updated when its row was left holding the owner's current
 * values — including the common case where the row already matched and no
 * UPDATE was needed. A car counts as skipped when ownership changed mid-sync:
 * the car left this owner between the initial car list snapshot and the
 * write, so the previous owner's data was correctly not written — this is
 * expected behavior, not an error. A car counts as failed when its
 * transaction was rolled back for a real error: the history insert failed or
 * the UPDATE itself errored.
 *
 * @author Jim Boone
 */
final class OwnerSyncResult
{
    /**
     * @param list<int> $updated Car IDs left holding the owner's current values
     * @param list<int> $failed  Car IDs whose sync was rolled back due to a real error
     * @param list<int> $skipped Car IDs no longer owned by this owner mid-sync — not a failure
     */
    public function __construct(
        public readonly array $updated = [],
        public readonly array $failed = [],
        public readonly array $skipped = []
    ) {
    }

    /**
     * Number of cars successfully synchronized.
     *
     * @return int
     */
    public function updatedCount(): int
    {
        return count($this->updated);
    }

    /**
     * Number of cars whose sync was rolled back.
     *
     * @return int
     */
    public function failedCount(): int
    {
        return count($this->failed);
    }

    /**
     * Number of cars skipped because ownership changed mid-sync.
     *
     * @return int
     */
    public function skippedCount(): int
    {
        return count($this->skipped);
    }

    /**
     * Total number of cars the sync attempted.
     *
     * @return int
     */
    public function totalCount(): int
    {
        return count($this->updated) + count($this->failed) + count($this->skipped);
    }

    /**
     * Human-readable phrase naming the cars that could not be updated.
     *
     * Singular: "Car 2107 could not be updated."
     * Plural:   "Cars 5, 12 could not be updated."
     *
     * Lives here so the two call sites that report a partial sync cannot drift
     * apart or reintroduce the "Car 5, 12" mis-pluralization.
     *
     * @return string Empty string when nothing failed
     */
    public function failedCarsPhrase(): string
    {
        if ($this->failed === []) {
            return '';
        }

        $noun = count($this->failed) === 1 ? 'Car' : 'Cars';

        return sprintf('%s %s could not be updated.', $noun, implode(', ', $this->failed));
    }

    /**
     * Human-readable phrase naming the cars skipped because ownership changed mid-sync.
     *
     * Singular: "Car 2107 no longer owned; not updated."
     * Plural:   "Cars 5, 12 no longer owned; not updated."
     *
     * Lives here so callers that want to mention skips informationally cannot
     * drift apart or reintroduce the "Car 5, 12" mis-pluralization.
     *
     * @return string Empty string when nothing was skipped
     */
    public function skippedCarsPhrase(): string
    {
        if ($this->skipped === []) {
            return '';
        }

        $noun = count($this->skipped) === 1 ? 'Car' : 'Cars';

        return sprintf('%s %s no longer owned; not updated.', $noun, implode(', ', $this->skipped));
    }

    /**
     * Whether every attempted car synchronized successfully.
     *
     * True for an owner with no cars at all — nothing was attempted, so nothing failed.
     *
     * @return bool
     */
    public function isCompleteSuccess(): bool
    {
        return $this->failed === [];
    }
}
