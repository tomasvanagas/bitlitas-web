<?php

// -----------------------------------------------------------
//  [*] auth — sign-in guards and password resets
//
//  Laravel's scaffold settings for accounts: a session guard
//  for the web, a token guard for the API, both reading
//  users through an Eloquent model, and the password-reset
//  broker. The site has no accounts — no App\User model, no
//  users or password_resets table — so these settings serve
//  only the dead routes that Auth::routes() and api/user add.
//
//  Known bug (medium): the account routes these settings back
//  answer with 500s, and api/user redirects to the 500 of
//  /login [account-routes] — see routes/web.php.
//
//  Used by:
//    - Laravel's configuration — read as config('auth.…') by
//      the auth guards and the password broker
// -----------------------------------------------------------


return [

    // The guard and the password broker used when none is
    // named
    'defaults' => [
        'guard'     => 'web',
        'passwords' => 'users',
    ],

    // web keeps the signed-in user in the session; api reads
    // an api_token from the request
    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'users',
        ],

        'api' => [
            'driver'   => 'token',
            'provider' => 'users',
        ],
    ],

    // Where the guards read their users: an Eloquent model
    // the app does not have. The scaffold's alternative, the
    // users table read directly, was left switched off
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\User::class,
        ],
    ],

    // The password-reset broker: its tokens in password_resets
    // (a table the app does not have), valid for 60 minutes
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table'    => 'password_resets',
            'expire'   => 60,
        ],
    ],

];
