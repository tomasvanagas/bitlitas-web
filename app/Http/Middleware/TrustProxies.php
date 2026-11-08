<?php

// -----------------------------------------------------------
//  [*] TrustProxies — believes the forwarded headers
//
//  Laravel's proxy trust, opened wide: every proxy is
//  trusted and all four X-Forwarded headers are believed.
//  Apache is reached only through the bitlitas-endpoint
//  Caddy, on the stack's internal network
//  (docker-compose.yml), and mod_remoteip has already put
//  the visitor's address in place of the endpoint's
//  (apache/000-default.conf). The endpoint sets
//  X-Forwarded-Proto to https — which is what makes the
//  URLs Laravel builds come out as https.
//
//  Used by:
//    - Kernel (app/Http) — the global middleware
// -----------------------------------------------------------


namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;








// -----------------------------------------------------------
// TrustProxies
// -----------------------------------------------------------
//
// Takes the scheme, host, port and client address the
// forwarded headers name from any peer, and refuses
// nothing. Apache trusts every proxy as well: mod_remoteip
// has taken the first X-Forwarded-For address as the
// visitor and removed the header, so Laravel's client
// address is the one Apache resolved. That address keys
// the /blokai throttle, which so counts each visitor apart.
//
// Used by:
//   - Kernel (app/Http) — every request
// -----------------------------------------------------------

class TrustProxies extends Middleware
{

    // The peers trusted as proxies: all of them
    protected $proxies = '*';

    // The forwarded headers believed: For, Host, Port and
    // Proto, as Symfony's bit set
    protected $headers = Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO;
}
