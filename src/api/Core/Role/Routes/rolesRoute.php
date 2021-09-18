<?php

namespace Casanova\Manifold\Api\Core\Role\Routes;

use Illuminate\Support\Facades\Route;

Route::middleware(['authjwt'])->group(function () {
    Route::get('/', 'RoleController@getAll')->name('roles.list')->middleware('can:read');
    Route::get('/toinvite', 'RoleController@toInvite')->name('roles.toinvite')->middleware('can:read');
    Route::post('/', 'RoleController@store')->name('roles.store')->middleware('can:create');
    Route::get('/{id}', 'RoleController@getOne')->name('roles.getOne')->middleware('can:read');
    Route::put('/{id}', 'RoleController@edit')->name('roles.edit')->middleware('can:update');
    Route::delete('/{id}', 'RoleController@remove')->name('roles.remove')->middleware('can:delete');
});