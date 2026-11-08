<?php

Route::get('/', 'HomeController@index')->name('home');
// Syncs with the full node; throttled because every visit queries it
Route::get('/blokai', 'BlocksController@index')->name('blocks')->middleware('throttle:10,1');

Auth::routes();