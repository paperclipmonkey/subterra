<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\CancelWatchdogJob;
use App\Models\Callout;
use App\Services\GcpWatchdogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * A lost watchdog cancel makes the backup raise a false emergency to every duty
 * officer, so a failed cancel must be retried rather than just logged.
 */
class WatchdogCancelRetryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_failed_cancel_is_queued_for_retry(): void
    {
        Queue::fake();
        $watchdog = Mockery::mock(GcpWatchdogService::class);
        $watchdog->shouldReceive('cancel')->once()->andReturn(false);

        $callout = Callout::factory()->create();
        CancelWatchdogJob::cancelOrRetry($callout, $watchdog);

        Queue::assertPushed(CancelWatchdogJob::class, fn ($job) => $job->callout->is($callout));
    }

    #[Test]
    public function a_successful_cancel_queues_nothing(): void
    {
        Queue::fake();
        $watchdog = Mockery::mock(GcpWatchdogService::class);
        $watchdog->shouldReceive('cancel')->once()->andReturn(true);

        CancelWatchdogJob::cancelOrRetry(Callout::factory()->create(), $watchdog);

        Queue::assertNothingPushed();
    }

    #[Test]
    public function the_retry_job_throws_so_the_queue_retries_it(): void
    {
        $watchdog = Mockery::mock(GcpWatchdogService::class);
        $watchdog->shouldReceive('cancel')->once()->andReturn(false);

        $this->expectException(RuntimeException::class);
        (new CancelWatchdogJob(Callout::factory()->create()))->handle($watchdog);
    }

    #[Test]
    public function cancelling_a_callout_retries_a_failed_watchdog_cancel(): void
    {
        Queue::fake();
        $watchdog = Mockery::mock(GcpWatchdogService::class);
        $watchdog->shouldReceive('cancel')->andReturn(false);
        $this->app->instance(GcpWatchdogService::class, $watchdog);

        $callout = Callout::factory()->create(['status' => 'active']);
        app(\App\Services\CalloutService::class)->cancel($callout);

        $this->assertSame('cancelled', $callout->fresh()->status);
        Queue::assertPushed(CancelWatchdogJob::class);
    }
}
