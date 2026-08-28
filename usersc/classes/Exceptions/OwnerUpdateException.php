<?php

declare(strict_types=1);

namespace ElanRegistry\Exceptions;

/**
 * OwnerUpdateException
 *
 * Exception thrown when owner update operations fail.
 * Used when database update errors occur or validation fails during
 * owner record modifications. Log category and HTTP status inherit from
 * OwnerException (#1654).
 *
 * @package ElanRegistry
 * @subpackage Exceptions
 * @since v2.11.0
 */
class OwnerUpdateException extends OwnerException
{
    /**
     * @inheritDoc
     */
    protected static function getDefaultUserMessage(): string
    {
        return "Unable to update the owner record. Please try again.";
    }
}
