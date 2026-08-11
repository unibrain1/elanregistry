<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * Resolves the ASSET_VERSION cache-busting token from the VERSION file
 * written by the post-receive deploy hook.
 *
 * Extracted from usersc/includes/config.php (#1598) so unit tests can
 * exercise the real resolution logic directly instead of a hand-copied
 * duplicate that could silently drift from production behavior. The
 * allow-list regex matches git describe output and prevents XSS if the
 * VERSION file is tampered with; falling back to 'dev' covers absent,
 * empty, or invalid content (expected in dev/CI).
 *
 * Uses error_log() rather than the project's logger() convention: this
 * method runs from config.php during early bootstrap, before the DB
 * connection logger() depends on is available.
 *
 * @issue 1126
 * @issue 1598
 */
class AssetVersionResolver
{
    /**
     * @param string $versionFilePath Absolute path to the VERSION file.
     * @return string The resolved version string, or 'dev' if the file is
     *                 absent, empty, unreadable, or fails the allow-list regex.
     */
    public static function resolve(string $versionFilePath): string
    {
        if (file_exists($versionFilePath)) {
            $contents = file_get_contents($versionFilePath);
            if ($contents === false) {
                error_log('[ElanRegistry] ASSET_VERSION: file_get_contents() failed for ' . $versionFilePath);
                $raw = '';
            } else {
                $raw = trim($contents);
            }
        } else {
            $raw = '';
        }

        return (preg_match('/^[a-zA-Z0-9.\-]+$/', $raw) === 1) ? $raw : 'dev';
    }
}
