<?php

declare(strict_types=1);

namespace Tests;

use App\Jobs\ProcessImageCloudJob;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Bus;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The cloud image pipeline talks to GCS/Cloud Run, which isn't available
        // in tests. Fake just this job so controllers can dispatch it normally
        // (and dispatch assertions still work) without the sync queue actually
        // executing it. Tests that exercise the job itself call handle()
        // directly, which a Bus fake does not intercept.
        Bus::fake([ProcessImageCloudJob::class]);
    }
}
