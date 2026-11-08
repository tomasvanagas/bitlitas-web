<?php

// -----------------------------------------------------------
//  [*] web — the site's routes
//
//  The routes of the web middleware group, which gives each
//  of them a session, encrypted cookies and CSRF checks. The
//  site is one page and the sync's door:
//
//    GET /       — the home page
//    GET /blokai — one sync with the node, as JSON
//
//  Used by:
//    - RouteServiceProvider::mapWebRoutes — loads it in the
//      web group
// -----------------------------------------------------------


Route::get('/', 'HomeController@index')->name('home');

// Syncs with the full node; throttled because every visit queries it
Route::get('/blokai', 'BlocksController@index')->name('blocks')->middleware('throttle:10,1');
