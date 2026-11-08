<?php

// -----------------------------------------------------------
//  [*] web — the site's routes
//
//  The routes of the web middleware group, which gives each
//  of them a session, encrypted cookies and CSRF checks. The
//  site is one page and the sync's door; Auth::routes() adds
//  the account pages of Laravel's scaffold, which the app
//  never had the code for.
//
//    GET  /                       — the home page
//    GET  /blokai                 — one sync with the node, as JSON
//    GET  /login                  — the sign-in form
//    POST /login                  — signing in
//    POST /logout                 — signing out
//    GET  /register               — the sign-up form
//    POST /register               — signing up
//    GET  /password/reset         — asking for a reset link
//    POST /password/email         — mailing the reset link
//    GET  /password/reset/{token} — the new-password form
//    POST /password/reset         — setting the new password
//
//  Known bug (medium): every route Auth::routes() adds is a
//  500 (web.php:41): the Auth controllers, the User model and
//  the users table do not exist [account-routes], and php
//  artisan route:list stops at the first missing controller
//  [route-list]. Fix: drop the Auth::routes() line.
//
//  Used by:
//    - RouteServiceProvider::mapWebRoutes — loads it in the
//      web group
// -----------------------------------------------------------


Route::get('/', 'HomeController@index')->name('home');

// Syncs with the full node; throttled because every visit queries it
Route::get('/blokai', 'BlocksController@index')->name('blocks')->middleware('throttle:10,1');

Auth::routes();
