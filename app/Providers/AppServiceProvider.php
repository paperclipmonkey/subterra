<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

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
    }
}
