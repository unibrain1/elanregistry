<?php

class ApplicationVersion
{
    public static function get()
    {
        $commitHash = trim(exec('git describe --tags'));

        $commitDate = new \DateTime(trim(exec('git log -n1 --pretty=%ci HEAD')));
        $commitDate->setTimezone(new \DateTimeZone('PST'));

        return sprintf('%s (%s)', $commitHash, $commitDate->format('Y-m-d H:i:s'));
    }
}

// Usage: echo 'MyApplication ' . ApplicationVersion::get();

// MyApplication v1.2.3-dev.b576fd7 (2016-11-02 14:11:22)
