<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\ImageProcessingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::except([
            '*',
        ]);

        Event::listen(
            \App\Events\TripCreated::class,
            [\App\Listeners\SendTripCreatedSlackAlert::class, 'handle']
        );

        Event::listen(
            [\App\Listeners\SendNewUserSignupEmailToAdmins::class, 'handle']
        );

        \App\Models\Incident::observe(\App\Observers\IncidentObserver::class);
        \App\Models\IncidentNote::observe(\App\Observers\IncidentNoteObserver::class);

        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'cave' => \App\Models\Cave::class,
            'cave_system' => \App\Models\CaveSystem::class,
            'route' => \App\Models\Route::class,
            'collection' => \App\Models\Collection::class,
        ]);

        // Add your custom route binding here
        Route::bind('user_without_scopes', function ($id) {
            // Use the correct namespace for your User model
            return User::withoutGlobalScopes()->findOrFail($id);
        });
    }
}
