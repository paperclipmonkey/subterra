<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;

class SyncCaveRegistryJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 600;

    public $tries = 1;

    public function __construct(public readonly string $registry) {}

    public function handle(): void
    {
        Artisan::call('sync:'.$this->registry.'-caves');
    }
}
