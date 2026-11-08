<?php

// -----------------------------------------------------------
//  [*] UserFactory — the scaffold's fake users
//
//  Defines how Faker would make an App\User: a name, a
//  unique e-mail address, the password "secret" and a
//  remember token. Dead code: nothing loads the file — a
//  Laravel factory is a class, and this is a closure handed
//  to a $factory nothing defines — and it could not run if
//  something did: the app has no App\User model or users
//  table, and the str_random helper it calls does not exist.
//
//  Used by:
//    - nothing loads it at the moment
// -----------------------------------------------------------


use Faker\Generator as Faker;

$factory->define(App\User::class, function (Faker $faker) {
    return [
        'name'           => $faker->name,
        'email'          => $faker->unique()->safeEmail,
        'password'       => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // bcrypt of "secret"
        'remember_token' => str_random(10),
    ];
});
