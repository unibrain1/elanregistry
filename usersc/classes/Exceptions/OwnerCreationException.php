<?php

declare(strict_types=1);

namespace ElanRegistry\Exceptions;

/**
 * OwnerCreationException
 *
 * Exception thrown when owner creation operations fail.
 * Used when database insertion or validation errors occur during
 * new owner record creation. Log category and HTTP status inherit from
 * OwnerException (#1654).
 *
 * @package ElanRegistry
 * @subpackage Exceptions
 * @since v2.11.0
 */
class OwnerCreationException extends OwnerException
{
    /**
     * @inheritDoc
     */
    protected static function getDefaultUserMessage(): string
    {
        return "Unable to create the owner record. Please try again.";
    }
}
