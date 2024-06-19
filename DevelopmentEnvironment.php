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
use function Northrook\Core\Function\normalizePath;
use Northrook\Logger\Log;

/**
 * @property-read AssetManager   $assetManager
 * @property-read CacheManager   $cacheManager
 * @property-read ContentManager $contentManager
 */
final class DevelopmentEnvironment
{

    public static bool $dumpOnExit = true;

    protected readonly CacheManager   $cacheManager;
    protected readonly AssetManager   $assetManager;
    protected readonly ContentManager $contentManager;

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

        if ( $this->errorHandler ) {
            register_shutdown_function(
                function () {
                    if ( $this::$dumpOnExit ) {
                        dump( $this );
                    }

                    if ( $logs = $this->logger->printLogs( false ) ) {
                        dump( $logs );
                    }
                },
            );
            Debug::enable();
        }

        $this->logger         = $logger ?? new Logger() ?? new NullLogger();
        $this->requestStack   = $requestStack ?? $this->newRequestStack();
        $this->currentRequest = $this->requestStack->getCurrentRequest();

        new Env( $env, $debug );
        new Log( $this->logger );

        $this->projectDir = normalizePath( $projectDir ?? getcwd() );
        $this->cacheDir   = normalizePath( $cacheDir ?? $this->projectDir . '/var/cache' );

        $this->title = $title ?? $_SERVER[ 'HTTP_HOST' ] ?? 'Development Environment';

        if ( $this->echoTitle ) {
            $this->echoTitle();
        }

        if ( $this->echoStyles ) {
            try {
                echo '<style>' . file_get_contents( __DIR__ . '/assets/stylesheet.css' ) . '</style>';
            }
            catch ( \Exception $exception ) {
                $this->logger->error( $exception->getMessage() );
            }
        }
    }


    public function __get( string $property ) {

        return match ( $property ) {
            'cacheManager'   => $this->cacheManager ??= $this->newCacheManager(),
            'assetManager'   => $this->assetManager ??= $this->newAssetManager(),
            'contentManager' => $this->contentManager ??= $this->newContentManager(),
            default          => null
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
                $this->logger->warning( "$propertyName has already been set" );
            }
        }

        return $this;
    }

    private function echoTitle() : void {
        echo "<title>{$this->title}</title>";
        echo "<div style='display: block; font-family: monospace; opacity: .5'>{$this->title}</div>";
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

    private function newContentManager() : ContentManager {
        return new ContentManager(
            logger : $this->logger,
        );
    }
}