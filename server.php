<?php

// -----------------------------------------------------------
//  [*] server.php — the router of PHP's built-in web server
//
//  Laravel's stand-in for nginx when the app is run with PHP's
//  own development server: a path naming a file of public/
//  is served as it is, everything else goes to the front
//  controller. Production never uses it — nginx serves the
//  site, and this file is outside its root.
//
//  Used by:
//    - artisan serve — which nothing runs at the moment
// -----------------------------------------------------------


$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// A file of public/ is left to the built-in server, the way
// mod_rewrite leaves it to Apache
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
