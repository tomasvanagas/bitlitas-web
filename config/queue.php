<?php

// -----------------------------------------------------------
//  [*] queue — where queued jobs would run
//
//  Laravel's scaffold queue settings. The app queues nothing;
//  with the default sync driver a job would run at once,
//  inside the request that dispatched it, so no worker is
//  needed.
//
//  Used by:
//    - Laravel's configuration — read as config('queue.…') by
//      the queue manager
// -----------------------------------------------------------


return [

    // The connection used when none is named: run at once
    'default' => env('QUEUE_DRIVER', 'sync'),

    // The back-ends Laravel offers, as the scaffold set them
    // up; the sqs values are its placeholders
    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver'      => 'database',
            'table'       => 'jobs',
            'queue'       => 'default',
            'retry_after' => 90,
        ],

        'beanstalkd' => [
            'driver'      => 'beanstalkd',
            'host'        => 'localhost',
            'queue'       => 'default',
            'retry_after' => 90,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key'    => env('SQS_KEY', 'your-public-key'),
            'secret' => env('SQS_SECRET', 'your-secret-key'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue'  => env('SQS_QUEUE', 'your-queue-name'),
            'region' => env('SQS_REGION', 'us-east-1'),
        ],

        'redis' => [
            'driver'      => 'redis',
            'connection'  => 'default',
            'queue'       => 'default',
            'retry_after' => 90,
        ],

    ],

    // Where failed jobs would be recorded
    'failed' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table'    => 'failed_jobs',
    ],

];
