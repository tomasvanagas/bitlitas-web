<?php

// -----------------------------------------------------------
//  [*] app — the application's own settings
//
//  Its name, environment and debug switch, the public URL,
//  the clock and the language, the encryption key, the log,
//  and the service providers and facades the app boots
//  with. The environment comes from the .env file the
//  Dockerfile writes and from what docker-compose.yml sets;
//  the image ships APP_ENV=production and a key generated at
//  build time.
//
//  Known bug (medium): debug mode defaults to on when
//  APP_DEBUG is missing (app.php:39), so a container started
//  without it shows the code and stack trace of every error
//  to the visitor who caused it; only APP_DEBUG=false in
//  docker-compose.yml keeps it off [debug-by-default]. Fix:
//  default it to false.
//
//  Used by:
//    - Laravel's configuration — read as config('app.…')
// -----------------------------------------------------------


return [

    // The app's name, also the start of the cache prefix and
    // the session cookie's name
    'name' => env('APP_NAME', 'Bitlitas'),

    // The environment the app runs in; the image's .env says
    // production
    'env' => env('APP_ENV', 'development'),

    // Debug mode: the code and the stack trace of every error
    // on the error page, and the node's errors in the answers
    // of /blokai
    'debug' => env('APP_DEBUG', true),

    // The public address, for the URLs commands generate
    'url' => env('APP_URL', 'https://bitlitas.lt'),

    // The clock PHP's date functions and the database's
    // timestamps run on
    'timezone' => 'UTC',

    // The language of translated strings, and the one to fall
    // back on — only English files ship, so every framework
    // message reads in English
    'locale' => 'lt',

    'fallback_locale' => 'en',

    // The 32-byte key of cookies, sessions and encrypted
    // values, generated when the image is built
    'key' => env('APP_KEY'),

    'cipher' => 'AES-256-CBC',

    // One log file, storage/logs/laravel.log — a bind mount in
    // production — written at every level from debug up
    'log' => env('APP_LOG', 'single'),

    'log_level' => env('APP_LOG_LEVEL', 'debug'),

    // The providers the app boots with. Package discovery is
    // off (the Dockerfile writes an empty manifest), so a
    // package's provider runs only when it is listed here, and
    // none is
    'providers' => [

        // The framework's own
        Illuminate\Auth\AuthServiceProvider::class,
        Illuminate\Broadcasting\BroadcastServiceProvider::class,
        Illuminate\Bus\BusServiceProvider::class,
        Illuminate\Cache\CacheServiceProvider::class,
        Illuminate\Foundation\Providers\ConsoleSupportServiceProvider::class,
        Illuminate\Cookie\CookieServiceProvider::class,
        Illuminate\Database\DatabaseServiceProvider::class,
        Illuminate\Encryption\EncryptionServiceProvider::class,
        Illuminate\Filesystem\FilesystemServiceProvider::class,
        Illuminate\Foundation\Providers\FoundationServiceProvider::class,
        Illuminate\Hashing\HashServiceProvider::class,
        Illuminate\Mail\MailServiceProvider::class,
        Illuminate\Notifications\NotificationServiceProvider::class,
        Illuminate\Pagination\PaginationServiceProvider::class,
        Illuminate\Pipeline\PipelineServiceProvider::class,
        Illuminate\Queue\QueueServiceProvider::class,
        Illuminate\Redis\RedisServiceProvider::class,
        Illuminate\Auth\Passwords\PasswordResetServiceProvider::class,
        Illuminate\Session\SessionServiceProvider::class,
        Illuminate\Translation\TranslationServiceProvider::class,
        Illuminate\Validation\ValidationServiceProvider::class,
        Illuminate\View\ViewServiceProvider::class,

        // The app's own; BroadcastServiceProvider is switched
        // off, and with it routes/channels.php
        App\Providers\AppServiceProvider::class,
        App\Providers\AuthServiceProvider::class,
        App\Providers\EventServiceProvider::class,
        App\Providers\RouteServiceProvider::class,

    ],

    // The facades' short names, registered when the app
    // starts and loaded only when used
    'aliases' => [

        'App'          => Illuminate\Support\Facades\App::class,
        'Artisan'      => Illuminate\Support\Facades\Artisan::class,
        'Auth'         => Illuminate\Support\Facades\Auth::class,
        'Blade'        => Illuminate\Support\Facades\Blade::class,
        'Broadcast'    => Illuminate\Support\Facades\Broadcast::class,
        'Bus'          => Illuminate\Support\Facades\Bus::class,
        'Cache'        => Illuminate\Support\Facades\Cache::class,
        'Config'       => Illuminate\Support\Facades\Config::class,
        'Cookie'       => Illuminate\Support\Facades\Cookie::class,
        'Crypt'        => Illuminate\Support\Facades\Crypt::class,
        'DB'           => Illuminate\Support\Facades\DB::class,
        'Eloquent'     => Illuminate\Database\Eloquent\Model::class,
        'Event'        => Illuminate\Support\Facades\Event::class,
        'File'         => Illuminate\Support\Facades\File::class,
        'Gate'         => Illuminate\Support\Facades\Gate::class,
        'Hash'         => Illuminate\Support\Facades\Hash::class,
        'Lang'         => Illuminate\Support\Facades\Lang::class,
        'Log'          => Illuminate\Support\Facades\Log::class,
        'Mail'         => Illuminate\Support\Facades\Mail::class,
        'Notification' => Illuminate\Support\Facades\Notification::class,
        'Password'     => Illuminate\Support\Facades\Password::class,
        'Queue'        => Illuminate\Support\Facades\Queue::class,
        'Redirect'     => Illuminate\Support\Facades\Redirect::class,
        'Redis'        => Illuminate\Support\Facades\Redis::class,
        'Request'      => Illuminate\Support\Facades\Request::class,
        'Response'     => Illuminate\Support\Facades\Response::class,
        'Route'        => Illuminate\Support\Facades\Route::class,
        'Schema'       => Illuminate\Support\Facades\Schema::class,
        'Session'      => Illuminate\Support\Facades\Session::class,
        'Storage'      => Illuminate\Support\Facades\Storage::class,
        'URL'          => Illuminate\Support\Facades\URL::class,
        'Validator'    => Illuminate\Support\Facades\Validator::class,
        'View'         => Illuminate\Support\Facades\View::class,

    ],

];
