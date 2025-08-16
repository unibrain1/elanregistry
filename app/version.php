
<?php

/**
 * ApplicationVersion class
 *
 * Provides a static method to get the current git commit hash and date for versioning.
 * Useful for displaying application version info in the UI or logs.
 */
class ApplicationVersion
{
    /**
     * Returns the current git tag/hash and commit date in formatted string.
     * @return string Version string
     */
    public static function get()
    {
        $commitHash = trim(exec('git describe --tags'));
        $commitDate = new \DateTime(trim(exec('git log -n1 --pretty=%ci HEAD')));
        $commitDate->setTimezone(new \DateTimeZone('PST'));
        return sprintf('%s (%s)', $commitHash, $commitDate->format('Y-m-d H:i:s'));
    }
}

// Usage example:
// echo 'MyApplication ' . ApplicationVersion::get();
