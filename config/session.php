<?php

// -----------------------------------------------------------
//  [*] session — the session a route could start
//
//  No route starts a session: the site has no form and
//  keeps nothing in one, so the web middleware group
//  carries no session middleware (app/Http/Kernel.php).
//  These are the settings a session would have — two hours
//  in files under storage/framework/sessions, behind the
//  bitlitas_session cookie, the app's name as a slug — with
//  its cookies marked Secure unless SESSION_SECURE_COOKIE
//  says otherwise, since the site is served over HTTPS only.
//
//  Used by:
//    - Laravel's configuration — read as config('session.…')
//      by the session manager, which nothing calls at the
//      moment
// -----------------------------------------------------------


use Illuminate\Support\Str;

return [

    // Sessions are files; the test suite's in-process app
    // keeps them in memory instead
    'driver' => env('SESSION_DRIVER', 'file'),

    // Minutes a session may sit idle, and whether it ends with
    // the browser
    'lifetime' => env('SESSION_LIFETIME', 120),

    'expire_on_close' => false,

    // The session's data is stored as it is, not encrypted
    'encrypt' => false,

    // Where the file driver keeps the sessions
    'files' => storage_path('framework/sessions'),

    // The connection, table and cache store of the database,
    // redis and cache drivers, which the app does not use
    'connection' => null,

    'table' => 'sessions',

    'store' => null,

    // The odds, 2 in 100, that a request sweeps the expired
    // session files
    'lottery' => [2, 100],

    // The session cookie's name, path and domain
    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_session'
    ),

    'path' => '/',

    'domain' => env('SESSION_DOMAIN', null),

    // Whether the cookies are sent over HTTPS only, and kept
    // from JavaScript
    'secure' => env('SESSION_SECURE_COOKIE', true),

    'http_only' => true,

    // No SameSite attribute: the CSRF token protects the posts
    'same_site' => null,

];
