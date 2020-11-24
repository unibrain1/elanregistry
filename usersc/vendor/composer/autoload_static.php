<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2b446c5be6c93568806ecf6e2e252d46
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'SecureEnvPHP\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'SecureEnvPHP\\' => 
        array (
            0 => __DIR__ . '/..' . '/johnathanmiller/secure-env-php/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit2b446c5be6c93568806ecf6e2e252d46::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit2b446c5be6c93568806ecf6e2e252d46::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit2b446c5be6c93568806ecf6e2e252d46::$classMap;

        }, null, ClassLoader::class);
    }
}
