<?php

// -----------------------------------------------------------
//  [*] mail — how mail would be sent
//
//  Laravel's scaffold mail settings, pointing at Mailgun's
//  SMTP server with a placeholder sender. The app sends no
//  mail; only the scaffold's password reset would, on routes
//  that do not work. The container has no outside network
//  either, beyond bitlitas-exit's route to the node.
//
//  Used by:
//    - Laravel's configuration — read as config('mail.…') by
//      the mailer
// -----------------------------------------------------------


return [

    // How mail goes out: SMTP
    'driver' => env('MAIL_DRIVER', 'smtp'),

    // The SMTP server and its port
    'host' => env('MAIL_HOST', 'smtp.mailgun.org'),

    'port' => env('MAIL_PORT', 587),

    // The sender of every mail: the scaffold's placeholder
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name'    => env('MAIL_FROM_NAME', 'Example'),
    ],

    // TLS to the server, and the account to sign in with
    'encryption' => env('MAIL_ENCRYPTION', 'tls'),

    'username' => env('MAIL_USERNAME'),

    'password' => env('MAIL_PASSWORD'),

    // The sendmail command, for the sendmail driver
    'sendmail' => '/usr/sbin/sendmail -bs',

    // The theme and component views of Markdown mails
    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
