<?php

// -----------------------------------------------------------
//  [*] auth — sign-in guards and password resets
//
//  Laravel's scaffold settings for accounts: a session guard
//  for the web, a token guard for the API, both reading
//  users through an Eloquent model, and the password-reset
//  broker. The site has no accounts — no App\User model, no
//  users or password_resets table — and no route asks for a
//  guard, so nothing reads these settings.
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
    // the app does not have
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
