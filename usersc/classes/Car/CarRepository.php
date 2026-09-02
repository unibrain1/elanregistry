<?php

declare(strict_types=1);

namespace ElanRegistry\Car;

use ElanRegistry\DatabaseInterface;
use ElanRegistry\Exceptions\CarDatabaseException;
use ElanRegistry\Exceptions\CarNotFoundException;
use ElanRegistry\LogCategories;

/**
 * CarRepository - Database access layer for car operations
 *
 * Extracted from Car.php to provide a focused, testable data access layer.
 * Wraps DB operations for cars, cars_hist, elan_factory_info, and car_models tables.
 *
 * @package ElanRegistry\Car
 * @since v2.15.0
 * @see https://github.com/unibrain1/elanregistry/issues/463
 */
class CarRepository
{
    /** @var array<string, string> Factory suffix code to description mapping */
    private const SUFFIX_MAP = [
        'A' => 'S4 FHC UK Market',
        'B' => 'S4 FHC Export',
        'C' => 'S4 DHC UK Market',
        'D' => 'S4 DHC Export',
        'E' => 'S4 S/E FHC UK Market',
        'F' => 'S4 S/E FHC Export',
        'G' => 'S4 S/E DHC UK Market',
        'H' => 'S4 S/E DHC Export',
        'J' => 'S4 FHC Federal',
        'K' => 'S4 DHC Federal',
        'L' => '+2S and +2S/130 UK Market',
        'M' => '+2S and +2S/130 Export',
        'N' => '+2S and +2S/130 Federal',
    ];

    private bool $transactionOwner = false;

    public function __construct(private DatabaseInterface $db) {}

    /**
     * Find a car by ID
     *
     * @param int $carId Car ID
     * @return object|null Car data object or null if not found
     * @throws CarDatabaseException If the query fails
     */
    public function findById(int $carId): ?object
    {
        $data = $this->db->get('cars', ['id', '=', $carId]);
        if ($data === false) {
            throw new CarDatabaseException("Failed to look up car $carId");
        }
        if ($data->count() === 0) {
            return null;
        }
        $result = $data->first();
        return is_object($result) ? $result : null;
    }

    /**
     * Find a car by ID and lock the row for the duration of the current transaction.
     * Must be called inside an active transaction (InnoDB SELECT...FOR UPDATE).
     *
     * @throws CarDatabaseException If query fails
     */
    public function findByIdForUpdate(int $carId): ?object
    {
        $this->db->query('SELECT * FROM cars WHERE id = ? FOR UPDATE', [$carId]);
        if ($this->db->error()) {
            throw new CarDatabaseException("Failed to lock car $carId for update");
        }
        if ($this->db->count() === 0) {
            return null;
        }
        $result = $this->db->first();
        return is_object($result) ? $result : null;
    }

    /**
     * Insert a new car record
     *
     * @param array<string, mixed> $fields Field values
     * @return bool True on success
     */
    public function insertCar(array $fields): bool
    {
        return $this->db->insert('cars', $fields);
    }

    /**
     * Update an existing car record
     *
     * @param int $carId Car ID
     * @param array<string, mixed> $fields Field values
     * @return bool True on success
     */
    public function updateCar(int $carId, array $fields): bool
    {
        return $this->db->update('cars', $carId, $fields);
    }

    /**
     * Delete a car by ID
     *
     * @param int $carId Car ID
     * @return bool True on success; false if the query itself fails (caller should treat as DB error)
     * @throws CarNotFoundException If no car with $carId exists (0 rows affected)
     */
    public function deleteCar(int $carId): bool
    {
        // Intentionally asymmetric: a query-level error returns false (caller decides how to
        // surface it) while a zero-rows result throws CarNotFoundException (semantically "the car
        // is gone"). CarAdministrationService wraps false in CarDatabaseException and lets
        // CarNotFoundException propagate so callers can distinguish the two failure modes.
        $this->db->query("DELETE FROM cars WHERE id = ?", [$carId]);
        if ($this->db->error()) {
            return false;
        }
        if ($this->db->count() === 0) {
            throw new CarNotFoundException("Car $carId not found for deletion");
        }
        return true;
    }

    /**
     * Bulk-reassign cars.user_id for all cars owned by a user.
     *
     * Used by the deletion hook to transfer a deleted user's cars to another user
     * (or set them ownerless) in a single UPDATE. Returns the number of rows changed.
     *
     * On database error, logs via LOG_CATEGORY_DATABASE_ERROR and throws so that
     * any enclosing transaction can roll back rather than commit over a partial state.
     *
     * @param int      $fromUserId Source user whose cars are being reassigned
     * @param int|null $toUserId   Target user, or null to clear ownership (user_id = NULL)
     * @return int                 Rows affected by the UPDATE (rows where user_id actually changed; 0 if no match or value already equal to target)
     * @throws CarDatabaseException If the UPDATE fails
     */
    public function reassignCarsByUser(int $fromUserId, ?int $toUserId): int
    {
        $this->db->query(
            'UPDATE cars SET user_id = ? WHERE user_id = ?',
            [$toUserId, $fromUserId]
        );

        if ($this->db->error()) {
            $target = $toUserId ?? 'NULL';
            $msg = "CarRepository::reassignCarsByUser failed (from={$fromUserId} to={$target}): " . $this->db->errorString();
            logger(0, LogCategories::LOG_CATEGORY_DATABASE_ERROR, $msg);
            throw new CarDatabaseException($msg);
        }

        return $this->db->count();
    }

    /**
     * Update the verification code for a car
     *
     * @param int $carId Car ID
     * @param string $verificationCode Verification code to set
     * @return bool True on success
     */
    public function updateVerificationCode(int $carId, string $verificationCode): bool
    {
        return $this->updateCar($carId, ['vericode' => $verificationCode]);
    }

    /**
     * Update the last-verified timestamp for a car
     *
     * @param int $carId Car ID
     * @param string $dateTime Datetime string in AppConstants::DATETIME_FORMAT
     * @return bool True on success
     */
    public function updateLastVerified(int $carId, string $dateTime): bool
    {
        return $this->updateCar($carId, ['last_verified' => $dateTime]);
    }

    /**
     * Update the timestamp at which a verification email was sent for a car
     *
     * @param int $carId Car ID
     * @param string $dateTime Datetime string in AppConstants::DATETIME_FORMAT
     * @return bool True on success
     */
    public function updateVerificationSentAt(int $carId, string $dateTime): bool
    {
        return $this->updateCar($carId, ['vericode_sent_at' => $dateTime]);
    }

    /**
     * Update the email-bounced flag for a car
     *
     * @param int $carId Car ID
     * @param bool $bounced True if the owner's email address bounced
     * @return bool True on success
     */
    public function updateEmailBounced(int $carId, bool $bounced): bool
    {
        return $this->updateCar($carId, ['email_bounced' => $bounced ? 1 : 0]);
    }

    /**
     * Update the timestamp at which the owner last updated their car record
     *
     * @param int $carId Car ID
     * @param string $dateTime Datetime string in AppConstants::DATETIME_FORMAT
     * @return bool True on success
     */
    public function updateOwnerLastUpdated(int $carId, string $dateTime): bool
    {
        return $this->updateCar($carId, ['owner_last_updated' => $dateTime]);
    }

    /**
     * Find cars eligible for a verification email, ordered oldest-verified first.
     *
     * A car is eligible when it is not marked sold, has a deliverable email address,
     * its last owner-driven update is more than two years old, and it has either never
     * been verified (last_verified IS NULL) or was last verified more than two years ago.
     *
     * @param int $limit Maximum rows to return (values below 1 return no rows)
     * @param int $offset Rows to skip (negative values are treated as 0)
     * @return array<object> Eligible car rows (empty if none)
     * @throws CarDatabaseException If the query fails
     */
    public function findVerificationEligible(int $limit, int $offset): array
    {
        // LIMIT/OFFSET are interpolated rather than bound: DB::query() binds every
        // parameter as PDO::PARAM_STR, and under emulated prepares that renders
        // LIMIT '10', which MySQL rejects. Casting to int makes injection impossible.
        $limit  = max(0, $limit);
        $offset = max(0, $offset);

        $result = $this->db->query(
            "SELECT * FROM cars
              WHERE (solddate IS NULL OR solddate = '')
                AND email_bounced = 0
                AND email != ''
                AND (last_verified IS NULL OR last_verified < NOW() - INTERVAL 2 YEAR)
                AND COALESCE(owner_last_updated, mtime) < NOW() - INTERVAL 2 YEAR
              ORDER BY last_verified ASC
              LIMIT {$limit} OFFSET {$offset}"
        );
        if ($this->db->error()) {
            throw new CarDatabaseException(
                "CarRepository::findVerificationEligible failed (limit={$limit} offset={$offset}): "
                . $this->db->errorString()
            );
        }
        return $result->results();
    }

    /**
     * Update the sold date for a car
     *
     * @param int $carId Car ID
     * @param string $soldDate Date string in Y-m-d format
     * @return bool True on success
     */
    public function updateSoldDate(int $carId, string $soldDate): bool
    {
        return $this->updateCar($carId, ['solddate' => $soldDate]);
    }

    /**
     * Update the image JSON for a car using compare-and-swap to prevent lost updates.
     *
     * Returns true when exactly 1 row was updated, false when 0 rows matched
     * (indicating a concurrent modification — the caller may retry or raise a conflict error).
     *
     * @param int    $carId        Car ID
     * @param string $newJson      New JSON-encoded image list
     * @param string $expectedJson The image value that must currently be stored (CAS guard)
     * @return bool True if the row was updated, false on concurrent modification
     * @throws CarDatabaseException If the query itself fails
     */
    public function updateImage(int $carId, string $newJson, string $expectedJson): bool
    {
        $this->db->query(
            'UPDATE cars SET image = ? WHERE id = ? AND image = ?',
            [$newJson, $carId, $expectedJson]
        );
        if ($this->db->error()) {
            throw new CarDatabaseException('Image update query failed');
        }
        return $this->db->count() === 1;
    }

    /**
     * Find a car by its composite chassis key (year, type, chassis).
     *
     * @param string $year Model year
     * @param string $type Type code (from CarValidator::parseModel())
     * @param string $chassis Chassis number
     * @return object|null Car data (id, user_id) or null if not found
     * @throws CarDatabaseException If the query fails
     */
    public function findByChassisKey(string $year, string $type, string $chassis): ?object
    {
        $result = $this->db->query(
            'SELECT id, user_id FROM cars WHERE year = ? AND type = ? AND chassis = ?',
            [$year, $type, $chassis]
        );
        if ($this->db->error()) {
            throw new CarDatabaseException(
                "CarRepository::findByChassisKey failed for year={$year} type={$type} chassis={$chassis}: "
                . $this->db->errorString()
            );
        }
        $row = $result->first();
        return is_object($row) ? $row : null;
    }

    /**
     * Find a car by verification code
     *
     * @param string $code Verification code
     * @return object|null Car data or null
     * @throws CarDatabaseException If the query fails
     */
    public function findByVerificationCode(string $code): ?object
    {
        $result = $this->db->query('SELECT * FROM cars WHERE vericode = ?', [$code]);
        if ($this->db->error()) {
            throw new CarDatabaseException(
                "CarRepository::findByVerificationCode failed: " . $this->db->errorString()
            );
        }
        if ($result->count() > 0) {
            return $result->first();
        }
        return null;
    }

    /**
     * Get all cars for sitemap generation
     *
     * @return list<object{id: int, mtime: string}>
     * @throws CarDatabaseException If query fails
     */
    public function getAllForSitemap(): array
    {
        $this->db->query('SELECT id, mtime FROM cars ORDER BY id');
        if ($this->db->error()) {
            throw new CarDatabaseException('Failed to query cars for sitemap generation: ' . $this->db->errorString());
        }
        return $this->db->results();
    }

    /**
     * Find car IDs owned by a specific user
     *
     * @param int $ownerId Owner user ID
     * @return array<object> Array of objects with 'id' property
     * @throws CarDatabaseException If the query fails
     */
    public function findByOwner(int $ownerId): array
    {
        $result = $this->db->query("SELECT id FROM cars WHERE user_id = ?", [$ownerId]);
        if ($this->db->error()) {
            throw new CarDatabaseException(
                "CarRepository::findByOwner failed for user={$ownerId}: " . $this->db->errorString()
            );
        }
        return $result->results();
    }

    /**
     * Get car history records
     *
     * @param int $carId Car ID
     * @return array<object> History records (empty if none)
     * @throws CarDatabaseException If the query fails
     */
    public function getHistory(int $carId): array
    {
        $result = $this->db->query(
            'SELECT id, car_id, ctime, mtime, timestamp, operation,
                    model, series, variant, year, type, chassis, chassis_override, color, engine,
                    purchasedate, solddate, comments, image,
                    fname, join_date, city, state, country, website
             FROM cars_hist WHERE car_id = ? ORDER BY timestamp DESC',
            [$carId]
        );
        if ($this->db->error()) {
            throw new CarDatabaseException(
                "CarRepository::getHistory failed for car={$carId}: " . $this->db->errorString()
            );
        }
        return $result->results();
    }

    /**
     * Insert a history record
     *
     * @param array<string, mixed> $fields History fields
     * @return bool True on success
     */
    public function insertHistory(array $fields): bool
    {
        return $this->db->insert('cars_hist', $fields);
    }

    /**
     * Transfer history records from one car to another
     *
     * @param int $fromCarId Source car ID
     * @param int $toCarId Target car ID
     * @return bool True on success
     */
    public function transferHistory(int $fromCarId, int $toCarId): bool
    {
        $this->db->query("UPDATE cars_hist SET car_id = ? WHERE car_id = ?", [$toCarId, $fromCarId]);
        return !$this->db->error();
    }

    /**
     * Look up factory information by chassis serial number
     *
     * @param string $chassis Full chassis number
     * @param int $suffixLength Length of chassis suffix to try as secondary search
     * @return object|null Factory info object or null
     * @throws CarDatabaseException If a query fails
     */
    public function getFactoryInfo(string $chassis, int $suffixLength): ?object
    {
        $search = [$chassis, substr($chassis, -$suffixLength)];

        foreach ($search as $serialNumber) {
            $factory = $this->db->query('SELECT * FROM elan_factory_info WHERE serial = ? ', [$serialNumber]);
            if ($this->db->error()) {
                throw new CarDatabaseException(
                    "CarRepository::getFactoryInfo failed for serial={$serialNumber}: " . $this->db->errorString()
                );
            }
            if ($factory->count()) {
                return $factory->first();
            }
        }

        return null;
    }

    /**
     * Convert a factory suffix code to descriptive text
     *
     * @param string $suffix Suffix code — single letter, case-insensitive (e.g. 'A' or 'a')
     * @return string Human-readable description, or "Unknown suffix: {suffix}" if the code is not recognised
     */
    public static function suffixToText(string $suffix): string
    {
        $s = strtoupper($suffix);
        return self::SUFFIX_MAP[$s] ?? "Unknown suffix: " . $s;
    }

    /**
     * Get distinct filter options from car_models for the car listing filter pills.
     *
     * Each sub-array contains objects whose property matches the SQL alias:
     * 'series' elements expose ->series, 'types' elements expose ->type,
     * and 'variants' elements expose ->variant.
     *
     * @return array{series: array<object>, types: array<object>, variants: array<object>}
     */
    public function getFilterOptions(): array
    {
        return [
            'series'   => $this->distinctCarModelValues('series_normalized', 'series'),
            'types'    => $this->distinctCarModelValues('type_code', 'type'),
            'variants' => $this->distinctCarModelValues('variant'),
        ];
    }

    /**
     * Return distinct non-empty values for a single car_models column, ordered alphabetically.
     *
     * IMPORTANT: $column and $alias are interpolated directly into SQL without parameterisation.
     * This is safe only because the method is private and every call site uses a string literal.
     * Never pass values derived from request input or runtime configuration.
     *
     * @param string $column Database column name
     * @param string $alias  Result property name; defaults to $column when omitted
     * @return array<object>
     */
    private function distinctCarModelValues(string $column, string $alias = ''): array
    {
        $alias  = $alias ?: $column;
        $result = $this->db->query(
            "SELECT DISTINCT {$column} AS {$alias} FROM car_models"
            . " WHERE {$column} IS NOT NULL AND {$column} != ''"
            . " ORDER BY {$column}"
        );
        if ($this->db->error()) {
            logger(0, LogCategories::LOG_CATEGORY_DATABASE_ERROR, "CarRepository::distinctCarModelValues failed for column={$column}: " . $this->db->errorString());
            return [];
        }
        return $result->results();
    }

    /**
     * Begin a database transaction
     *
     * When participating in an outer transaction (begun by the caller before
     * this repository), this method is a no-op.
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        if ($this->db->inTransaction()) {
            return; // Participating in outer transaction — no-op
        }
        $this->db->beginTransaction();
        $this->transactionOwner = true;
    }

    /**
     * Commit the current transaction
     *
     * When participating in an outer transaction (begun by the caller before
     * this repository), this method is a no-op.
     *
     * @return void
     */
    public function commit(): void
    {
        if (!$this->transactionOwner) {
            return; // Outer transaction manages commit — no-op
        }
        $this->transactionOwner = false;
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    /**
     * Rollback the current transaction
     *
     * When participating in an outer transaction (begun by the caller before
     * this repository), this method is a no-op.
     *
     * @return void
     */
    public function rollback(): void
    {
        if (!$this->transactionOwner) {
            return; // Outer transaction manages rollback — no-op
        }
        $this->transactionOwner = false;
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    /**
     * Get the last inserted ID
     *
     * @return int Last insert ID
     */
    public function lastId(): int
    {
        return $this->db->lastId();
    }

    /**
     * Get the error string from the last operation
     *
     * @return string Error message
     */
    public function errorString(): string
    {
        return $this->db->errorString();
    }

}
