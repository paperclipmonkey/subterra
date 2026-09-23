<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Callout;
use App\Services\GcpWatchdogService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Retry a GCP watchdog cancel that failed inline.
 *
 * If a cancel is lost (a network blip, a Cloud Run cold start past the 10s
 * timeout), the watchdog raises a false EMERGENCY to every duty officer 15
 * minutes after callout_time. The watchdog's DELETE is idempotent, so retrying
 * is always safe.
 */
class CancelWatchdogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(
        public Callout $callout
    ) {
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [15, 30, 60, 120, 300];
    }

    public function handle(GcpWatchdogService $watchdog): void
    {
        if (!$watchdog->cancel($this->callout)) {
            throw new RuntimeException("GCP watchdog cancel failed for callout {$this->callout->id}; retrying.");
        }
    }

    public function failed(?\Throwable $e): void
    {
        Log::critical("Gave up cancelling the GCP watchdog for callout {$this->callout->id}. It will raise a FALSE backup alert to duty officers unless cancelled manually.", [
            'callout_id' => $this->callout->id,
            'error' => $e?->getMessage(),
        ]);
    }

    /**
     * Cancel now; if that fails, queue retries rather than silently dropping it.
     */
    public static function cancelOrRetry(Callout $callout, ?GcpWatchdogService $watchdog = null): void
    {
        try {
            if (($watchdog ?? app(GcpWatchdogService::class))->cancel($callout)) {
                return;
            }
        } catch (\Throwable $e) {
            Log::error("GCP watchdog cancel threw for callout {$callout->id}: {$e->getMessage()}");
        }

        self::dispatch($callout)->delay(now()->addSeconds(15));
    }
}
