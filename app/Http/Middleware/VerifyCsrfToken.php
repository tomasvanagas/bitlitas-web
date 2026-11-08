<?php

// -----------------------------------------------------------
//  [*] VerifyCsrfToken — posts must carry the session's token
//
//  Refuses a POST, PUT, PATCH or DELETE whose CSRF token
//  does not match the session's, with a 419 page, and sets
//  the XSRF-TOKEN cookie on every answer. The site has no
//  form of its own, no route takes a post, and no route
//  starts the session the check needs, so no middleware
//  group lists it.
//
//  Used by:
//    - nothing at the moment
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;








// -----------------------------------------------------------
// VerifyCsrfToken
// -----------------------------------------------------------
//
// The framework's middleware with the app's list of
// unchecked paths, which is empty.
//
// Used by:
//   - Kernel (app/Http) — the web group
// -----------------------------------------------------------

class VerifyCsrfToken extends Middleware
{

    // Paths whose posts need no token: none
    protected $except = [];
}
