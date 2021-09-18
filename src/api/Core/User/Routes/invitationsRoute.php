<?php

namespace Casanova\Manifold\Api\Core\User\Routes;

use Illuminate\Support\Facades\Route;

Route::middleware(['authjwt'])->group(function () {
    Route::get('/', 'InvitationController@getAll')->name('invitations.list')->middleware('can:read');
    Route::post('/', 'InvitationController@storeAndMail')->name('invitations.store')->middleware('can:create');
});

Route::get('/{token}', 'InvitationController@validateToken')->name('invitations.validate');
Route::post('/signup', 'InvitationController@signUpViaToken')->name('invitations.signup');

// Route::get('/test/te', function(){
//     $invoice = 1;

//     return (new \App\Notifications\InvoicePaid($invoice))
//                 ->toMail($invoice);
// });