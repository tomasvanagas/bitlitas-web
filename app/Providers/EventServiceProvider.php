<?php

// -----------------------------------------------------------
//  [*] EventServiceProvider — the event listeners
//
//  Registers which listener classes handle which events.
//  The only mapping is the scaffold's sample, an event and a
//  listener that do not exist; the app fires no event of its
//  own, so nothing ever looks them up.
//
//  Used by:
//    - config/app.php — listed among the app's providers
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;








// -----------------------------------------------------------
// EventServiceProvider
// -----------------------------------------------------------
//
// The framework's provider with the app's listener list;
// Event is imported for listeners written in boot, and none
// are.
//
//   boot — registers the listeners
//
// Used by:
//   - config/app.php
// -----------------------------------------------------------

class EventServiceProvider extends ServiceProvider
{

    // Event class → its listener classes: the scaffold's
    // placeholder, naming classes the app does not have
    protected $listen = [
        'App\Events\Event' => [
            'App\Listeners\EventListener',
        ],
    ];






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Hands $listen to the event dispatcher; nothing more.
    //
    // Used by:
    //   - Laravel — when the app boots
    // -----------------------------------------------------------

    public function boot()
    {
        parent::boot();
    }
}
