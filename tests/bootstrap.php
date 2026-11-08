<?php

// -----------------------------------------------------------
//  [*] bootstrap — the autoloaders of the suite
//
//  The app's own autoloader first — its production vendor,
//  with nothing added — then PHPUnit's from /opt/test-tools,
//  where Dockerfile.tests installs it apart from the app.
//  The Tests namespace is mapped to this folder here,
//  because the shipped image was built with composer
//  --no-dev and carries no autoload-dev map.
//
//  Used by:
//    - phpunit.xml — the bootstrap of every suite
// -----------------------------------------------------------


require __DIR__ . '/../vendor/autoload.php';
require '/opt/test-tools/vendor/autoload.php';

spl_autoload_register(function ($class) {
    if (strpos($class, 'Tests\\') !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen('Tests\\'))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
