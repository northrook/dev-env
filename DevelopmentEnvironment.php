<?php // dev-env

/** @noinspection PhpUndefinedNamespaceInspection */
/** @noinspection ForgottenDebugOutputInspection */
/** @noinspection PhpUndefinedClassInspection */

declare( strict_types = 1 );

namespace Northrook;

use Northrook\Logger\{Log, Output};
use Northrook\Core\Env;
use Northrook\Core\Trait\PropertyAccessor;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\ErrorHandler\Debug;
use Psr\Log\{LoggerInterface, NullLogger};
use function Northrook\Core\Function\normalizePath;

/**
 * @property-read AssetManager   $assetManager
 * @property-read CacheManager   $cacheManager
 * @property-read ContentManager $contentManager
 * @property bool                $dumpOnExit [true]
 */
final class DevelopmentEnvironment
{

    use PropertyAccessor;

    private const STYLESHEET = <<<CSS
        body {
            font-family: sans-serif;
            color: #e6f2ff;
            background-color: #1f2937;
        }
        pre.sf-dump,
        pre.sf-dump * {
            font: unset ;
        }
        body pre.sf-dump {
            background-color: #15191e80;
            font-size: 15px;
            letter-spacing: .05ch;
            line-height: 1.5;
            font-family: "Dev Workstation", monospace !important;
        }
        body pre.sf-dump .sf-dump-public {
            color: #ffffff;
        }
        body pre.sf-dump .sf-dump-ellipsis {
            direction: rtl;
            max-width: 35vw;
        }
        body xmp, body pre {
            max-width: 100%;
            white-space: pre-wrap;
        }
        CSS;


    public static bool $dumpOnExit = true;

    private bool $echoedDocument = false;

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

                    Output::dump( $this->logger );

                    if ( $this->echoedDocument ) {
                        echo '</html>';
                    }
                },
            );
            Debug::enable();
        }

        $this->logger         = $logger ?? new Logger() ?? new NullLogger();
        $this->requestStack   = $requestStack ?? $this->newRequestStack();
        $this->currentRequest = $this->requestStack->getCurrentRequest();

        new Env( $env, $debug );

        Log::setLogger( $this->logger );

        $this->projectDir = normalizePath( $projectDir ?? getcwd() );
        $this->cacheDir   = normalizePath( $cacheDir ?? $this->projectDir . '/var/cache' );

        $this->title = $title ?? $_SERVER[ 'HTTP_HOST' ] ?? 'Development Environment';

        if ( $this->echoTitle ) {
            $this->echoTitle();
        }

        if ( $this->echoStyles ) {
            echo '<style>' . DevelopmentEnvironment::STYLESHEET . '</style>';
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

        if ( $name === 'dumpOnExit' && is_bool( $value ) ) {
            $this::$dumpOnExit = $value;
            return;
        }

        throw new \LogicException(
            "Cannot set property '$name', " . $this::class . " does not allow setting arbitrary properties.",
        );
    }

    public function document(
        ?string        $title = null,
        string | array $styles = [],
        string | array $scripts = [],
        string         $locale = 'en',
    ) {
        $title  ??= $this->title;
        $styles = is_string( $styles ) ? [ $styles ] : $styles;

        foreach ( $styles as $key => $style ) {
            $styles[$key] = "<style>{$style}</style>";
        }
        $styles = implode( "\n", $styles );

        $scripts = is_string( $scripts ) ? [ $scripts ] : $scripts;

        foreach ( $scripts as $key => $script ) {
            $script[$key] = "<script>{$script}</script>";
        }
        $scripts = implode( "\n", $scripts );

        echo <<<DOCUMENT
        <!DOCTYPE html>
        <html lang="$locale">
            <head>
                <title>$title</title>
                $styles
                $scripts
            </head>
        DOCUMENT;



        $this->echoedDocument = true;

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