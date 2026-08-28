<?php

declare(strict_types=1);

namespace ElanRegistry\Exceptions;

/**
 * OwnerSearchException
 *
 * Exception thrown when owner search operations fail.
 * Used for errors during owner profile searches, data retrieval failures,
 * and quality score calculation errors. Log category and HTTP status
 * inherit from OwnerException (#1654).
 *
 * @package ElanRegistry
 * @subpackage Exceptions
 * @since v2.12.0
 */
class OwnerSearchException extends OwnerException
{
    /**
     * @inheritDoc
     */
    protected static function getDefaultUserMessage(): string
    {
        return "Search failed. Please try again.";
    }
}
