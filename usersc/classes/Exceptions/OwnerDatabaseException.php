<?php

declare(strict_types=1);

namespace ElanRegistry\Exceptions;

use ElanRegistry\LogCategories;

/**
 * OwnerDatabaseException
 *
 * Exception thrown when owner-related database operations fail.
 * Used for query, transaction, and rollback failures across owner-related
 * data access operations. Mirrors CarDatabaseException's shape — no
 * OwnerException abstract base exists yet (see #1654), so this extends
 * ElanRegistryException directly, matching the other Owner exception
 * classes in this file's package.
 *
 * @package ElanRegistry
 * @subpackage Exceptions
 * @since v2.29.5
 */
class OwnerDatabaseException extends ElanRegistryException
{
    /**
     * @inheritDoc
     */
    protected static function getDefaultUserMessage(): string
    {
        return "A database error occurred while processing the owner record.";
    }

    /**
     * @inheritDoc
     */
    protected static function getDefaultLogCategory(): string
    {
        return LogCategories::LOG_CATEGORY_DATABASE_ERROR;
    }
}
