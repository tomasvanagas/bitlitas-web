<?php

// -----------------------------------------------------------
//  [*] RouteDefectsTest — routes that lead nowhere
//
//  The pinned defects of the route table (tests/README.md):
//  each test states the wanted behaviour and stays
//  incomplete while the defect reproduces. routes/web.php
//  still calls Auth::routes(), and routes/api.php still has
//  the scaffold's api/user, but the app has no accounts: no
//  Auth controllers, no User model, no users table. The
//  account pages are 500s, api/user sends visitors to that
//  broken login, and php artisan route:list stops at the
//  first missing controller.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Routes;

use Tests\FeatureTestCase;








// -----------------------------------------------------------
// RouteDefectsTest
// -----------------------------------------------------------
//
// One pin per test, through pinDefect.
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

class RouteDefectsTest extends FeatureTestCase
{

    // The account routes and api/user, as a visitor would try
    // them
    private const DEAD = [
        'GET /login',
        'POST /login',
        'GET /register',
        'GET /password/reset',
        'GET /password/reset/some-token',
        'POST /logout',
        'GET /api/user',
    ];






    // -----------------------------------------------------------
    // testAccountRoutesAreNotFound
    // -----------------------------------------------------------
    //
    // [account-routes] A site without accounts answers its
    // account paths with 404. Every one of them is a 500 —
    // the controller class does not exist — or, for
    // api/user, a redirect to the 500 of /login.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testAccountRoutesAreNotFound(): void
    {
        $this->pinDefect('account-routes', function () {
            $statuses = [];
            foreach (self::DEAD as $route) {
                list($method, $path) = explode(' ', $route);
                $statuses[$route] = $this->call($method, $path)->getStatusCode();
            }
            $answers = implode(', ', array_map(function (string $route, int $status) {
                return "$route $status";
            }, array_keys($statuses), $statuses));
            $this->assertSame(array_fill_keys(self::DEAD, 404), $statuses, "the account paths answer $answers");
        });
    }






    // -----------------------------------------------------------
    // testRouteListOpens
    // -----------------------------------------------------------
    //
    // [route-list] php artisan route:list prints the table.
    // It builds every route's controller to gather its
    // middleware, and stops at LoginController, which does
    // not exist.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRouteListOpens(): void
    {
        $this->pinDefect('route-list', function () {
            list($code, $lines) = $this->console('route:list');
            $this->assertSame(0, $code, 'route:list fails: ' . implode(' ', array_slice($lines, 0, 2)));
        });
    }
}
