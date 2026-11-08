<?php

// -----------------------------------------------------------
//  [*] AuthServiceProvider — the authorization policies
//
//  Registers the policies Gate checks a user against. The
//  only mapping is the scaffold's sample, a model and a
//  policy that do not exist; since the app has no accounts,
//  nothing ever asks.
//
//  Used by:
//    - config/app.php — listed among the app's providers
// -----------------------------------------------------------


namespace App\Providers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;








// -----------------------------------------------------------
// AuthServiceProvider
// -----------------------------------------------------------
//
// The framework's provider with the app's policy list; Gate
// is imported for policies written in boot, and none are.
//
//   boot — registers the policies
//
// Used by:
//   - config/app.php
// -----------------------------------------------------------

class AuthServiceProvider extends ServiceProvider
{

    // Model class → policy class: the scaffold's placeholder,
    // naming classes the app does not have
    protected $policies = [
        'App\Model' => 'App\Policies\ModelPolicy',
    ];






    // -----------------------------------------------------------
    // boot
    // -----------------------------------------------------------
    //
    // Hands $policies to Gate; nothing more.
    //
    // Used by:
    //   - Laravel — when the app boots
    // -----------------------------------------------------------

    public function boot()
    {
        $this->registerPolicies();
    }
}
