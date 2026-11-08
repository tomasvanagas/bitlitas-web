<?php

// -----------------------------------------------------------
//  [*] session — the session every page view starts
//
//  The web middleware group starts a session for every
//  visitor, kept for two hours in files under
//  storage/framework/sessions, and identified by the
//  bitlitas_session cookie — the app's name as a slug. The
//  site stores nothing in it; the session exists because the
//  web group, CSRF tokens included, needs one.
//
//  Known bug (low): the session and XSRF cookies are not
//  marked Secure (session.php:67), though the site is served
//  over HTTPS only, so a browser would send them over plain
//  HTTP too [session-cookie-secure]. Fix: set
//  SESSION_SECURE_COOKIE=true in docker-compose.yml.
//
//  Used by:
//    - Laravel's configuration — read as config('session.…')
//      by StartSession and VerifyCsrfToken
// -----------------------------------------------------------


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
        str_slug(env('APP_NAME', 'laravel'), '_').'_session'
    ),

    'path' => '/',

    'domain' => env('SESSION_DOMAIN', null),

    // Whether the cookies are sent over HTTPS only, and kept
    // from JavaScript
    'secure' => env('SESSION_SECURE_COOKIE', false),

    'http_only' => true,

    // No SameSite attribute: the CSRF token protects the posts
    'same_site' => null,

];
