<?php

declare(strict_types=1);

namespace Northrook;

final class Debug
{
    /** @var list<mixed> */
    private static array $dumpOnExit = [];

    /** @var array<string, mixed> */
    private static array $dumpLater = [];

    public function __construct()
    {
    }

    /**
     * @return mixed[]
     */
    public function getDumpOnExit() : array
    {
        return Debug::$dumpOnExit;
    }

    /**
     * @param mixed ...$var
     *
     * @return void
     */
    public static function dumpOnExit( mixed ...$var ) : void
    {
        foreach ( $var as $dump ) {
            Debug::$dumpOnExit[] = $dump;
        }
    }

    /**
     * @param string $key
     * @param mixed  ...$var
     *
     * @return void
     */
    public static function dumpLater( string $key, mixed ...$var ) : void
    {
        Debug::$dumpLater[$key] = $var;
    }

    public static function dump( ?string $key = null ) : void
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
