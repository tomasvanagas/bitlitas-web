<?php

// -----------------------------------------------------------
//  [*] view — where the Blade templates live
//
//  The templates are read from resources/views — the site's
//  one page, welcome.blade.php, with its layout and footer —
//  and compiled to PHP under storage/framework/views the
//  first time each one renders.
//
//  Used by:
//    - Laravel's configuration — read as config('view.…') by
//      the view factory and the Blade compiler
// -----------------------------------------------------------


return [

    // The folders templates are looked up in
    'paths' => [
        resource_path('views'),
    ],

    // Where the compiled templates go
    'compiled' => realpath(storage_path('framework/views')),

];
