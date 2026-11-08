<?php

// -----------------------------------------------------------
//  [*] ProductionModeTest — how errors look to a visitor
//
//  php-fpm runs the app in the production environment
//  run.sh gives it — APP_ENV production from .env, APP_DEBUG
//  off — so a server error is Laravel's plain 500 page, with
//  nothing in it about how the server is built or what its
//  secrets are, and the error itself goes to laravel.log.
//
//  Used by:
//    - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Server;

use Illuminate\Support\Facades\DB;
use Tests\ServerTestCase;








// -----------------------------------------------------------
// ProductionModeTest
// -----------------------------------------------------------
//
// The home page is broken on purpose by hiding the
// statistics table for the length of one request.
//
//   openBrokenHomePage — the home page with statistics gone
//
// Used by:
//   - PHPUnit — the Server suite (runTests.sh)
// -----------------------------------------------------------

class ProductionModeTest extends ServerTestCase
{






    // -----------------------------------------------------------
    // testServerErrorShowsTheGenericPage
    // -----------------------------------------------------------
    //
    // The 500 is the framework's plain page; it names no path,
    // no query, no table, no stack trace and no secret.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testServerErrorShowsTheGenericPage(): void
    {
        $response = $this->openBrokenHomePage();

        $this->assertSame(500, $response->status);
        $this->assertStringContainsString('Whoops, looks like something went wrong.', $response->body);
        preg_match('/^APP_KEY=(.+)$/m', file_get_contents(base_path('.env')), $key);
        foreach (['/var/www/html', 'SQLSTATE', 'statistics', 'Stack trace', 'HomeController', $key[1], getenv('DB_PASSWORD')] as $leak) {
            $this->assertStringNotContainsStringIgnoringCase($leak, $response->body);
        }
    }






    // -----------------------------------------------------------
    // testServerErrorIsLogged
    // -----------------------------------------------------------
    //
    // The error the visitor was spared is written to
    // laravel.log, in the production environment.
    //
    // Used by:
    //   - PHPUnit — the Server suite (runTests.sh)
    // -----------------------------------------------------------

    public function testServerErrorIsLogged(): void
    {
        $written = $this->logWrittenBy(function () {
            $this->openBrokenHomePage();
        });

        $this->assertRegExp("/production\\.ERROR: SQLSTATE\\[42S02\\].*Table 'bitlitas\\.statistics' doesn't exist/", $written);
    }






    // -----------------------------------------------------------
    // openBrokenHomePage
    // -----------------------------------------------------------
    //
    // The home page asked for while the statistics table is
    // renamed away; the table is back before the method
    // returns, whatever happened.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function openBrokenHomePage()
    {
        $this->world->seed();
        DB::statement('RENAME TABLE statistics TO statistics_hidden');
        try {
            return $this->http->get('/');
        } finally {
            DB::statement('RENAME TABLE statistics_hidden TO statistics');
        }
    }
}
