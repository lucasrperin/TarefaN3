<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit85444947ff8f8aec5baa29960ba777e1
{
    public static $prefixLengthsPsr4 = array (
        'T' => 
        array (
            'Twilio\\' => 7,
        ),
        'G' => 
        array (
            'Guilherme\\TarefaN3\\' => 19,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Twilio\\' => 
        array (
            0 => __DIR__ . '/..' . '/twilio/sdk/src/Twilio',
        ),
        'Guilherme\\TarefaN3\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit85444947ff8f8aec5baa29960ba777e1::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit85444947ff8f8aec5baa29960ba777e1::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit85444947ff8f8aec5baa29960ba777e1::$classMap;

        }, null, ClassLoader::class);
    }
}
