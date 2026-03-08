<?php

namespace App\Providers;

use App\Models\User;
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
        // Reserve 5MB of memory to ensure the Slack log webhook can fire during OOM errors
        $GLOBALS['reserved_memory_for_fatal_errors'] = \str_repeat('x', 1024 * 1024 * 5);
        \register_shutdown_function(function () {
            unset($GLOBALS['reserved_memory_for_fatal_errors']);
        });

        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::except([
            '*',
        ]);

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
