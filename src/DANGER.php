<?php

declare(strict_types=1);

namespace Northrook;

use LogicException;

final class DANGER
{
    /** @noinspection HtmlUnknownTarget */
    private const array MISC = [
        // Text Strings
        "<script>alert('XSS')</script>",
        "' OR '1'='1",
        "''; DROP TABLE users; --",
        "<IMG SRC='javascript:alert(\"XSS\");'>",
        "<svg/onload=alert('XSS')>",
        "<a href='javascript:alert(\"XSS\")'>Click me</a>",
        "</title><script>alert('XSS')</script>",
        "');alert('XSS');//",
        "\" onmouseover=\"alert('XSS')\"",
        "<body onload=alert('XSS')>",
    ];

    /** @noinspection HtmlUnknownTarget */
    private const array URL = [
        "http://example.com/<script>alert('XSS')</script>",
        "javascript:alert('XSS')",
        "http://example.com/?param=<img src='x' alt='xss' onerror='alert(1)'>",
        "http://example.com/?q=<script>document.location='http://badsite.com'</script>",
    ];

    /** @noinspection HtmlUnknownTarget */
    private const array EMAIL = [
        'normal@example.com',
        "test@example.com<script>alert('XSS')</script>",
        "xss@example.com<img src='x' alt='xss'  onerror='alert(1)'>",
        "<script>alert('email')</script>@example.com",
    ];

    private const array SQL = [
        '1; DROP TABLE users',
        "' OR '1'='1",
        "' OR '1'='1' --",
        "'; DELETE FROM users WHERE 'a'='a",
        "admin'--",
        "admin' #",
        "' UNION SELECT null, null, null --",
        "' OR 1=1 --",
        '" OR 1=1 --',
        '" OR "" = "',
    ];

    private const array PATH = [
        '../../../../etc/passwd',
        '../../../../../../../../windows/win.ini',
        'php://filter/convert.base64-encode/resource=index.php',
        'expect://ls',
    ];

    private const array CLI = [
        '; ls -la',
        '| cat /etc/passwd',
        '`cat /etc/passwd`',
        '$(cat /etc/passwd)',
    ];

    private static bool $DANGER_ACKNOWLEDGED = false;

    public static function enableReturningDangerousStrings() : void
    {
        self::$DANGER_ACKNOWLEDGED = true;
    }

    public static function getString( bool $all = false ) : string|array
    {
        return DANGER::return( DANGER::MISC, $all );
    }

    public static function getUrl( bool $all = false ) : string|array
    {
        return DANGER::return( DANGER::URL, $all );
    }

    public static function getEmail( bool $all = false ) : string|array
    {
        return DANGER::return( DANGER::EMAIL, $all );
    }

    public static function getPath( bool $all = false ) : string|array
    {
        return DANGER::return( DANGER::PATH, $all );
    }

    public static function getSql( string $areYouSure, bool $all = false ) : string|array
    {
        if ( 'Yes I absolutely understand that this can and will drop my entire database if used incorrectly' !== $areYouSure ) {
            throw new LogicException( <<<'EOD'
                You tried to return a SQL injection that could nuke your database without paying attention.
                                Please read the docblock for the method first.
                EOD, );
        }
        return DANGER::return( DANGER::SQL, $all );
    }

    public static function getCli( bool $all = false ) : string|array
    {
        return DANGER::return( DANGER::CLI, $all );
    }

    private static function return( array $danger, bool $all = false ) : string|array
    {
        if ( ! self::$DANGER_ACKNOWLEDGED ) {
            throw new LogicException( <<<'EOD'
                You tried to return an intentionally malicious variable.
                            Please call 
                EOD.DANGER::class.'enableReturningDangerousStrings() first.', );
        }
        return $all ? $danger : $danger[\rand( 0, \count( $danger ) - 1 )];
    }
}
