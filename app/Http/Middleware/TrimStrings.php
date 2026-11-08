<?php

// -----------------------------------------------------------
//  [*] TrimStrings — inputs without their surrounding blanks
//
//  Trims the blanks around every string input of a request
//  before the app sees it, except the password fields, whose
//  blanks are part of the password.
//
//  Used by:
//    - Kernel (app/Http) — the global middleware
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as Middleware;








// -----------------------------------------------------------
// TrimStrings
// -----------------------------------------------------------
//
// The framework's middleware with the app's list of
// untouched inputs.
//
// Used by:
//   - Kernel (app/Http) — every request
// -----------------------------------------------------------

class TrimStrings extends Middleware
{

    // Inputs left as typed
    protected $except = [
        'password',
        'password_confirmation',
    ];
}
