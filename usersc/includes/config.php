<?php
declare(strict_types=1);

use ElanRegistry\AssetVersionResolver;

/**
 * Global Application Configuration
 *
 * Centralized configuration constants for the Elan Registry application.
 * This file defines paths, directories, retention policies, and other
 * application-wide settings.
 *
 * @version 2.15.1
 */

// ============================================================================
// Backup Configuration
// ============================================================================

/**
 * Base backup directory (relative to $us_url_root)
 * Backups are organized into subdirectories: automated/, manual/, rollback/
 */
define('BACKUP_BASE_DIR', 'backups/');

/**
 * Backup retention policies (in days)
 * Controls how long each type of backup is kept before automatic cleanup
 */
define('BACKUP_RETENTION_AUTOMATED', 7);    // Automated backups: 7 days
define('BACKUP_RETENTION_MANUAL', 30);      // Manual backups: 30 days
define('BACKUP_RETENTION_ROLLBACK', 30);    // Rollback backups: 30 days

/**
 * Backup cleanup warning threshold (in days)
 * Warns admins when backups are approaching expiration
 */
define('BACKUP_WARNING_THRESHOLD_DAYS', 7);

/**
 * Backup failure lookback window (in days)
 * How far back the health check looks for logged backup failures
 */
define('BACKUP_FAILURE_LOOKBACK_DAYS', 7);

/**
 * Backup table subdirectories
 * Organized by backup type for clarity and management
 */
define('BACKUP_DIR_AUTOMATED', BACKUP_BASE_DIR . 'automated/');
define('BACKUP_DIR_MANUAL', BACKUP_BASE_DIR . 'manual/');
define('BACKUP_DIR_ROLLBACK', BACKUP_BASE_DIR . 'rollback/');

// ============================================================================
// Asset Versioning
// ============================================================================

// Appended as ?v=<version> to all first-party .min.js/.min.css URLs for cache-busting.
// Reads the VERSION file written by the post-receive deploy hook.
// Resolution logic lives in AssetVersionResolver so it's unit-testable
// directly (see #1598) — config.php only builds the path and defines the constant.
$_versionFile = $abs_us_root . $us_url_root . 'VERSION';
define('ASSET_VERSION', AssetVersionResolver::resolve($_versionFile));
unset($_versionFile);

