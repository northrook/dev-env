<?php /** @noinspection PhpUndefinedClassInspection */

declare( strict_types = 1 );

namespace Northrook;

use Northrook\Core\Env;
use Symfony\Component\ErrorHandler\Debug;

final class DevelopmentEnvironment
{

    public readonly string       $title;
    public readonly ?string      $cacheDir;
    public readonly ?string      $projectDir;
    public readonly CacheManager $cacheManager;
    public readonly AssetManager $assetManager;

    /**
     * @param null|string  $title
     * @param string       $env  = ['dev', 'prod', 'staging'][$any]
     * @param bool         $debug
     * @param null|string  $cacheDir
     * @param null|string  $projectDir
     * @param bool         $errorHandler
     * @param bool         $echoTitle
     * @param bool         $echoStyles
     * @param bool         $cacheManager
     */
    public function __construct(
        ?string     $title = null,
        string      $env = Env::DEVELOPMENT,
        bool        $debug = true,
        ?string     $cacheDir = null,
        ?string     $projectDir = null,
        public bool $errorHandler = true,
        public bool $echoTitle = true,
        public bool $echoStyles = true,
        bool        $cacheManager = true,
    ) {

        if ( $this->errorHandler ) {
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

        if ( $cacheManager && class_exists( 'Northrook\CacheManager' ) ) {
            $this->cacheManager = new CacheManager(
                cacheDirectory : $this->cacheDir,
                assetDirectory : $this->projectDir . '/public',
            );
        }

    }

    public function set( $property ) : void {

        if ( is_object( $property ) ) {
            $propertyName = lcfirst( substr( $property::class, strpos( $property::class, '\\' ) + 1 ) );
            if ( !isset( $this->$propertyName ) ) {
                $this->$propertyName = $property;
            }
        }

        // dump(
        //     $property,
        // );
        // if ( $property instanceof CacheManager && !isset( $this->cacheManager ) ) {
        //     $this->cacheManager = $property;
        // }
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