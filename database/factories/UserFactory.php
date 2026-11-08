<?php

// -----------------------------------------------------------
//  [*] UserFactory — the scaffold's fake users
//
//  Defines how Faker makes an App\User for tests and seeders:
//  a name, a unique e-mail address, the password "secret" and
//  a remember token. Dead code: the app has no App\User model
//  and no users table, and no test or seeder makes users.
//
//  Used by:
//    - Laravel's model factories — loaded with every factory
//      call, of which there are none at the moment
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
