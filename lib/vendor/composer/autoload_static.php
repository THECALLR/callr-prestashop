<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitfd553ac237c0373a3977fab99824c06b
{
    public static $prefixLengthsPsr4 = array (
        'G' => 
        array (
            'Giggsey\\Locale\\' => 15,
        ),
        'C' => 
        array (
            'CALLR\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Giggsey\\Locale\\' => 
        array (
            0 => __DIR__ . '/..' . '/giggsey/locale/src',
        ),
        'CALLR\\' => 
        array (
            0 => __DIR__ . '/..' . '/callr/sdk-php/src/CALLR',
        ),
    );

    public static $prefixesPsr0 = array (
        'l' => 
        array (
            'libphonenumber' => 
            array (
                0 => __DIR__ . '/..' . '/giggsey/libphonenumber-for-php/src',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitfd553ac237c0373a3977fab99824c06b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitfd553ac237c0373a3977fab99824c06b::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInitfd553ac237c0373a3977fab99824c06b::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
