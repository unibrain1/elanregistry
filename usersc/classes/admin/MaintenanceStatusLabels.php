<?php
declare(strict_types=1);

namespace ElanRegistry\Admin;

/**
 * Pure label-selection logic for maintenance.php's backup-attention chip and
 * alert (#1225). Extracted from inline match(true) expressions so the
 * 3-way precedence (unknown > failure > cleanup) is directly unit-testable
 * instead of only reachable via a full page render.
 */
final class MaintenanceStatusLabels
{
    /**
     * Label for the header warning chip (badge-lg). Shorter, action-oriented.
     */
    public static function chipLabel(bool $backupStatusUnknown, bool $backupFailureDetected): string
    {
        return match (true) {
            $backupStatusUnknown => 'Backup Status Unavailable',
            $backupFailureDetected => 'Backup Failure Detected',
            default => 'Backup Cleanup Needed',
        };
    }

    /**
     * Heading for the full backup-attention alert box. Same precedence as
     * chipLabel(), slightly different wording for the longer-form context.
     */
    public static function alertHeading(bool $backupStatusUnknown, bool $backupFailureDetected): string
    {
        return match (true) {
            $backupStatusUnknown => 'Backup Status Unavailable',
            $backupFailureDetected => 'Backup Failure Detected',
            default => 'Backup Cleanup Recommended',
        };
    }
}
