<?php

// -----------------------------------------------------------
//  [*] passwords — the password-reset messages
//
//  The English messages of Laravel's password broker: a
//  password too short, a reset done, a link mailed, a token
//  or an address unknown. The app has no password reset that
//  works, so nothing shows them.
//
//  Used by:
//    - Laravel's password broker — for the scaffold's reset
//      routes, which have no controllers
// -----------------------------------------------------------


return [

    'password' => 'Passwords must be at least six characters and match the confirmation.',
    'reset'    => 'Your password has been reset!',
    'sent'     => 'We have e-mailed your password reset link!',
    'token'    => 'This password reset token is invalid.',
    'user'     => "We can't find a user with that e-mail address.",

];
