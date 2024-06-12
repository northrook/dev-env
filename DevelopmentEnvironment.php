<?php

declare( strict_types = 1 );

namespace Northrook;

use Composer\Autoload\ClassLoader;
use JetBrains\PhpStorm\ExpectedValues;
use Northrook\Core\Env;

// use Northrook\Support\Str;
use Symfony\Component\ErrorHandler\Debug;

// function cacheDir() : string {
//     return sys_get_temp_dir() . '/' . Str::key( $_SERVER[ 'HTTP_HOST' ] ?? 'dev' );
// }

final class DevelopmentEnvironment
{

    public readonly string  $title;
    public readonly ?string $cacheDir;
    public readonly ?string $projectDir;

    /**
     * @param null|string  $title
     * @param string       $env  = ['dev', 'prod', 'staging'][$any]
     * @param bool         $debug
     * @param null|string  $cacheDir
     * @param null|string  $projectDir
     * @param bool         $errorHandler
     * @param bool         $echoTitle
     * @param bool         $echoStyles
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
    ) {

        if ( $this->errorHandler ) {
            Debug::enable();
        }

        new Env( $env, $debug );

        $this->cacheDir   = $this->normalizePath( $cacheDir );
        $this->projectDir = $this->normalizePath( $projectDir );


        $this->title = $title ?? $_SERVER[ 'HTTP_HOST' ] ?? 'Development Environment';

        if ( $this->echoTitle ) {
            $this->echoTitle( $title );
        }

        if ( $this->echoStyles ) {
            $this->echoStyles();
        }

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
     * @param ?string  $string  The string to normalize.
     *
     * @return ?string
     */
    private function normalizePath(
        ?string $string,
        bool    $allowFilePath = false,
    ) : ?string {

        if ( !$string ) {
            return null;
        }

        $string = strtolower( str_replace( "\\", "/", $string ) );

        if ( str_contains( $string, '/' ) ) {

            $path = [];

            foreach ( array_filter( explode( '/', $string ) ) as $part ) {
                if ( $part === '..' && $path && end( $path ) !== '..' ) {
                    array_pop( $path );
                }
                elseif ( $part !== '.' ) {
                    $path[] = trim( $part );
                }
            }

            $path = implode( separator : DIRECTORY_SEPARATOR, array : $path );
        }
        else {
            $path = $string;
        }

        $extension = pathinfo( $path, PATHINFO_EXTENSION );

        if ( $extension && !$allowFilePath ) {
            throw new \InvalidArgumentException(
                "Invalid path: {$path}.\n\nFile path not allowed.\n\nDirectory path required.",
            );
        }

        // Only append a directory separator if the path is not a file
        return $extension ? $path : $path . DIRECTORY_SEPARATOR;
    }
}