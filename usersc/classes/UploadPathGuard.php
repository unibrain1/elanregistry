<?php

declare(strict_types=1);

namespace ElanRegistry;

/**
 * Guards car-image upload paths against directory traversal by confirming a
 * resolved upload destination lies within the configured upload directory.
 *
 * Extracted from app/api/cars/save.php::uploadImages() (#1601) so unit tests
 * can exercise the real guard directly instead of a hand-copied duplicate that
 * could silently drift from production behavior. save.php now calls this class
 * and keeps its own logging and exception handling; the class itself holds only
 * the decision and has zero framework dependency (no logger(), no $user, no
 * project exception classes) — a pure method on a PSR-4-autoloaded class, so
 * it loads standalone in the unit tier.
 *
 * @issue 1601
 */
class UploadPathGuard
{
    private function __construct() {}

    /**
     * Both realpath() calls must succeed. dirname() strips the trailing slash
     * from $filePath and walks up, so for a direct child like /userimages/123/
     * it resolves to /userimages — equal to the canonical target. Equality is
     * therefore valid; only sibling prefixes (e.g. /userimages-other) must be
     * rejected.
     *
     * @param string $targetFilePath Configured upload base directory.
     * @param string $filePath       Candidate upload destination (its parent
     *                                directory is the path actually checked).
     * @return bool True when the path resolves within the target directory,
     *              false when either realpath() fails or the path resolves
     *              outside it.
     */
    public static function isWithinTarget(string $targetFilePath, string $filePath): bool
    {
        $realTargetPath = realpath($targetFilePath);
        $realFilePath = realpath(dirname($filePath));

        if ($realTargetPath === false || $realFilePath === false) {
            return false;
        }

        $canonicalTarget = rtrim($realTargetPath, DIRECTORY_SEPARATOR);

        return $realFilePath === $canonicalTarget
            || str_starts_with($realFilePath, $canonicalTarget . DIRECTORY_SEPARATOR);
    }
}
