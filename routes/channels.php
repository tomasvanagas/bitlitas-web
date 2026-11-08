<?php

// -----------------------------------------------------------
//  [*] channels — the private broadcast channels
//
//  The scaffold's one channel, App.User.{id}, open to the
//  user of that id. Dead code: the app broadcasts nothing,
//  and BroadcastServiceProvider, which would load this file,
//  is switched off in config/app.php.
//
//  Used by:
//    - BroadcastServiceProvider::boot — which nothing runs at
//      the moment
// -----------------------------------------------------------


Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
