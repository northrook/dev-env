<?php

declare(strict_types=1);

namespace Northrook;

use \Interface\{SingletonClass, Singleton};
use Northrook\Logger\{Log, Output};
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Psr\Log\LoggerInterface;
use Support\{Normalize, PropertyAccessor};
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Exception\CacheException;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Stopwatch\Stopwatch;
use function Support\getProjectRootDirectory;
use const Support\TAB;
use LogicException;

/**
 * @property string $title
 * @property string $projectDir
 * @property string $varDir
 * @property string $cacheDir
 */
final class DevEnv implements Singleton
{
    use PropertyAccessor, SingletonClass;

    private const string STYLESHEET = <<<'CSS'
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

    private bool $echoedDocument = false;

    // private bool     $echoDocument   = true;
    private Debug $debugger;

    private array $services = [];

    private array $parameters = [
        'env'   => 'dev',
        'debug' => true,
    ];

    public bool $logsExpanded = true;

    public readonly RequestStack $requestStack;

    public readonly Request $currentRequest;

    public readonly LoggerInterface $logger;

    public readonly Stopwatch $stopwatch;

    public function __construct(
        private readonly bool $echoDocument = true,
        public bool           $showLogs = true,
        public bool           $dumpOnExit = false,
        private readonly bool $errorHandler = true,
        array                 $parameters = [],
        array                 $services = [],
        ?RequestStack         $requestStack = null,
        ?LoggerInterface      $logger = null,
        ?Stopwatch            $stopwatch = null,
    ) {
        if ( $this->instantiationCheck() ) {
            return;
        }

        $this->initialize()
            ->stopwatch( $stopwatch )
            ->debugger( $logger )
            ->requestStack( $requestStack );

        new \Support\Filesystem( logger : $this->logger );

        $this->parameters = \array_merge( $this->parameters, $parameters );
        $this->parameters += ['title' => $_SERVER['HTTP_HOST'] ?? 'Development Environment'];

        foreach ( $services as $service ) {
            $this->set( $service );
        }

        if ( $this->echoDocument ) {
            $this->document();
        }

        $this->debugHandler();

        $this->instantiateSingleton();
    }

    private function initialize() : self
    {
        $this->instantiationCheck();
        return $this;
    }

    private function stopwatch( ?Stopwatch $stopwatch ) : self
    {
        $this->stopwatch = $stopwatch ?? new Stopwatch();
        $this->stopwatch->start( 'app', 'dev-env' );
        new Clerk( $this->stopwatch );
        return $this;
    }

    private function debugger( ?LoggerInterface $logger ) : self
    {
        $this->debugger = new Debug();

        $this->logger = $logger ?? new Logger();
        Log::setLogger( $this->logger );
        return $this;
    }

    private function requestStack( ?RequestStack $requestStack ) : self
    {
        $this->requestStack   = $requestStack ?? $this->newRequestStack();
        $this->currentRequest = $this->requestStack->getCurrentRequest();

        if ( ! $this->currentRequest->hasSession() ) {
            $this->currentRequest->setSession( new Session( new MockArraySessionStorage() ) );
        }

        return $this;
    }

    public function __get( string $property )
    {
        return match ( $property ) {
            'title'      => $this->parameters['title'],
            'env'        => $this->parameters['env'],
            'debug'      => $this->parameters['debug'],
            'projectDir' => $this->getProjectDir(),
            'varDir'     => $this->parameters['varDir']   ??= Normalize::path( $this->getProjectDir().'/var' ),
            'cacheDir'   => $this->parameters['cacheDir'] ??= Normalize::path(
                $this->getProjectDir().'/var/cache',
            ),
            default => $this->parameters[$property] ?? $this->services[$property] ?? null,
        };
    }

    /**
     * @template Service
     * @param class-string<Service> $service
     *
     * @return null|Service
     */
    public function get( string $service ) : ?object
    {
        return $this->services[$service] ?? null;
    }

    public function set( object $service ) : self
    {
        $this->services[$service::class] = $service;
        return $this;
    }

    private function getProjectDir() : string
    {
        return $this->parameters['projectDir'] ??= Normalize::path( getProjectRootDirectory() );
    }

    public function dumpOnExit( bool $bool = true ) : self
    {
        $this->dumpOnExit = $bool;
        return $this;
    }

    public function document(
        ?string      $title = null,
        string|array $styles = [],
        string|array $scripts = [],
        string       $locale = 'en',
        bool         $logsOpen = true,
    ) : void {
        $this->logsExpanded = $logsOpen;
        $title ??= $this->parameters['title'] ?? 'Development Environment';

        $styles            = (array) $styles;
        $styles['dev-env'] = self::STYLESHEET;

        echo '<!DOCTYPE html>'.PHP_EOL;
        echo '<html lang="'.$locale.'">'.PHP_EOL;
        echo '<head>'.PHP_EOL;
        echo TAB.'<meta charset="utf-8">'.PHP_EOL;
        echo TAB.'<title>'.$title.'</title>'.PHP_EOL;
        if ( $styles ) {
            foreach ( (array) $styles as $key => $style ) {
                echo TAB.'<style>'.\preg_replace( '/\s+/', ' ', $style ).'</style>'.PHP_EOL;
            }
        }
        if ( $scripts ) {
            foreach ( (array) $scripts as $key => $script ) {
                echo TAB.'<script>'.\preg_replace( '/\s+/', ' ', $script ).'</script>'.PHP_EOL;
            }
        }
        echo '</head>'.PHP_EOL;

        $this->echoedDocument = true;
    }

    private function debugHandler() : void
    {
        if ( ! $this->errorHandler ) {
            return;
        }
        \register_shutdown_function(
            function() {
                if ( $this->dumpOnExit ) {
                    $dump = ( new Debug() )->getDumpOnExit() + [$this];

                    foreach ( $dump as $var ) {
                        dump( $var );
                    }

                    $event    = (string) $this->stopwatch->stop( 'app' );
                    $rendered = \str_replace( 'dev-env/app', $this->parameters['title'], $event );
                    echo "<script>console.log( '{$rendered}' )</script>";
                }

                if ( $this->showLogs ) {
                    $open = $this->logsExpanded ? 'open' : '';
                    echo "<details {$open}><summary>Logs</summary>";
                    Output::dump( $this->logger );
                    echo '</details>';
                }

                if ( $this->echoedDocument ) {
                    echo PHP_EOL.'</html>';
                }
            },
        );
        Debugger::enable();
    }

    private function newRequestStack() : RequestStack
    {
        $requestStack = new RequestStack();
        $requestStack->push( Request::createFromGlobals() );
        return $requestStack;
    }

    public static function mockFileCacheAdapter(
        string  $namespace = 'dev',
        int     $defaultLifetime = 0,
        ?string $directory = null,
        bool    $appendOnly = false,
    ) : PhpFilesAdapter {
        try {
            return new PhpFilesAdapter(
                $namespace,
                $defaultLifetime,
                Normalize::path( $directory ?? getProjectRootDirectory().'/var/cache' ),
            );
        }
        catch ( CacheException $e ) {
            throw new LogicException( 'symfony/cache is required.' );
        }
    }
}
