<?php

// -----------------------------------------------------------
//  [*] PlatformDefectsTest — what the image still gets wrong
//
//  The pinned defects of the platform (tests/README.md):
//  each test states the wanted behaviour and stays
//  incomplete while the defect reproduces. The image ships
//  no php.ini, so php-fpm runs on PHP's built-in defaults,
//  which print errors into the page; the official image's
//  zz-docker.conf has php-fpm listen on every interface,
//  though nginx in the same container is its only client;
//  and config/app.php turns debug mode on whenever APP_DEBUG
//  is missing.
//
//  Used by:
//    - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Platform;

use Tests\TestCase;








// -----------------------------------------------------------
// PlatformDefectsTest
// -----------------------------------------------------------
//
// One pin per test, through pinDefect.
//
// Used by:
//   - PHPUnit — the Platform suite (runTests.sh)
// -----------------------------------------------------------

class PlatformDefectsTest extends TestCase
{






    // -----------------------------------------------------------
    // testPhpFpmShowsNoErrorsToVisitors
    // -----------------------------------------------------------
    //
    // [display-errors] php-fpm keeps PHP's own errors out of
    // the pages it serves — php.ini-production's
    // display_errors Off. Without a php.ini it is On: an
    // error PHP raises before Laravel's handler is in place
    // prints with the server's paths.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpFpmShowsNoErrorsToVisitors(): void
    {
        $this->pinDefect('display-errors', function () {
            preg_match('/^display_errors => (\S+)/m', (string) shell_exec('php-fpm -i 2>/dev/null'), $match);
            $this->assertSame('Off', $match[1] ?? null, 'php-fpm runs with display_errors ' . ($match[1] ?? '?') . ' (no php.ini is loaded)');
        });
    }






    // -----------------------------------------------------------
    // testPhpFpmListensOnlyForNginx
    // -----------------------------------------------------------
    //
    // [fpm-listens-everywhere] php-fpm listens on 127.0.0.1
    // only, as www.conf asks: nginx is in the same container.
    // zz-docker.conf, read last, sets listen = 9000, every
    // interface — any container on bitlitas-isolated could
    // send php-fpm a FastCGI request of its own.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpFpmListensOnlyForNginx(): void
    {
        $this->pinDefect('fpm-listens-everywhere', function () {
            $this->assertSame('127.0.0.1:9000', PhpRuntimeTest::fpmSettings()['listen'] ?? null, 'php-fpm listens on every interface');
        });
    }






    // -----------------------------------------------------------
    // testDebugStaysOffWithoutAppDebug
    // -----------------------------------------------------------
    //
    // [debug-by-default] A container started without
    // APP_DEBUG runs with debug mode off. config/app.php
    // defaults it to true, so such a container shows the
    // code, the stack trace and the request of every error
    // to whoever caused it; docker-compose.yml sets
    // APP_DEBUG=false, which is all that keeps it off.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testDebugStaysOffWithoutAppDebug(): void
    {
        $this->pinDefect('debug-by-default', function () {
            $app = ConfigTest::withoutEnv(['APP_DEBUG'], 'app.php');
            $this->assertFalse($app['debug'], 'debug mode is on without APP_DEBUG');
        });
    }
}
