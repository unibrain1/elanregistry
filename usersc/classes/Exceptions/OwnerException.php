<?php

declare(strict_types=1);

namespace ElanRegistry\Exceptions;

use ElanRegistry\LogCategories;

/**
 * OwnerException - Abstract base class for all Owner domain exceptions
 *
 * Provides a domain-specific base for owner-related exceptions,
 * enabling catch blocks to handle all owner errors uniformly.
 * All owner-specific exceptions MUST extend this class.
 *
 * @package ElanRegistry
 * @subpackage Exceptions
 * @since v2.29.5
 * @abstract
 */
abstract class OwnerException extends ElanRegistryException
{
    /**
     * @inheritDoc
     */
    protected static function getDefaultUserMessage(): string
    {
        return "An owner operation error occurred.";
    }

    /**
     * @inheritDoc
     */
    protected static function getDefaultLogCategory(): string
    {
        return LogCategories::LOG_CATEGORY_OWNER_ACTIONS;
    }
}
