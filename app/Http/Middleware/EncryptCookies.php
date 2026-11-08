<?php

// -----------------------------------------------------------
//  [*] EncryptCookies — cookies sealed with the app's key
//
//  Encrypts every cookie the app sets and decrypts every one
//  it receives, with APP_KEY. The key is made once, by
//  runUpdateThisStack.sh in the stack's .env, and kept, so a
//  rebuilt image still reads the cookies of the one before;
//  a new key would only give every visitor a new session.
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
