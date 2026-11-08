<?php

// -----------------------------------------------------------
//  [*] Kernel — the HTTP kernel and its middleware
//
//  Every web request passes through here: the global
//  middleware first, then the group its route belongs to
//  (web for routes/web.php, api for routes/api.php), then
//  the aliases a route names. Laravel's scaffold, with the
//  web group's session and CSRF middleware taken off: the
//  site has no form and keeps nothing in a session.
//
//  Used by:
//    - bootstrap/app.php — bound as the HTTP kernel
//    - public/index.php — handles every request through it
// -----------------------------------------------------------


namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;








// -----------------------------------------------------------
// Kernel
// -----------------------------------------------------------
//
// The three middleware lists Laravel's kernel reads; the
// class adds no method of its own.
//
// Used by:
//   - bootstrap/app.php, public/index.php
// -----------------------------------------------------------

class Kernel extends HttpKernel
{

    // Run on every request: the maintenance switch, the post
    // size limit, trimming and nulling of inputs, and the
    // proxy handling of TrustProxies, which trusts every proxy
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\TrustProxies::class,
    ];

    // The groups RouteServiceProvider puts the route files
    // in. web encrypts cookies and binds route parameters, and
    // starts no session: the site has no form and keeps
    // nothing in one, and a session would cost every visit
    // without a cookie a file on the RAM-backed tmpfs, kept
    // two hours, and set two cookies — so no CSRF check
    // either, which needs the session's token. api allows 60
    // requests a minute and binds route parameters.
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:60,1',
            'bindings',
        ],
    ];

    // The names a route can ask for: throttle on /blokai and
    // in the api group; auth and guest are what the
    // scaffold's account routes would use, which do not
    // exist, and auth.basic, bindings and can are not used
    protected $routeMiddleware = [
        'auth'       => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings'   => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can'        => \Illuminate\Auth\Middleware\Authorize::class,
        'guest'      => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle'   => \Illuminate\Routing\Middleware\ThrottleRequests::class,
    ];
}
