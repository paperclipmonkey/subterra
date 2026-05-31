<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Callout;
use App\Services\GcpWatchdogService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendTestWatchdogAlert extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watchdog:test-alert';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a monthly test alert through the GCP watchdog system to verify it\'s working';

    /**
     * Execute the console command.
     */
    public function handle(GcpWatchdogService $watchdogService): int
    {
        $this->info('Creating test watchdog callout...');

        // Create a test callout that will trigger in 1 minute
        $calloutTime = Carbon::now()->addMinute();

        // Create a minimal test callout record (not stored in DB)
        $testCalloutData = [
            'callout_id' => 'TEST-'.now()->format('Y-m-d-His'),
            'callout_time' => $calloutTime->toIso8601String(),
            'user' => [
                'name' => '🧪 Monthly Test Alert',
                'phone' => config('services.gcp_watchdog.test_phone'),
                'email' => config('services.gcp_watchdog.test_email'),
            ],
            'participants' => [],
            'trip_plan' => 'This is a MONTHLY TEST ALERT from the Subterra watchdog system. This message confirms the emergency alert system is functioning correctly.',
            'cave_name' => '🧪 Test System Check',
        ];

        try {
            // Register directly with GCP watchdog
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->withHeaders(['X-Watchdog-Key' => config('services.gcp_watchdog.api_key')])
                ->post(config('services.gcp_watchdog.url').'/watchdog', $testCalloutData);

            if ($response->successful()) {
                $this->info('✅ Test watchdog registered successfully!');
                $this->info("Callout ID: {$testCalloutData['callout_id']}");
                $this->info("Will trigger at: {$calloutTime->toDateTimeString()}");
                $this->info('Test alerts will be sent to: '.config('services.gcp_watchdog.test_email'));

                return Command::SUCCESS;
            } else {
                $this->error("❌ Failed to register test watchdog: {$response->status()}");
                $this->error($response->body());
                Log::error('SendTestWatchdogAlert failed: Watchdog API responded with error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error("❌ Exception: {$e->getMessage()}");
            Log::error('SendTestWatchdogAlert failed: Exception caught', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}
