<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use App\Models\User;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::except([
            '*'
        ]);

        Event::listen(
            \App\Events\TripCreated::class,
            [\App\Listeners\SendTripCreatedSlackAlert::class, 'handle']
        );

        Event::listen(
            \App\Events\UserCreated::class,
            [\App\Listeners\SendNewUserSignupEmailToAdmins::class, 'handle']
        );

        // Add your custom route binding here
        Route::bind('user_without_scopes', function($id) {
            // Use the correct namespace for your User model
            return User::withoutGlobalScopes()->findOrFail($id);
        });
    }
}
