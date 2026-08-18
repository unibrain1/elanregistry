<?php

declare(strict_types=1);

namespace ElanRegistry;

use InvalidArgumentException;

/**
 * Converts database result values (PDO's inconsistent int/string returns for
 * INTEGER columns) to a definite int.
 *
 * Extracted from the global dbInt() in usersc/includes/custom_functions.php
 * (#1599) so unit tests can exercise the real conversion logic directly
 * instead of a hand-copied duplicate in tests/bootstrap-unit.php that could
 * silently drift from production behavior. dbInt() — both the real one and
 * the bootstrap-unit.php stub — now delegates here; this class holds the
 * actual logic and has zero framework dependency, so it loads standalone in
 * the unit tier.
 *
 * @issue 1599
 */
class TypeHelpers
{
    private function __construct() {}

    /**
     * @param mixed  $value    Database result object or scalar value
     * @param string $property Property name to extract from objects (default: 'id')
     * @return int The integer value
     * @throws InvalidArgumentException If the value cannot be converted to int
     */
    public static function toInt(mixed $value, string $property = 'id'): int
    {
        if (is_object($value)) {
            if (!isset($value->$property)) {
                throw new InvalidArgumentException("Property '$property' does not exist on object");
            }
            $value = $value->$property;
        }

        if ($value === null || $value === '') {
            throw new InvalidArgumentException("Cannot convert empty value to int (property: $property)");
        }

        if (!is_numeric($value)) {
            throw new InvalidArgumentException("Cannot convert non-numeric value to int (property: $property): $value");
        }

        return (int) $value;
    }
}
