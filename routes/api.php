<?php

// -----------------------------------------------------------
//  [*] api — the API routes
//
//  The routes of the api middleware group, under /api:
//  stateless, and throttled to 60 requests a minute. The one
//  route is the scaffold's, which returns the user an API
//  token belongs to; the app has no users.
//
//    GET /api/user — the token's user, behind auth:api
//
//  Known bug (medium): a visitor without a token is
//  redirected to /login, which is a 500 itself, and one with
//  a token gets a 500, since there is no users table to look
//  the token up in (api.php:27) [account-routes]. Fix: drop
//  the route.
//
//  Used by:
//    - RouteServiceProvider::mapApiRoutes — loads it under
//      /api in the api group
// -----------------------------------------------------------


use Illuminate\Http\Request;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
