<?php

// -----------------------------------------------------------
//  [*] app — builds the application
//
//  Creates the Laravel application for the project folder
//  and binds the app's two kernels and its exception handler
//  in place of the framework's interfaces, then hands the
//  application back. Running it — a request or a command —
//  is the caller's part.
//
//  Used by:
//    - public/index.php — every web request
//    - artisan — every command
//    - CreatesApplication (tests) — the app of every test
// -----------------------------------------------------------


// The container every part of Laravel is resolved from,
// rooted at the project folder
$app = new Illuminate\Foundation\Application(
    realpath(__DIR__.'/../')
);

// The kernels that serve web requests and commands, and the
// handler of their exceptions
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

return $app;
