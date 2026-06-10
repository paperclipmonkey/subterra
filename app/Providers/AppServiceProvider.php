<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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

        // Communications providers (Fly/primary side). Bound to interfaces so the vendor
        // is swappable and easily mocked in tests. The GCP backup uses TextMagic separately.
        $this->app->bind(\App\Contracts\SmsSender::class, \App\Services\Twilio\TwilioSmsService::class);
        $this->app->bind(\App\Contracts\VoiceCaller::class, \App\Services\Twilio\TwilioVoiceService::class);
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

        $this->configureRateLimiters();

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

    /**
     * Register a dedicated, named rate limiter for every throttled route.
     *
     * Laravel's inline `throttle:N,M` middleware keys its counter ONLY by the
     * request signature — sha1(user id) for authenticated requests, or
     * sha1(domain|ip) for guests — and that same key is shared across EVERY
     * inline-throttled route regardless of each route's limit. The practical
     * effect is that unrelated routes share one counter: e.g. a user's AI
     * assistant calls (a 24h window) could exhaust the budget and block an
     * unrelated action, with a confusing multi-hour Retry-After.
     *
     * Named limiters avoid this: Laravel namespaces their cache key by the
     * limiter name (md5(name . key)), so each route below gets its own isolated
     * budget. We also make the keying explicit — authenticated routes are keyed
     * per user (falling back to IP), webhooks/guest routes per client IP.
     */
    private function configureRateLimiters(): void
    {
        // Per-user limiters (these routes always run behind auth middleware, so
        // a user is present; the IP fallback is purely defensive).
        $perUser = fn (int $max, int $decayMinutes) => fn (Request $request) => Limit::perMinutes($decayMinutes, $max)
            ->by($request->user()?->id ? 'user:'.$request->user()->id : 'ip:'.$request->ip());

        RateLimiter::for('user-create', $perUser(10, 1));
        RateLimiter::for('assistant-chat', $perUser(50, 1440));      // 50 per day
        RateLimiter::for('assistant-feedback', $perUser(60, 60));    // 60 per hour
        RateLimiter::for('assistant-logbook-import', $perUser(20, 1440)); // 20 per day
        RateLimiter::for('duty-officer-test-self', $perUser(10, 1));
        RateLimiter::for('duty-officer-test-broadcast', $perUser(3, 5)); // 3 per 5 min

        // Per-IP limiters for guest/webhook endpoints.
        $perIp = fn (int $max, int $decayMinutes) => fn (Request $request) => Limit::perMinutes($decayMinutes, $max)
            ->by('ip:'.$request->ip());

        RateLimiter::for('magic-link', $perIp(5, 1));
        RateLimiter::for('webhook-twilio-sms', $perIp(60, 1));
        RateLimiter::for('webhook-twilio-voice', $perIp(120, 1));
        RateLimiter::for('webhook-gcp-media', $perIp(120, 1));
    }
}
