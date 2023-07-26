<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitf096b0d5b045ae9e6e2c147b6730edbd
{
    public static $files = array (
        '3109cb1a231dcd04bee1f9f620d46975' => __DIR__ . '/..' . '/paragonie/sodium_compat/autoload.php',
    );

    public static $prefixLengthsPsr4 = array (
        'F' => 
        array (
            'Firebase\\JWT\\' => 13,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Firebase\\JWT\\' => 
        array (
            0 => __DIR__ . '/..' . '/firebase/php-jwt/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitf096b0d5b045ae9e6e2c147b6730edbd::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitf096b0d5b045ae9e6e2c147b6730edbd::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
