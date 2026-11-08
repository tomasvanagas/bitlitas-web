<?php

// -----------------------------------------------------------
//  [*] cache — Laravel's cache stores
//
//  The app caches nothing itself; the cache matters for one
//  thing, the /blokai throttle, whose counters live in the
//  default store. That store is files under
//  storage/framework/cache/data, inside the container, so a
//  restarted container starts its throttle afresh. The test
//  suite switches its in-process app to the array store.
//
//  Used by:
//    - Laravel's configuration — read as config('cache.…') by
//      the cache manager and the throttle middleware
// -----------------------------------------------------------


use Illuminate\Support\Str;

return [

    // The store used when none is named: files
    'default' => env('CACHE_DRIVER', 'file'),

    // The stores Laravel offers, as the scaffold set them up;
    // memcached takes no driver options
    'stores' => [

        'apc' => [
            'driver' => 'apc',
        ],

        'array' => [
            'driver' => 'array',
        ],

        'database' => [
            'driver'     => 'database',
            'table'      => 'cache',
            'connection' => null,
        ],

        'file' => [
            'driver' => 'file',
            'path'   => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver'        => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl'          => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options'       => [],
            'servers'       => [
                [
                    'host'   => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port'   => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver'     => 'redis',
            'connection' => 'default',
        ],

    ],

    // Put in front of every key, so apps sharing a store keep
    // apart: the app's name as a slug
    'prefix' => env(
        'CACHE_PREFIX',
        Str::slug(env('APP_NAME', 'laravel'), '_').'_cache'
    ),

];
