<?php

declare ( strict_types = 1 );

namespace Northrook;

use Northrook\Core\Trait\SingletonClass;
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
        array            $parameters = [],
        array            $services = [],
        ?RequestStack    $requestStack = null,
        ?LoggerInterface $logger = null,
        ?Stopwatch       $stopwatch = null,
    ) : DevEnv {
        return self::$devEnv ??= new DevEnv( $parameters, $services, $requestStack, $logger, $stopwatch, );
    }

    public static function disable() : void {
        self::$debug = false;
    }

    public static function enable() : void {
        self::$debug = true;
    }

    public static function getFileCacheAdapter(
        string  $namespace = 'dev',
        int     $defaultLifetime = 0,
        ?string $directory = null,
        bool    $appendOnly = false,
    ) : PhpFilesAdapter {
        try {
            return new PhpFilesAdapter(
                $namespace,
                $defaultLifetime,
                $directory ?? getProjectRootDirectory(),
            );
        }
        catch ( CacheException $e ) {
            throw new \LogicException( 'symfony/cache is required.' );
        }
    }
}