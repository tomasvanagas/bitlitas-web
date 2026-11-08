<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
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
