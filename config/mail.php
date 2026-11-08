<?php

// -----------------------------------------------------------
//  [*] mail — how mail would be sent
//
//  One SMTP mailer: Mailgun's server, port 587 with TLS, and
//  the scaffold's placeholder sender. The driver key at the
//  top level makes Laravel build its default mailer from
//  this whole file, so the framework's own mailers list goes
//  unused. The app sends no mail, and the container has no
//  outside network beyond bitlitas-exit's route to the node.
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
