<?php

// -----------------------------------------------------------
//  [*] RedirectIfAuthenticated — keeps signed-in users off
//      the guest pages
//
//  The scaffold's guest middleware: a visitor who is already
//  signed in is sent to /home instead of the page asked for.
//  The app has no accounts — no route is guarded by it, and
//  no /home route exists.
//
//  Used by:
//    - Kernel (app/Http) — the guest alias, which no route
//      uses at the moment
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;








// -----------------------------------------------------------
// RedirectIfAuthenticated
// -----------------------------------------------------------
//
//   handle — sends a signed-in user to /home
//
// Used by:
//   - Kernel (app/Http) — the guest alias
// -----------------------------------------------------------

class RedirectIfAuthenticated
{






    // -----------------------------------------------------------
    // handle
    // -----------------------------------------------------------
    //
    // Redirects to /home when the guard — the default one, or
    // the one the route names — has a user; lets the request
    // through otherwise.
    //
    // Used by:
    //   - the guest alias — nothing uses it at the moment
    // -----------------------------------------------------------

    public function handle($request, Closure $next, $guard = null)
    {
        if (Auth::guard($guard)->check()) {
            return redirect('/home');
        }

        return $next($request);
    }
}
