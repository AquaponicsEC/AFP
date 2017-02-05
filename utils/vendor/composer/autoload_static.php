<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit750cee422df365b62416b70ca6714507
{
    public static $files = array (
        '3a37ebac017bc098e9a86b35401e7a68' => __DIR__ . '/..' . '/mongodb/mongodb/src/functions.php',
        '06dd8487319ccd8403765f5b8c9f2d61' => __DIR__ . '/..' . '/alcaeus/mongo-php-adapter/lib/Mongo/functions.php',
    );

    public static $prefixLengthsPsr4 = array (
        'M' => 
        array (
            'MongoDB\\' => 8,
        ),
        'A' => 
        array (
            'Alcaeus\\MongoDbAdapter\\' => 23,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'MongoDB\\' => 
        array (
            0 => __DIR__ . '/..' . '/mongodb/mongodb/src',
        ),
        'Alcaeus\\MongoDbAdapter\\' => 
        array (
            0 => __DIR__ . '/..' . '/alcaeus/mongo-php-adapter/lib/Alcaeus/MongoDbAdapter',
        ),
    );

    public static $prefixesPsr0 = array (
        'M' => 
        array (
            'Mongo' => 
            array (
                0 => __DIR__ . '/..' . '/alcaeus/mongo-php-adapter/lib/Mongo',
            ),
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit750cee422df365b62416b70ca6714507::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit750cee422df365b62416b70ca6714507::$prefixDirsPsr4;
            $loader->prefixesPsr0 = ComposerStaticInit750cee422df365b62416b70ca6714507::$prefixesPsr0;

        }, null, ClassLoader::class);
    }
}
