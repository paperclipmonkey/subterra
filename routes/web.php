<?php

declare(strict_types=1);

use App\Http\Controllers\MagicLinkController;
use Illuminate\Support\Facades\Route;

Route::get('/api/google/redirect', [App\Http\Controllers\GoogleLoginController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/api/google/callback', [App\Http\Controllers\GoogleLoginController::class, 'handleGoogleCallback'])->name('google.callback');

// Magic link callback route (web route to handle the email link)
Route::get('/auth/magic-link', [MagicLinkController::class, 'handleWebCallback'])->name('magic-link.callback');

Route::get('/newsletter/unsubscribe/{user}', [App\Http\Controllers\NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

// Vue Spa routing
Route::fallback(function (\Illuminate\Http\Request $request) {
    // If it's an API request that reached here, it means it didn't match any API route.
    // We should let it fall through to the exception handler (or abort) to ensure a JSON response.
    if ($request->is('api/*')) {
        abort(404);
    }

    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return file_get_contents($indexPath);
    }

    return response('Frontend not built', 404);
});
