<?php

// -----------------------------------------------------------
//  [*] RouteInventoryTest — every route the app answers
//
//  Reads the live route table and holds it to the list: the
//  home page, the sync's /blokai, the account routes
//  Auth::routes() adds and the api/user route of Laravel's
//  scaffold. A route added or dropped without a decision
//  fails here. The account routes and api/user lead nowhere
//  — their controllers and the users table were never part
//  of this app — and are pinned in RouteDefectsTest.
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
// adds it to every GET. Middleware is read from the route
// alone: gathering a controller's middleware (the account
// controllers' guest, in Laravel's scaffold) would build the
// controller, and the account routes' controllers do not
// exist.
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
        'GET /'                         => ['App\Http\Controllers\HomeController@index', ['web']],
        'GET blokai'                    => ['App\Http\Controllers\BlocksController@index', ['web', 'throttle:10,1']],
        'GET login'                     => ['App\Http\Controllers\Auth\LoginController@showLoginForm', ['web']],
        'POST login'                    => ['App\Http\Controllers\Auth\LoginController@login', ['web']],
        'POST logout'                   => ['App\Http\Controllers\Auth\LoginController@logout', ['web']],
        'GET register'                  => ['App\Http\Controllers\Auth\RegisterController@showRegistrationForm', ['web']],
        'POST register'                 => ['App\Http\Controllers\Auth\RegisterController@register', ['web']],
        'GET password/reset'            => ['App\Http\Controllers\Auth\ForgotPasswordController@showLinkRequestForm', ['web']],
        'POST password/email'           => ['App\Http\Controllers\Auth\ForgotPasswordController@sendResetLinkEmail', ['web']],
        'GET password/reset/{token}'    => ['App\Http\Controllers\Auth\ResetPasswordController@showResetForm', ['web']],
        'POST password/reset'           => ['App\Http\Controllers\Auth\ResetPasswordController@reset', ['web']],
        'GET api/user'                  => ['Closure', ['api', 'auth:api']],
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
        $response->assertSee('Sorry, the page you are looking for could not be found.');
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
            /** @var Route $route */
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $routes["$method {$route->uri()}"] = [$route->getActionName(), $route->middleware()];
            }
        }
        ksort($routes);
        return $routes;
    }
}
