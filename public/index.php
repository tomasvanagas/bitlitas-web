<?php

// -----------------------------------------------------------
//  [*] index.php — the front controller
//
//  Every request that names no file of public/ ends up here:
//  the site's nginx hands such paths to this script. It loads
//  the autoloader and the application, passes the request
//  through the HTTP kernel, sends the response and lets the
//  kernel finish its work after the response has gone.
//
//  Used by:
//    - nginx — the site's server block in the image, for
//      every path that is no file
//    - server.php — the router of PHP's built-in server
// -----------------------------------------------------------


// When the request began, for Laravel's timing
define('LARAVEL_START', microtime(true));

// Composer's autoloader: the app's classes and vendor/
require __DIR__.'/../vendor/autoload.php';

// The application, built by bootstrap/app.php
$app = require_once __DIR__.'/../bootstrap/app.php';

// The request through the HTTP kernel, the response to the
// browser, then the kernel's terminate hooks
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
