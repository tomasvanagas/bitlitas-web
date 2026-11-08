<?php

// -----------------------------------------------------------
//  [*] VerifyCsrfToken — posts must carry the session's token
//
//  Refuses a POST, PUT, PATCH or DELETE of the web group
//  whose CSRF token does not match the session's, with a 419
//  page, and sets the XSRF-TOKEN cookie on every answer. The
//  site has no form of its own, and no web route takes a
//  post.
//
//  Used by:
//    - Kernel (app/Http) — the web group
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
