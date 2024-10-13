<?php

use Illuminate\Support\Facades\Route;

Route::get('/api/google/redirect', [App\Http\Controllers\GoogleLoginController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/api/google/callback', [App\Http\Controllers\GoogleLoginController::class, 'handleGoogleCallback'])->name('google.callback');

// Vue Spa routing
Route::fallback(function () {
    return file_get_contents(public_path('index.html'));
});