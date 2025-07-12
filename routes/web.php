<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MagicLinkController;

Route::get('/api/google/redirect', [App\Http\Controllers\GoogleLoginController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/api/google/callback', [App\Http\Controllers\GoogleLoginController::class, 'handleGoogleCallback'])->name('google.callback');

// Magic link callback route (web route to handle the email link)
Route::get('/auth/magic-link', [MagicLinkController::class, 'handleWebCallback'])->name('magic-link.callback');

// Vue Spa routing
Route::fallback(function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return file_get_contents($indexPath);
    }
    return response('Frontend not built', 404);
});