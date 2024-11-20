<?php

declare(strict_types=1);

namespace Northrook;

final class Debug
{
    private static array $dumpOnExit = [];

    private static array $dumpLater = [];

    public function __construct()
    {
    }

    public function getDumpOnExit() : array
    {
        return Debug::$dumpOnExit;
    }

    public static function dumpOnExit( ...$var ) : void
    {
        foreach ( $var as $dump ) {
            Debug::$dumpOnExit[] = $dump;
        }
    }

    public static function dumpLater( string $key, ...$var ) : void
    {
        Debug::$dumpLater[$key] = $var;
    }

    public static function dump( ?string $key ) : void
    {
        if ( ! $key ) {
            foreach ( Debug::$dumpLater as $var ) {
                dump( $var );
            }

            return;
        }

        $dump = Debug::$dumpLater[$key] ?? null;

        if ( $dump ) {
            dump( $dump );
        }
    }
}
