<?php

// -----------------------------------------------------------
//  [*] BroadcastServiceProvider — the broadcast channels
//
//  Would register the routes that authorize private
//  broadcast channels and load routes/channels.php, but
//  broadcasting is switched off: config/app.php does not
//  list this provider, so neither ever happens.
//
//  Used by:
//    - nothing uses it at the moment — its line in
//      config/app.php is switched off
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Broadcast;








// -----------------------------------------------------------
// BroadcastServiceProvider
// -----------------------------------------------------------
//
//   boot — the channel routes and routes/channels.php
//
// Used by:
//   - nothing uses it at the moment
// -----------------------------------------------------------

class BroadcastServiceProvider extends ServiceProvider
{






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Registers Broadcast's authorization route and loads the
    // channel definitions.
    //
    // Used by:
    //   - Laravel — if the provider were listed
    // -----------------------------------------------------------

    public function boot()
    {
        Broadcast::routes();

        require base_path('routes/channels.php');
    }
}
