<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * Refreshes a car's denormalized owner-contact columns from the owner's
 * current profile (issue #1962).
 *
 * This exists as a class rather than inline in `app/api/cars/save.php` for one
 * reason: `save.php` cannot be loaded by a test. Every path through it ends in
 * `exit` via {@see ApiResponse::send()}, so any test of the refresh logic that
 * lived there would have to hand-copy it, and a hand-copied test passes just as
 * happily when the production code is deleted. Keeping the logic here lets the
 * tests call the same code the endpoint calls.
 *
 * @see \ElanRegistry\Owner::ownerContactFields() for the nine-column list
 * @see \ElanRegistry\Owner::syncOwnerFieldsToCars() the profile-save path,
 *      which writes the same bundle but persists it differently (see
 *      "Divergence from the sync path" below)
 */
class OwnerContactRefresher
{
    /**
     * Columns that are owner-level on the sync path but still per-car here.
     *
     * `website` has its own form input on the car-edit page
     * (`app/owner/cars/edit.php:370-374`), so a mechanical refresh would clobber
     * a value the member just typed. Issue #1963 makes `website` owner-level and
     * removes that input; when it lands, delete this constant and the
     * `array_diff_key()` below with it.
     *
     * @var array<string, true>
     */
    private const PER_CAR_FIELDS = ['website' => true];

    /**
     * Merge the owner's current contact values over a car's existing details.
     *
     * The caller is responsible for having built `$cardetails` from the CAR's
     * database row and for passing the Owner constructed from that row's
     * `user_id` — never from the logged-in session user. An admin or editor may
     * be editing another member's car, and using the session user would write
     * staff's contact details onto the member's record. This method cannot
     * enforce that itself: by the time it is called, both arguments are already
     * chosen. {@see \ElanRegistry\Owner} carries no notion of "who is logged
     * in", which is what makes the mistake possible upstream and invisible
     * here.
     *
     * Returns `$cardetails` unchanged when the owner failed to load, so a car
     * with a dangling or null `user_id` stays editable and keeps whatever
     * contact data it already holds rather than being blanked. Callers that
     * want to log or surface that case should test {@see hasLoadableOwner()}
     * first — this method deliberately does not log, so that it stays pure and
     * callable from a test without a database-backed logger.
     *
     * Divergence from the sync path: {@see Owner::syncOwnerFieldsToCars()}
     * writes its bundle straight through `CarRepository::updateCarForOwner()`,
     * which persists blanks — clearing your city there clears it on your cars.
     * The edit path routes through `Car::update()`, whose `array_filter` drops
     * `''`/`null` for any field not in `Car::CLEARABLE_FIELDS`, so blank owner
     * values are silently no-ops here. The asymmetry is deliberate but easy to
     * trip over; see the tests in `CarEditOwnerColumnRefreshTest`.
     *
     * Never writes `mtime` or `owner_last_updated`. `ownerContactFields()`
     * excludes both, and `owner_last_updated` in particular drives verification
     * -email eligibility (#1873/#1953) — a mechanical refresh must not reset
     * that clock. `Car::update()` handles it separately for genuine owner
     * self-edits.
     *
     * @param array<string, mixed> $cardetails Car details built from the car's
     *                                         DB row.
     * @param Owner                $carOwner   Owner constructed from that row's
     *                                         `user_id`.
     * @return array<string, mixed> `$cardetails` with the owner-contact columns
     *                              refreshed, or unchanged if the owner did not
     *                              load.
     */
    public function refresh(array $cardetails, Owner $carOwner): array
    {
        if (!$this->hasLoadableOwner($carOwner)) {
            return $cardetails;
        }

        $ownerFields = array_diff_key($carOwner->ownerContactFields(), self::PER_CAR_FIELDS);

        foreach ($ownerFields as $key => $value) {
            $cardetails[$key] = $value;
        }

        return $cardetails;
    }

    /**
     * Whether the owner loaded, and so whether {@see refresh()} will do
     * anything.
     *
     * Split out so the endpoint can log the skip (it has the car ID and the
     * session user for the log line; this class has neither) without
     * duplicating the null test.
     *
     * Calling `refresh()` without checking this first is **safe, not a bug**:
     * `refresh()` performs the same test internally and returns the details
     * untouched. The only consequence of skipping this call is that the skip
     * goes unlogged — an orphaned or null `user_id` is a data-integrity
     * problem worth a log line, so the endpoint checks. Read the pair as
     * "ask if you want to say something about it", never as "guard required
     * or the merge will blank the car's columns".
     */
    public function hasLoadableOwner(Owner $carOwner): bool
    {
        return $carOwner->data() !== null;
    }
}
