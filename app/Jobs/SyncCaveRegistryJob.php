<?php

declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;

class SyncCaveRegistryJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public $tries = 1;

    public function __construct(public readonly string $registry)
    {
    }

    /**
     * Prevent two syncs of the same registry from running concurrently.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('sync-cave-registry:'.$this->registry))->expireAfter($this->timeout),
        ];
    }

    public function handle(): void
    {
        $exitCode = Artisan::call('sync:'.$this->registry.'-caves');

        if ($exitCode !== 0) {
            throw new \RuntimeException("Cave registry sync '{$this->registry}' failed with exit code {$exitCode}.");
        }
    }
}
