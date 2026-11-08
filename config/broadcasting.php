<?php

// -----------------------------------------------------------
//  [*] broadcasting — where broadcast events would go
//
//  Laravel's scaffold settings for broadcasting events to
//  websockets. The app broadcasts nothing: the default
//  driver is null, which drops every event, and
//  BroadcastServiceProvider is switched off in
//  config/app.php.
//
//  Used by:
//    - Laravel's configuration — read as
//      config('broadcasting.…') by the broadcast manager
// -----------------------------------------------------------


return [

    // The driver events go to: null, which drops them
    'default' => env('BROADCAST_DRIVER', 'null'),

    // The drivers Laravel offers, as the scaffold set them up;
    // none is used
    'connections' => [

        'pusher' => [
            'driver'  => 'pusher',
            'key'     => env('PUSHER_APP_KEY'),
            'secret'  => env('PUSHER_APP_SECRET'),
            'app_id'  => env('PUSHER_APP_ID'),
            'options' => [
                'cluster'   => env('PUSHER_APP_CLUSTER'),
                'encrypted' => true,
            ],
        ],

        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
