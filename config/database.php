<?php

// -----------------------------------------------------------
//  [*] database — the connection to MySQL
//
//  The app's one database is the bitlitas-mysql service, MySQL
//  5.7 on its stock settings. The .env file the Dockerfile
//  writes names its host, database, user and password as
//  references — ${DB_HOST} and the like — which the
//  environment docker-compose.yml sets fills in. Laravel
//  connects in strict mode with utf8mb4, so its tables are
//  utf8mb4 whatever the database's own default (latin1 on
//  the official image).
//
//  Used by:
//    - Laravel's configuration — read as config('database.…')
//      by the database manager and the migrator
// -----------------------------------------------------------


return [

    // The connection used when none is named: MySQL
    'default' => env('DB_CONNECTION', 'mysql'),

    // The connections Laravel offers; only mysql is used, the
    // others are the scaffold's
    'connections' => [

        'sqlite' => [
            'driver'   => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'   => '',
        ],

        'mysql' => [
            'driver'      => 'mysql',
            'host'        => env('DB_HOST', '127.0.0.1'),
            'port'        => env('DB_PORT', '3306'),
            'database'    => env('DB_DATABASE', 'forge'),
            'username'    => env('DB_USERNAME', 'forge'),
            'password'    => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset'     => 'utf8mb4',
            'collation'   => 'utf8mb4_unicode_ci',
            'prefix'      => '',
            'strict'      => true,
            'engine'      => null,
        ],

        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => env('DB_HOST', '127.0.0.1'),
            'port'     => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8',
            'prefix'   => '',
            'schema'   => 'public',
            'sslmode'  => 'prefer',
        ],

        'sqlsrv' => [
            'driver'   => 'sqlsrv',
            'host'     => env('DB_HOST', 'localhost'),
            'port'     => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
            'charset'  => 'utf8',
            'prefix'   => '',
        ],

    ],

    // The table that records which migrations have run
    'migrations' => 'migrations',

    // Redis, as the scaffold set it up; the app uses none
    'redis' => [

        'client' => 'predis',

        'default' => [
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD', null),
            'port'     => env('REDIS_PORT', 6379),
            'database' => 0,
        ],

    ],

];
