<?php /** @noinspection PhpUndefinedClassInspection */

declare( strict_types = 1 );

namespace Northrook;

use Northrook\Core\Env;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\ErrorHandler\BufferingLogger;
use Symfony\Component\ErrorHandler\Debug;

final class DevelopmentEnvironment
{

    public static bool $dumpOnExit = true;

    public readonly string          $title;
    public readonly ?string         $cacheDir;
    public readonly ?string         $projectDir;
    public readonly LoggerInterface $logger;
    public readonly CacheManager    $cacheManager;
    public readonly AssetManager    $assetManager;

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
    ) {

        $this->logger = $logger ?? new BufferingLogger() ?? new NullLogger();

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

        $this->cacheDir   = $this->normalizePath( $cacheDir );
        $this->projectDir = $this->normalizePath( $projectDir );

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
                    background-color: #15191E;
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


    /**
     * Normalise a `string`, assuming it is a `path`.
     *
     * - Removes repeated slashes.
     * - Normalises slashes to system separator.
     * - Prevents backtracking.
     * - No validation is performed.
     *
     * @param string  $string  The string to normalize.
     *
     * @return string
     */
    private function normalizePath(
        string $string,
    ) : string {
        $normalize = str_replace( [ '\\', '/' ], DIRECTORY_SEPARATOR, $string );
        $exploded  = explode( DIRECTORY_SEPARATOR, $normalize );
        $path      = implode( DIRECTORY_SEPARATOR, array_filter( $exploded ) );

        return ( realpath( $path ) ?: $path ) . DIRECTORY_SEPARATOR;
    }
}