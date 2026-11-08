<?php

// -----------------------------------------------------------
//  [*] RouteInventoryTest — every route the app answers
//
//  Reads the live route table and holds it to the list: the
//  home page and the sync's /blokai, nothing else. A route
//  added or dropped without a decision fails here. The site
//  has no accounts, so the paths of Laravel's account pages
//  and of the scaffold's api/user are all 404s; php artisan
//  route:list prints the table.
//
//  Used by:
//    - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------


namespace Tests\Feature\Routes;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Tests\FeatureTestCase;








// -----------------------------------------------------------
// RouteInventoryTest
// -----------------------------------------------------------
//
// Routes are named "METHOD uri" here, HEAD left out — Laravel
// adds it to every GET. Middleware is what the route itself
// carries — its group's and what the route files add — read
// without building its controller.
//
//   routes — the live table, by name
//
// Used by:
//   - PHPUnit — the Feature suite (runTests.sh)
// -----------------------------------------------------------

class RouteInventoryTest extends FeatureTestCase
{

    // Every route, with its action and middleware
    private const ROUTES = [
        'GET /'      => ['App\Http\Controllers\HomeController@index', ['web']],
        'GET blokai' => ['App\Http\Controllers\BlocksController@index', ['web', 'throttle:10,1']],
    ];

    // The paths of Laravel's account pages and api/user, as a
    // visitor would try them — api/user with and without an
    // API token
    private const ACCOUNT_PATHS = [
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
    // testRouteTableIsTheKnownOne
    // -----------------------------------------------------------
    //
    // The table holds exactly the routes of ROUTES, each with
    // its action and middleware.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRouteTableIsTheKnownOne(): void
    {
        $expected = self::ROUTES;
        ksort($expected);

        $this->assertSame($expected, $this->routes());
    }






    // -----------------------------------------------------------
    // testUnknownPathIsNotFound
    // -----------------------------------------------------------
    //
    // A path no route answers gets the framework's 404 page.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testUnknownPathIsNotFound(): void
    {
        $response = $this->get('/no/such/page');

        $response->assertStatus(404);
        $response->assertSee('Not Found');
    }






    // -----------------------------------------------------------
    // testAccountRoutesAreNotFound
    // -----------------------------------------------------------
    //
    // A site without accounts answers its account paths with
    // 404 — api/user too, with a token or without one.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testAccountRoutesAreNotFound(): void
    {
        $statuses = [];
        foreach (self::ACCOUNT_PATHS as $route) {
            list($method, $path) = explode(' ', $route);
            $statuses[$route] = $this->call($method, $path)->getStatusCode();
        }

        $this->assertSame(array_fill_keys(self::ACCOUNT_PATHS, 404), $statuses);
    }






    // -----------------------------------------------------------
    // testRouteListPrintsTheTable
    // -----------------------------------------------------------
    //
    // php artisan route:list prints the table. It builds every
    // route's controller to gather its middleware, so a route
    // naming a controller the app lacks would stop it.
    //
    // Used by:
    //   - PHPUnit — the Feature suite (runTests.sh)
    // -----------------------------------------------------------

    public function testRouteListPrintsTheTable(): void
    {
        list($code, $lines) = $this->console('route:list');

        $this->assertSame(0, $code, implode(' ', array_slice($lines, 0, 2)));
        $this->assertStringContainsString('blokai', implode("\n", $lines));
    }






    // -----------------------------------------------------------
    // routes
    // -----------------------------------------------------------
    //
    // The live table as "METHOD uri" => [action, middleware],
    // in name order.
    //
    // Used by:
    //   - the tests above
    // -----------------------------------------------------------

    private function routes(): array
    {
        $routes = [];
        foreach (Router::getRoutes()->getRoutes() as $route) {
            // Each one an Illuminate\Routing\Route (the import above)
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $routes["$method {$route->uri()}"] = [$route->getActionName(), $route->middleware()];
            }
        }
        ksort($routes);
        return $routes;
    }
}
