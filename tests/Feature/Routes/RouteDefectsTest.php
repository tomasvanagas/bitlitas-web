<?php

// -----------------------------------------------------------
//  [*] RouteDefectsTest — routes that lead nowhere
//
//  The pinned defects of the route table (tests/README.md):
//  each test states the wanted behaviour and stays
//  incomplete while the defect reproduces. The app has no
//  accounts — no Auth controllers, no User model, no users
//  table — yet routes/api.php has the scaffold's api/user,
//  which returns the user of an API token.
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

    // The paths of Laravel's account pages and api/user, as a
    // visitor would try them — api/user with and without an
    // API token
    private const DEAD = [
        'GET /login',
        'POST /login',
        'GET /register',
        'GET /password/reset',
        'GET /password/reset/some-token',
        'POST /logout',
        'GET /api/user',
        'GET /api/user?api_token=some-token',
    ];






    // -----------------------------------------------------------
    // testAccountRoutesAreNotFound
    // -----------------------------------------------------------
    //
    // [account-routes] A site without accounts answers its
    // account paths with 404. The account pages do; api/user
    // is a 401 without a token, and with one the token guard
    // looks for an App\User model that does not exist — a 500.
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
}
