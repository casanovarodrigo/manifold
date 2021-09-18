<?php

namespace Casanova\Manifold\Api\Core\User\Routes;

use Illuminate\Support\Facades\Route;

Route::middleware(['authjwt', 'getUserResource'])->group(function () {
    Route::get('/', 'UserController@getAll')->name('users.list')->middleware('can:read');
    Route::get('/{id}', 'UserController@getOne')->name('users.getOne')->middleware('can:read');
    Route::put('/{id}/editbasic', 'UserController@editBasicInfo')->name('users.editBasic')->middleware('can:update');
    Route::put('/{id}/editpassword', 'UserController@editPassword')->name('users.editPassword')->middleware('can:update');
    Route::delete('/{id}', 'UserController@remove')->name('users.remove')->middleware('can:delete');
    Route::delete('/{id}/force', 'UserController@forceRemove')->name('users.forceRemove')->middleware('can:delete');
});

// JUST for Postman Tinkering
// Route::post('/', 'UserController@store'); // Comment for production mode

Route::post('/signin', 'UserController@signIn')->name('users.signin');
Route::post('/mailrecoverytoken', 'UserController@mailRecoveryToken')->name('users.mailrecoverytoken');
Route::post('/passwordrecovery/{token}', 'UserController@passwordRecovery')->name('users.passwordrecovery');
