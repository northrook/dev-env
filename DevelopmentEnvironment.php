<?php // dev-env

/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection ForgottenDebugOutputInspection */
/** @noinspection PhpUndefinedClassInspection */

declare( strict_types = 1 );

namespace Northrook;

use Northrook\Core\Env;
use Northrook\Core\Trait\PropertyAccessor;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use function Northrook\Core\Functions\normalizePath;

/**
 * @property-read AssetManager $assetManager
 * @property-read CacheManager $cacheManager
 */
final class DevelopmentEnvironment
{

    public static bool $dumpOnExit = true;

    protected readonly CacheManager $cacheManager;
    protected readonly AssetManager $assetManager;

    public readonly string          $title;
    public readonly ?string         $projectDir;
    public readonly ?string         $cacheDir;
    public readonly RequestStack    $requestStack;
    public readonly Request         $currentRequest;
    public readonly LoggerInterface $logger;

    /**
     * @param null|string       $title
     * @param string            $env  = ['dev', 'prod', 'staging'][$any]
     * @param bool              $debug
     * @param null|string       $cacheDir
     * @param null|string       $projectDir
     * @param bool              $errorHandler
     * @param bool              $echoTitle
     * @param bool              $echoStyles
     * @param ?LoggerInterface  $logger
     * @param ?RequestStack     $requestStack
     */
    public function __construct(
        ?string          $title = null,
        string           $env = Env::DEVELOPMENT,
        bool             $debug = true,
        ?string          $projectDir = null,
        ?string          $cacheDir = null,
        ?LoggerInterface $logger = null,
        ?RequestStack    $requestStack = null,
        public bool      $errorHandler = true,
        public bool      $echoTitle = true,
        public bool      $echoStyles = true,
    ) {

        $this->logger         = $logger ?? new BufferingLogger() ?? new NullLogger();
        $this->requestStack   = $requestStack ?? $this->newRequestStack();
        $this->currentRequest = $this->requestStack->getCurrentRequest();

        if ( $this->errorHandler ) {
            $app = $this;
            register_shutdown_function(
                static function () use ( $app ) {
                    $logs = [];

                    foreach ( $app->logger->cleanLogs() as $index => $log ) {
                        $key          = " $index [{$log[0]}] {$log[1]}";
                        $logs[ $key ] = $log;
                    }

                    if ( $app::$dumpOnExit ) {
                        dump( $app );
                    }
                    if ( $logs ) {
                        dump( $logs );
                    }
                },

            );
            Debug::enable();
        }

        new Env( $env, $debug );

        $this->projectDir = normalizePath( $projectDir ?? getcwd() );
        $this->cacheDir   = normalizePath( $cacheDir ?? $this->projectDir . '/var/cache' );

        $this->title = $title ?? $_SERVER[ 'HTTP_HOST' ] ?? 'Development Environment';

        if ( $this->echoTitle ) {
            $this->echoTitle();
        }

        if ( $this->echoStyles ) {
            $this->echoStyles();
        }

        unset( $title, $env, $debug, $projectDir, $cacheDir, $logger, $requestStack );
    }


    public function __get( string $property ) {

        return match ( $property ) {
            'cacheManager' => $this->cacheManager ??= $this->newCacheManager(),
            'assetManager' => $this->assetManager ??= $this->newAssetManager(),
        };
    }

    /**
     * Check if the property exists.
     *
     * @param string  $property
     *
     * @return bool
     */
    public function __isset( string $property ) : bool {
        return isset( $this->$property );
    }

    /**
     * The {@see PropertyAccessor} trait does not allow setting properties.
     *
     * @throws \LogicException
     */
    public function __set( string $name, mixed $value ) {
        throw new \LogicException(
            "Cannot set property '$name', " . $this::class . " does not allow setting arbitrary properties.",
        );
    }

    public function dumpOnExit( bool $bool = true ) : DevelopmentEnvironment {
        $this::$dumpOnExit = $bool;
        return $this;
    }

    public function set( $property ) : DevelopmentEnvironment {
        if ( is_object( $property ) ) {
            $propertyName = $property::class;
            $namespace    = strrpos( $propertyName, '\\' );
            if ( $namespace !== false ) {
                $propertyName = lcfirst( substr( $propertyName, ++$namespace ) );
            }
            if ( !isset( $this->$propertyName ) ) {
                $this->$propertyName = $property;
            }
            else {
                dump( "$propertyName has already been set" );
            }
        }

        return $this;
    }

    private function echoTitle() : void {
        echo "<title>{$this->title}</title>";
        echo "<div style='display: block; font-family: monospace; opacity: .5'>{$this->title}</div>";
    }

    private function echoStyles() : void {
        echo <<<STYLE
            <style>
                body {
                    font-family: sans-serif;
                    color: #e6f2ff;
                    background-color: #1f2937;
                }
                body pre.sf-dump {
                    background-color: #15191E80;
                }
                body pre.sf-dump .sf-dump-public {
                    color: #FFFFFF;
                }
                body pre.sf-dump .sf-dump-ellipsis {
                    direction: rtl;
                }
                body xmp, body pre {
                    max-width: 100%;
                    white-space: pre-wrap;
                }
            </style>
        STYLE;
    }

    private function newRequestStack() : RequestStack {
        $requestStack = new RequestStack();
        $requestStack->push( Request::createFromGlobals() );
        return $requestStack;
    }

    private function newCacheManager() : CacheManager {
        return new CacheManager(
            cacheDirectory    : $this->projectDir . '/var/cache',
            manifestDirectory : $this->projectDir . '/assets',
            logger            : $this->logger,
        );
    }


    private function newAssetManager() : AssetManager {

        if ( !isset( $this->cacheManager ) ) {
            $this->cacheManager = $this->newCacheManager();
        }

        return new AssetManager(
            publicRoot   : $this->projectDir . '/public',
            publicAssets : $this->projectDir . '/public/assets',
            cache        : $this->cacheManager->getAdapter( 'assetCache' ),
            manifest     : new \Northrook\Cache\ManifestCache( 'assetManager' ),
        );
    }
}