<?php

// -----------------------------------------------------------
//  [*] EncryptCookies — cookies sealed with the app's key
//
//  Encrypts every cookie the app sets and decrypts every one
//  it receives, with APP_KEY; the site sets none at the
//  moment, since no route starts a session. The key is made
//  once, by runUpdateThisStack.sh in the stack's .env, and
//  kept; a cookie sealed with another key is dropped as
//  unreadable.
//
//  Used by:
//    - Kernel (app/Http) — the first middleware of the web
//      group
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;








// -----------------------------------------------------------
// EncryptCookies
// -----------------------------------------------------------
//
// The framework's middleware with the app's list of
// exceptions, which is empty.
//
// Used by:
//   - Kernel (app/Http) — the web group
// -----------------------------------------------------------

class EncryptCookies extends Middleware
{

    // Cookies left unencrypted: none
    protected $except = [];
}
