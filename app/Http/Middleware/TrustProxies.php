<?php

// -----------------------------------------------------------
//  [*] TrustProxies — which proxies' headers the app believes
//
//  Every request reaches the app through the bitlitas-endpoint
//  Caddy and nginx, but no proxy is trusted here, so Laravel
//  ignores their X-Forwarded headers: the visitor's address
//  is the endpoint's on every request, and the scheme is
//  plain HTTP. That is why the /blokai throttle counts every
//  visitor as one (SyncOverHttpTest) — a limit for the whole
//  site. The page links its files by relative paths, so the
//  scheme Laravel sees changes nothing a visitor gets.
//
//  Used by:
//    - Kernel (app/Http) — the global middleware
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Fideloper\Proxy\TrustProxies as Middleware;








// -----------------------------------------------------------
// TrustProxies
// -----------------------------------------------------------
//
// fideloper/proxy's middleware with no trusted proxy and the
// headers it would read from one.
//
// Used by:
//   - Kernel (app/Http) — every request
// -----------------------------------------------------------

class TrustProxies extends Middleware
{

    // The trusted proxies' addresses: unset, so none
    protected $proxies;

    // The forwarded headers a trusted proxy's values would come
    // from
    protected $headers = [
        Request::HEADER_FORWARDED         => 'FORWARDED',
        Request::HEADER_X_FORWARDED_FOR   => 'X_FORWARDED_FOR',
        Request::HEADER_X_FORWARDED_HOST  => 'X_FORWARDED_HOST',
        Request::HEADER_X_FORWARDED_PORT  => 'X_FORWARDED_PORT',
        Request::HEADER_X_FORWARDED_PROTO => 'X_FORWARDED_PROTO',
    ];
}
