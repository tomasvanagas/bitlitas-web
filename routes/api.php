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
//  Known bug (medium): the route is dead weight. Without a
//  token it answers 401; with one, the token guard looks for
//  an App\User model that does not exist, and the answer is a
//  500 (api.php:26) [account-routes]. Fix: drop the route.
//
//  Used by:
//    - RouteServiceProvider::mapApiRoutes — loads it under
//      /api in the api group
// -----------------------------------------------------------


use Illuminate\Http\Request;

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
