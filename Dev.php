<?php

declare ( strict_types = 1 );

namespace Northrook;

use Northrook\Trait\SingletonClass;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Stopwatch\Stopwatch;

final class Dev
{
    private static DevEnv $devEnv;
    private static bool   $debug = true;

    public function __construct() {
        if ( !Dev::$debug ) {
            return;
        }
    }

    public static function env(
        bool             $echoDocument = true,
        bool             $showLogs = true,
        bool             $dumpOnExit = false,
        bool             $errorHandler = true,
        array            $parameters = [],
        array            $services = [],
        ?RequestStack    $requestStack = null,
        ?LoggerInterface $logger = null,
        ?Stopwatch       $stopwatch = null,
    ) : DevEnv {
        return self::$devEnv ??= new DevEnv( ... \get_defined_vars() );
    }

    public static function disable() : void {
        self::$debug = false;
    }

    public static function enable() : void {
        self::$debug = true;
    }
}