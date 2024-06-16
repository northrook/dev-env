<?php /** @noinspection PhpUndefinedClassInspection */

declare( strict_types = 1 );

namespace Northrook;

use Northrook\Core\Env;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\Debug;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DevelopmentEnvironment
{

    public static bool $dumpOnExit = true;

    public readonly string          $title;
    public readonly ?string         $cacheDir;
    public readonly ?string         $projectDir;
    public readonly RequestStack    $requestStack;
    public readonly Request         $currentRequest;
    public readonly LoggerInterface $logger;
    public readonly CacheManager    $cacheManager;
    public readonly AssetManager    $assetManager;

    /**
     * @param null|string        $title
     * @param string             $env  = ['dev', 'prod', 'staging'][$any]
     * @param bool               $debug
     * @param null|string        $cacheDir
     * @param null|string        $projectDir
     * @param bool               $errorHandler
     * @param bool               $echoTitle
     * @param bool               $echoStyles
     * @param ?LoggerInterface   $logger
     * @param ?RequestStack  $requestStack
     */
    public function __construct(
        ?string          $title = null,
        string           $env = Env::DEVELOPMENT,
        bool             $debug = true,
        ?string          $cacheDir = null,
        ?string          $projectDir = null,
        public bool      $errorHandler = true,
        public bool      $echoTitle = true,
        public bool      $echoStyles = true,
        ?LoggerInterface $logger = null,
        ?RequestStack    $requestStack = null,
    ) {

        $this->logger       = $logger ?? new BufferingLogger() ?? new NullLogger();
        $this->requestStack = $requestStack ?? $this->newRequestStack();
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

        $this->cacheDir   = normalizeRealPath( $cacheDir );
        $this->projectDir = normalizeRealPath( $projectDir );

        $this->title = $title ?? $_SERVER[ 'HTTP_HOST' ] ?? 'Development Environment';

        if ( $this->echoTitle ) {
            $this->echoTitle();
        }

        if ( $this->echoStyles ) {
            $this->echoStyles();
        }

    }

    public function dumpOnExit( bool $bool = true ) : DevelopmentEnvironment {
        $this::$dumpOnExit = $bool;
        return $this;
    }

    public function set( $property ) : DevelopmentEnvironment {


        if ( is_object( $property ) ) {
            $propertyName = lcfirst( substr( $property::class, strpos( $property::class, '\\' ) + 1 ) );
            if ( !isset( $this->$propertyName ) ) {
                $this->$propertyName = $property;
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
}