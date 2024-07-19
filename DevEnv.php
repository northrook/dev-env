<?php

namespace Northrook;

use Northrook\Debug as Debugger;
use Northrook\Core\Env;
use Northrook\Core\Trait\PropertyAccessor;
use Northrook\Core\Trait\SingletonClass;
use Northrook\Logger\Log;
use Northrook\Logger\Output;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\ErrorHandler\Debug;
use function Northrook\getProjectRootDirectory;
use function Northrook\normalizePath;

/**
 * @property-read string $projectDir
 * @property-read string $varDir
 * @property-read string $cacheDir
 */
final class DevEnv
{
    use PropertyAccessor, SingletonClass;

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

    private bool     $echoDocument   = true;
    private bool     $echoedDocument = false;
    private bool     $errorHandler   = true;
    private bool     $dumpOnExit     = false;
    private Debugger $debugger;

    private array $services   = [];
    private array $parameters = [
        'env'   => Env::DEVELOPMENT,
        'debug' => true,
    ];

    public readonly RequestStack    $requestStack;
    public readonly Request         $currentRequest;
    public readonly LoggerInterface $logger;
    public readonly Stopwatch       $stopwatch;

    public function __construct(
        array            $parameters = [],
        array            $services = [],
        ?RequestStack    $requestStack = null,
        ?LoggerInterface $logger = null,
        ?Stopwatch       $stopwatch = null,
    ) {
        $this->instantiationCheck();

        $this->stopwatch = $stopwatch ?? new Stopwatch();
        $this->stopwatch->start( 'app', 'dev-env' );

        $this->debugger = new Debugger();
        $this->logger   = $logger ?? new Logger();
        Log::setLogger( $this->logger );

        $this->requestStack   = $requestStack ?? $this->newRequestStack();
        $this->currentRequest = $this->requestStack->getCurrentRequest();

        $this->parameters = array_merge( $this->parameters, $parameters );
        $this->parameters += [ 'title' => $_SERVER[ 'HTTP_HOST' ] ?? 'Development Environment' ];

        new Env( $this->parameters[ 'env' ], $this->parameters[ 'debug' ] );
        Log::setLogger( $this->logger );

        foreach ( $services as $service ) {
            $this->set( $service );
        }

        $this->debugHandler();
        $this::$instance = $this;
    }

    public function __get( string $property ) {
        return match ( $property ) {
            'env'        => $this->parameters[ 'env' ],
            'debug'      => $this->parameters[ 'debug' ],
            'projectDir' => $this->getProjectDir(),
            'varDir'     => $this->parameters[ 'varDir' ] ??= normalizePath( $this->getProjectDir() . '/var' ),
            'cacheDir'   => $this->parameters[ 'cacheDir' ] ??= normalizePath( $this->getProjectDir() . '/var/cache' ),
            default      => $this->parameters[ $property ] ?? $this->services[ $property ] ?? null,
        };
    }

    /**
     * @template Service
     * @param class-string<Service>  $service
     *
     * @return Service|null
     */
    public function get( string $service ) : ?object {
        return $this->services[ $service ] ?? null;
    }

    public function set( object $service ) : self {
        $this->services[ $service::class ] = $service;
        return $this;
    }

    private function getProjectDir() : string {
        return $this->parameters[ 'projectDir' ] ??= normalizePath( getProjectRootDirectory() );
    }

    public function dumpOnExit( bool $bool = true ) : self {
        $this->dumpOnExit = $bool;
        return $this;
    }

    public function document(
        ?string        $title = null,
        string | array $styles = [],
        string | array $scripts = [],
        string         $locale = 'en',
    ) : void {
        $title  ??= $this->parameters[ 'title' ] ?? 'Development Environment';
        $styles = is_string( $styles ) ? [ $styles ] : $styles;

        foreach ( $styles as $key => $style ) {
            $styles[ $key ] = "<style>{$style}</style>";
        }
        $styles = implode( "\n", $styles );

        $scripts = is_string( $scripts ) ? [ $scripts ] : $scripts;

        foreach ( $scripts as $key => $script ) {
            $script[ $key ] = "<script>{$script}</script>";
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


        if ( $this->echoDocument ) {
            echo "<div style='display: block; font-family: monospace; opacity: .5'>{$this->parameters['title']}</div>";
        }

        if ( $this->echoDocument ) {
            echo '<style>' . self::STYLESHEET . '</style>';
        }

        $this->echoedDocument = true;
    }

    private function debugHandler() : void {
        if ( !$this->errorHandler ) {
            return;
        }
        register_shutdown_function(
            function () {
                if ( $this->dumpOnExit ) {

                    $dump = ( new Debugger() )->getDumpOnExit() + [ $this ];

                    foreach ( $dump as $var ) {
                        dump( $var );
                    }

                    $event    = (string) $this->stopwatch->stop( 'app' );
                    $rendered = str_replace( 'dev-env/app', $this->parameters[ 'title' ], $event );
                    echo "<script>console.log( '$rendered' )</script>";
                }

                Output::dump( $this->logger );

                if ( $this->echoedDocument ) {
                    echo '</html>';
                }
            },
        );
        Debug::enable();

    }

    private function newRequestStack() : RequestStack {
        $requestStack = new RequestStack();
        $requestStack->push( Request::createFromGlobals() );
        return $requestStack;
    }
}