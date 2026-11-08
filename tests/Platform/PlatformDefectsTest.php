<?php

// -----------------------------------------------------------
//  [*] PlatformDefectsTest — what the image still gets wrong
//
//  The pinned defects of the platform (tests/README.md):
//  each test states the wanted behaviour and stays
//  incomplete while the defect reproduces. The image ships
//  no php.ini, so PHP runs on its built-in defaults, which
//  print errors into the page; and config/app.php turns
//  debug mode on whenever APP_DEBUG is missing.
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
    // testPhpShowsNoErrorsToVisitors
    // -----------------------------------------------------------
    //
    // [display-errors] PHP keeps its own errors out of the
    // pages Apache serves — php.ini-production's
    // display_errors Off. Without a php.ini it is On: an
    // error PHP raises before Laravel's handler is in place
    // prints with the server's paths. mod_php reads the ini
    // files the command line reads, so a command line PHP
    // shows the site's setting.
    //
    // Used by:
    //   - PHPUnit — the Platform suite (runTests.sh)
    // -----------------------------------------------------------

    public function testPhpShowsNoErrorsToVisitors(): void
    {
        $this->pinDefect('display-errors', function () {
            $value = trim((string) shell_exec('php -r "echo ini_get(\'display_errors\');"'));
            $this->assertContains(strtolower($value), ['', '0', 'off'], "PHP runs with display_errors $value (no php.ini is loaded)");
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
