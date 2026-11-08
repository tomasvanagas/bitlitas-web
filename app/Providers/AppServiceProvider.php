<?php

// -----------------------------------------------------------
//  [*] AppServiceProvider — the app's own services
//
//  Binds the one service the app adds to Laravel's
//  container: DaemonClient, the client of the full node,
//  built once per request from config/services.php's
//  bitlitas settings — BITLITAS_DAEMON_URL and
//  BITLITAS_DAEMON_LOGIN.
//
//  Used by:
//    - config/app.php — listed among the app's providers
// -----------------------------------------------------------


namespace App\Providers;

use App\Services\DaemonClient;
use Illuminate\Support\ServiceProvider;








// -----------------------------------------------------------
// AppServiceProvider
// -----------------------------------------------------------
//
//   boot     — nothing to wire
//   register — the DaemonClient binding
//
// Used by:
//   - config/app.php
// -----------------------------------------------------------

class AppServiceProvider extends ServiceProvider
{






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Runs after every provider registered; the app has
    // nothing to wire at that point.
    //
    // Used by:
    //   - Laravel — when the app boots
    // -----------------------------------------------------------

    public function boot()
    {
    }






    // -----------------------------------------------------------
    // register
    // -----------------------------------------------------------
    //
    // Binds DaemonClient as a singleton: the first class that
    // asks for it — NetworkSync, through its constructor — gets
    // a client for the configured node, and every later one
    // the same client.
    //
    // Used by:
    //   - Laravel — when the app starts
    // -----------------------------------------------------------

    public function register()
    {
        // The full node the home page's statistics come from
        $this->app->singleton(DaemonClient::class, function () {
            return new DaemonClient(
                config('services.bitlitas.daemon_url'),
                config('services.bitlitas.daemon_login')
            );
        });
    }
}
