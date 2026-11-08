<?php

// -----------------------------------------------------------
//  [*] RouteServiceProvider — where the routes come from
//
//  Loads the two route files: routes/api.php under the /api
//  prefix in the api middleware group, routes/web.php in the
//  web group. Controller names in both resolve under
//  App\Http\Controllers.
//
//  Used by:
//    - config/app.php — listed among the app's providers
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;








// -----------------------------------------------------------
// RouteServiceProvider
// -----------------------------------------------------------
//
//   boot         — the framework's route setup
//   map          — both route files
//   mapWebRoutes — routes/web.php
//   mapApiRoutes — routes/api.php
//
// Used by:
//   - config/app.php
// -----------------------------------------------------------

class RouteServiceProvider extends ServiceProvider
{

    // The namespace a route's "Controller@method" string is
    // read in, and the root of action URLs
    protected $namespace = 'App\Http\Controllers';






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // No route model bindings or patterns of the app's own;
    // the parent loads the routes through map.
    //
    // Used by:
    //   - Laravel — when the app boots
    // -----------------------------------------------------------

    public function boot()
    {
        parent::boot();
    }






    // -----------------------------------------------------------
    // map
    // -----------------------------------------------------------
    //
    // Both route files, the api one first.
    //
    // Used by:
    //   - the parent's boot — when the routes are loaded
    // -----------------------------------------------------------

    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();
    }






    // -----------------------------------------------------------
    // mapWebRoutes
    // -----------------------------------------------------------
    //
    // routes/web.php in the web group: sessions, cookies and
    // CSRF checks for every route of it.
    //
    // Used by:
    //   - map (above)
    // -----------------------------------------------------------

    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }






    // -----------------------------------------------------------
    // mapApiRoutes
    // -----------------------------------------------------------
    //
    // routes/api.php under /api in the api group: stateless,
    // and throttled to 60 requests a minute.
    //
    // Used by:
    //   - map (above)
    // -----------------------------------------------------------

    protected function mapApiRoutes()
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path('routes/api.php'));
    }
}
