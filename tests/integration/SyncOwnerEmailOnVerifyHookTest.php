<?php

declare(strict_types=1);

require_once __DIR__ . '/IntegrationTestCase.php';

use ElanRegistry\DatabaseInterface;
use ElanRegistry\Exceptions\CarDatabaseException;
use ElanRegistry\Owner;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for Issue #1958: a confirmed email change via
 * users/verify.php's confirm-by-link flow wasn't syncing to cars.email.
 *
 * The fix is usersc/plugins/hooker/hooks/sync_owner_email_on_verify.php, a
 * hooker plugin hook registered on the `verifySuccess` event. It is a plain
 * included script (not a class or function) that runs inside
 * users/verify.php's own scope, relying on a `global $verify` (a User
 * instance) already being set by that page.
 *
 * This suite is the DB-backed half of the hook's coverage. It validates the
 * sync mechanics the hook depends on against a real database: a fixture user
 * whose users.email has just changed (mirroring what users/verify.php does
 * immediately before firing the verifySuccess hooks — see verify.php's
 * `$verify->update(['email' => $verify->data()->email_new, ...])` call sites)
 * followed by syncOwnerFieldsToCars(), asserting cars.email updates for every
 * owned car, and that the two exception types the hook's catch clauses name
 * are the ones syncOwnerFieldsToCars() can actually throw.
 *
 * The hook FILE's own control flow — its run-once-per-request guard, its
 * OwnerSyncResult partial-failure logging, and which LogCategories constant
 * each catch branch uses — is covered separately and directly in
 * tests/unit/security/SyncOwnerEmailOnVerifyHookTest.php, which `require`s
 * the hook file itself with a stubbed `$verify` and a fake DatabaseInterface.
 * (An earlier version of this docblock claimed the hook could not be tested
 * as a black box, citing a CLAUDE.md rule that does not exist; that unit
 * suite disproves the claim. The hook needs no part of verify.php — only a
 * `$verify` global and an Owner.)
 *
 * This does not duplicate OwnerSyncOwnerFieldsToCarsTest.php (#1873), which
 * already covers syncOwnerFieldsToCars()'s general nine-field behavior and
 * business rules at the Owner class level in detail. This file exists solely
 * to pin the specific sequence the hook relies on — a confirmed
 * users.email change followed by a sync — as the regression guard for #1958.
 *
 * The negative path (syncOwnerFieldsToCars() throwing) reuses the same
 * DatabaseInterface proxy pattern established in
 * OwnerSyncOwnerFieldsToCarsFailureTest.php (#1873) to force a genuine
 * per-car UPDATE failure.
 *
 * Manual/Playwright verification of the real confirm-by-link flow end to end
 * (clicking an actual emailed link) is documented, not automated.
 *
 * ---------------------------------------------------------------------------
 * MANUAL VERIFICATION STEPS (not automatable — see rationale below)
 * ---------------------------------------------------------------------------
 * No existing Playwright pattern drives UserSpice's email confirmation links
 * end-to-end (checked tests/playwright/ — the auto-auth pattern in
 * logged-in.spec.js authenticates via TEST_USERNAME/TEST_PASSWORD, it does
 * not retrieve or click emailed confirmation links). Per EMAIL_SYSTEM.md,
 * local outbound mail is captured via Mailtrap (not Mailhog/Mailpit), which
 * has no documented API/fixture hook in this repo for a Playwright test to
 * pull the confirmation link from programmatically. Forcing an automated
 * path here would mean either scraping Mailtrap's web UI (fragile, not worth
 * it for a one-off hook) or bypassing the real email step entirely (which
 * would stop testing the thing #1958 is actually about). So this is
 * documented as a manual check instead:
 *
 *   1. Ensure local Mailtrap SMTP credentials are configured (Admin →
 *      Settings → Email) per EMAIL_SYSTEM.md's "Local Development Testing"
 *      section.
 *   2. Log in as a test owner who owns at least one car.
 *   3. Go to Account Settings and change the email address. This stages
 *      email_new and sends a confirmation email (users.email is NOT changed
 *      yet at this point — confirm cars.email is still the OLD address).
 *   4. Open the Mailtrap inbox and retrieve the confirmation link from the
 *      captured email.
 *   5. Click the confirmation link (or paste its URL into a browser where
 *      the same session is logged in).
 *   6. Confirm the success page renders (proving verify.php reached the
 *      verifySuccess branch).
 *   7. Query the database directly: SELECT email FROM cars WHERE user_id =
 *      <test user id>; — confirm it now reflects the NEW address for every
 *      car the owner has.
 *   8. Check the `logs` table for any LOG_CATEGORY_DATABASE_ERROR row
 *      mentioning `sync_owner_email_on_verify` — there should be none on a
 *      successful run.
 *
 * @see usersc/plugins/hooker/hooks/sync_owner_email_on_verify.php
 * @see usersc/classes/Owner.php Owner::syncOwnerFieldsToCars()
 * @see tests/integration/OwnerSyncOwnerFieldsToCarsTest.php
 * @see tests/integration/OwnerSyncOwnerFieldsToCarsFailureTest.php
 */
#[Group('integration')]
#[Group('owner')]
final class SyncOwnerEmailOnVerifyHookTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->requireDatabase();
    }

    /**
     * A DatabaseInterface proxy backed by the real connection, except that
     * query() reports a database error for the specific
     * `UPDATE cars SET ... WHERE id = ? AND user_id = ?` call issued by
     * CarRepository::updateCarForOwner() — forcing that call to throw
     * CarDatabaseException, exactly as a genuine deadlock or constraint
     * violation would. Mirrors
     * OwnerSyncOwnerFieldsToCarsFailureTest::dbFailingUpdateCarForOwner()
     * exactly; duplicated here (rather than shared) because that class is
     * `final` with a private helper, and this suite deliberately covers the
     * hook's own catch semantics rather than extending that class's fixture.
     */
    private function dbFailingUpdateCarForOwner(): DatabaseInterface
    {
        $real = $this->db;
        return new class ($real) implements DatabaseInterface {
            private bool $lastCallFailed = false;

            public function __construct(private DatabaseInterface $real)
            {
            }

            public function query(string $sql, array $params = []): self
            {
                $this->lastCallFailed = str_starts_with($sql, 'UPDATE cars SET')
                    && str_ends_with($sql, 'WHERE id = ? AND user_id = ?');

                if (!$this->lastCallFailed) {
                    $this->real->query($sql, $params);
                }

                return $this;
            }
            public function get(string $table, array $where): self|false
            {
                $result = $this->real->get($table, $where);
                return $result === false ? false : $this;
            }
            public function insert(string $table, array $fields = [], bool $update = false): bool
            {
                return $this->real->insert($table, $fields, $update);
            }
            public function update(string $table, array|int $id, array $fields): bool
            {
                return $this->real->update($table, $id, $fields);
            }
            public function delete(string $table, array|int $where): self|false
            {
                $result = $this->real->delete($table, $where);
                return $result === false ? false : $this;
            }
            public function error(): bool
            {
                return $this->lastCallFailed || $this->real->error();
            }
            public function errorString(): string
            {
                return $this->lastCallFailed ? 'simulated deadlock' : $this->real->errorString();
            }
            public function errorInfo(): array
            {
                return $this->real->errorInfo();
            }
            public function count(): int
            {
                return $this->real->count();
            }
            public function first(bool $assoc = false): array|object
            {
                return $this->real->first($assoc);
            }
            public function results(bool $assoc = false): array
            {
                return $this->real->results($assoc);
            }
            public function lastId(): int
            {
                return $this->real->lastId();
            }
            public function beginTransaction(): bool
            {
                return $this->real->beginTransaction();
            }
            public function commit(): bool
            {
                return $this->real->commit();
            }
            public function rollBack(): bool
            {
                return $this->real->rollBack();
            }
            public function inTransaction(): bool
            {
                return $this->real->inTransaction();
            }
        };
    }

    /**
     * Core positive-path test for #1958: construct a real Owner for a user
     * whose users.email has just changed, call syncOwnerFieldsToCars(), and
     * confirm cars.email reflects the new address for every car the owner
     * has. Uses two cars to also confirm the sync is not scoped to a single
     * car.
     */
    public function testConfirmedEmailChangeSyncsToAllOwnedCars(): void
    {
        $userId = $this->createTestUser(['email' => 'old-address@example.com']);
        $carId1 = $this->createTestCar($userId, ['email' => 'old-address@example.com']);
        $carId2 = $this->createTestCar($userId, ['email' => 'old-address@example.com']);

        // Mirrors users/verify.php's own confirm-by-link write: users.email
        // is updated to the previously-staged email_new value immediately
        // before the verifySuccess hooks fire.
        $this->db->query("UPDATE users SET email = ? WHERE id = ?", ['new-address@example.com', $userId]);

        // This is the hook's entire body, reduced to its essential sequence
        // (see class docblock) — $userId here stands in for
        // (int) $verify->data()->id in the real hook.
        $owner = new Owner($userId);
        $result = $owner->syncOwnerFieldsToCars();

        $this->assertTrue($result->isCompleteSuccess(), 'The sync must succeed for both owned cars');
        $this->assertSame([$carId1, $carId2], $result->updated);

        foreach ([$carId1, $carId2] as $carId) {
            $car = $this->db->query("SELECT email FROM cars WHERE id = ?", [$carId])->first();
            $this->assertNotNull($car);
            $this->assertSame(
                'new-address@example.com',
                $car->email,
                "cars.email for car {$carId} must reflect the confirmed new address"
            );
        }
    }

    /**
     * Confirms the negative path the hook's catch block exists for: when
     * syncOwnerFieldsToCars() throws (a genuine per-car UPDATE failure, not
     * the row-count ambiguity — see OwnerSyncOwnerFieldsToCarsFailureTest's
     * class docblock), it throws CarDatabaseException, which the hook's
     * first catch clause (OwnerDatabaseException | CarDatabaseException)
     * names explicitly. This proves that catch clause is reachable and
     * matches what syncOwnerFieldsToCars() can actually throw against a real
     * database.
     */
    public function testSyncFailureThrowsCarDatabaseExceptionCatchableAsTheHookExpects(): void
    {
        $userId = $this->createTestUser();
        $carId = $this->createTestCar($userId, [
            'chassis' => 'VERIFYHK1',
            'email'   => 'old-address@example.com',
        ]);

        $db = $this->dbFailingUpdateCarForOwner();
        $owner = $this->ownerWithLoadedData($db, [
            'id'      => $userId,
            'fname'   => 'Test',
            'lname'   => 'User',
            'email'   => 'new-address@example.com',
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => null,
            'lon'     => null,
            'website' => '',
        ]);

        $thrown = null;
        try {
            $owner->syncOwnerFieldsToCars();
        } catch (\ElanRegistry\Exceptions\OwnerDatabaseException | CarDatabaseException $e) {
            // Exactly the combined catch clause the hook uses.
            $thrown = $e;
        }

        $this->assertNotNull(
            $thrown,
            'syncOwnerFieldsToCars() must throw a type caught by the hook\'s '
            . 'OwnerDatabaseException | CarDatabaseException clause — otherwise '
            . 'the hook\'s first catch is unreachable dead code'
        );
        $this->assertInstanceOf(
            CarDatabaseException::class,
            $thrown,
            'A genuine per-car UPDATE failure must surface as CarDatabaseException specifically'
        );

        // Confirm the car's email was NOT actually changed — the per-car
        // transaction rolled back before the exception propagated.
        $car = $this->db->query("SELECT email FROM cars WHERE id = ?", [$carId])->first();
        $this->assertNotNull($car);
        $this->assertSame(
            'old-address@example.com',
            $car->email,
            'cars.email must remain unchanged when the underlying UPDATE fails'
        );
    }

    /**
     * Confirms the hook's second catch clause (\Throwable) is exercisable
     * too: the comment on both the hook and its user_settings.php precedent
     * explains that syncOwnerFieldsToCars() builds its field bundle from the
     * Owner's untyped $_data properties, so a malformed value could surface
     * as a TypeError-family Error rather than one of the two named
     * application exception classes. PHP's Error hierarchy (TypeError et
     * al.) does not extend Exception, which is exactly why the hook's second
     * catch clause must be `\Throwable` and not `\Exception`.
     *
     * The real DB layer (users/classes/DB.php, via PDO::bindValue()) turns
     * out to coerce a non-scalar bound parameter with only an "Array to
     * string conversion" warning rather than throwing — confirmed by direct
     * experimentation before writing this test — so a genuine \TypeError
     * cannot be forced through that path with today's implementation. This
     * test instead uses a minimal DatabaseInterface stub whose query() call
     * throws a bare \TypeError directly, the same way
     * OwnerSyncOwnerFieldsToCarsFailureTest's proxies simulate a
     * CarDatabaseException-causing DB failure — proving the hook's
     * \Throwable clause, not \Exception, is what is required to catch this
     * class of failure, and that it is reachable through the exact call
     * syncOwnerFieldsToCars() makes.
     */
    public function testMalformedOwnerDataThrowsThrowableCatchableAsTheHookExpects(): void
    {
        $db = new class implements DatabaseInterface {
            /**
             * Call counter, incremented by every method below. Its only purpose
             * is to give each otherwise-constant stub body a genuine side
             * effect, matching the @phpstan-impure contract DatabaseInterface
             * declares for these methods — this stub is never asked to report
             * an accurate count anywhere in this test.
             */
            private int $calls = 0;

            public function query(string $sql, array $params = []): self
            {
                $this->calls++;
                // Simulates a TypeError-family Error surfacing from deep in the
                // DB layer when handed a malformed, untyped value — the class
                // of failure the hook's own comment calls out by name.
                throw new \TypeError('simulated: bindValue() received a non-scalar value');
            }
            public function get(string $table, array $where): self
            {
                $this->calls++;
                return $this;
            }
            public function insert(string $table, array $fields = [], bool $update = false): bool
            {
                $this->calls++;
                return true;
            }
            public function update(string $table, array|int $id, array $fields): bool
            {
                $this->calls++;
                return true;
            }
            public function delete(string $table, array|int $where): self
            {
                $this->calls++;
                return $this;
            }
            public function error(): bool
            {
                $this->calls++;
                return false;
            }
            public function errorString(): string
            {
                $this->calls++;
                return '';
            }
            public function errorInfo(): array
            {
                $this->calls++;
                return [];
            }
            public function count(): int
            {
                $this->calls++;
                return 0;
            }
            public function first(bool $assoc = false): array
            {
                $this->calls++;
                return [];
            }
            public function results(bool $assoc = false): array
            {
                $this->calls++;
                return [];
            }
            public function lastId(): int
            {
                $this->calls++;
                return 0;
            }
            public function beginTransaction(): bool
            {
                $this->calls++;
                return true;
            }
            public function commit(): bool
            {
                $this->calls++;
                return true;
            }
            public function rollBack(): bool
            {
                $this->calls++;
                return true;
            }
            public function inTransaction(): bool
            {
                $this->calls++;
                return false;
            }
        };

        $owner = $this->ownerWithLoadedData($db, [
            'id'      => 1,
            'fname'   => 'Test',
            'lname'   => 'User',
            'email'   => 'new-address@example.com',
            'city'    => 'Portland',
            'state'   => 'Oregon',
            'country' => 'United States',
            'lat'     => null,
            'lon'     => null,
            'website' => '',
        ]);

        $thrown = null;
        try {
            $owner->syncOwnerFieldsToCars();
        } catch (\ElanRegistry\Exceptions\OwnerDatabaseException | CarDatabaseException $e) {
            $this->fail(
                'Expected a bare \\Throwable (\\TypeError) to propagate untouched, not '
                . 'be wrapped as one of the application exceptions — got ' . get_class($e)
            );
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertInstanceOf(
            \TypeError::class,
            $thrown,
            'A bare \\TypeError from the DB layer must propagate to the caller uncaught by '
            . 'syncOwnerFieldsToCars()/getCarsOwned() — it is exactly the class of failure the '
            . "hook's second catch clause (\\Throwable, not \\Exception) exists for, since "
            . "PHP's Error hierarchy does not extend Exception"
        );
    }
}
