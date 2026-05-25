<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use League\Flysystem\Filesystem;
use League\Flysystem\GoogleCloudStorage\GoogleCloudStorageAdapter;

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
        if (\App::environment('production')) {
            \URL::forceScheme('https');
        }

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

        // Looks up by string ID while bypassing IsActiveScope
        // so that admin routes can operate on deactivated users too.
        Route::bind('user_without_scopes', function ($id) {
            return User::withoutGlobalScopes()->findOrFail($id);
        });

        // Register the GCS storage driver used for the transcoding staging bucket
        Storage::extend('gcs', function ($app, array $config) {
            $storageClient = new \Google\Cloud\Storage\StorageClient([
                'projectId' => $config['project_id'] ?? null,
                'keyFilePath' => !empty($config['key_file_path']) ? $config['key_file_path'] : null,
                'keyFile' => !empty($config['key_file']) ? $config['key_file'] : null,
            ]);

            $bucket = $storageClient->bucket($config['bucket']);
            $adapter = new GoogleCloudStorageAdapter($bucket);

            return new \Illuminate\Filesystem\FilesystemAdapter(
                new Filesystem($adapter),
                $adapter,
                $config
            );
        });
    }
}
