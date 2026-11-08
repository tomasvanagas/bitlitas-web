<?php

// -----------------------------------------------------------
//  [*] filesystems — Laravel's storage disks
//
//  The scaffold's disks: storage/app on the local disk, a
//  public one under storage/app/public, and an S3 bucket.
//  The app stores no files through them — the downloads and
//  pictures are plain files of public/, served by nginx.
//
//  Used by:
//    - Laravel's configuration — read as
//      config('filesystems.…') by the Storage facade
// -----------------------------------------------------------


return [

    // The disk used when none is named, and the one bound as
    // the cloud disk
    'default' => env('FILESYSTEM_DRIVER', 'local'),

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    // The disks, as the scaffold set them up
    'disks' => [

        'local' => [
            'driver' => 'local',
            'root'   => storage_path('app'),
        ],

        'public' => [
            'driver'     => 'local',
            'root'       => storage_path('app/public'),
            'url'        => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key'    => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
        ],

    ],

];
