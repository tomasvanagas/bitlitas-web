<?php

// -----------------------------------------------------------
//  [*] services — the outside services the app talks to
//
//  The one that matters is the Bitlitas full node: its RPC
//  address and login, from BITLITAS_DAEMON_URL and
//  BITLITAS_DAEMON_LOGIN, which docker-compose.yml sets for
//  bitlitas-web (the node is reached through bitlitas-exit).
//  The mail and payment entries are the scaffold's, unused;
//  stripe's model, App\User, does not exist.
//
//  Used by:
//    - AppServiceProvider — builds the DaemonClient from the
//      bitlitas entry
//    - Laravel's mailer — would read mailgun, ses and
//      sparkpost
// -----------------------------------------------------------


return [

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key'    => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model'  => App\User::class,
        'key'    => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    // The Bitlitas full node (bitlitasd) the home page's statistics come from
    'bitlitas' => [
        // Its RPC address: port 18081 on v0.11 nodes, 18281 on v0.18
        'daemon_url' => env('BITLITAS_DAEMON_URL', 'http://127.0.0.1:18081'),
        // "user:password" if the node runs with --rpc-login
        'daemon_login' => env('BITLITAS_DAEMON_LOGIN'),
    ],

];
