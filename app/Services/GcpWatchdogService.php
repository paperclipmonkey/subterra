<?php

namespace App\Services;

use App\Models\Callout;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GcpWatchdogService
{
    private ?string $baseUrl;
    private ?string $apiKey;

    public function __construct()
    {
        $this->baseUrl = config('services.gcp_watchdog.url');
        $this->apiKey = config('services.gcp_watchdog.api_key');
    }

    /**
     * Register a callout with the GCP watchdog service.
     *
     * @param Callout $callout
     * @return string|null Watchdog ID on success, null on failure
     */
    public function register(Callout $callout): ?string
    {
        // Skip if watchdog is not configured (e.g., in tests)
        if (!$this->baseUrl) {
            return null;
        }

        try {
            $payload = $this->buildPayload($callout);

            $response = Http::timeout(10)
                ->post("{$this->baseUrl}/watchdog", $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("GCP Watchdog registered: {$callout->id}", $data);
                return $callout->id; // Use callout ID as watchdog ID
            }

            Log::error("Failed to register GCP watchdog: {$response->status()}", [
                'callout_id' => $callout->id,
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Exception registering GCP watchdog: {$e->getMessage()}", [
                'callout_id' => $callout->id,
                'exception' => $e
            ]);

            return null;
        }
    }

    /**
     * Cancel a callout watchdog.
     *
     * @param Callout $callout
     * @return bool
     */
    public function cancel(Callout $callout): bool
    {
        // Skip if watchdog is not configured (e.g., in tests)
        if (!$this->baseUrl) {
            return true;
        }

        try {
            $watchdogId = $callout->id;

            $response = Http::timeout(10)
                ->delete("{$this->baseUrl}/watchdog", [
                    'callout_id' => $watchdogId
                ]);

            if ($response->successful()) {
                Log::info("GCP Watchdog cancelled: {$watchdogId}");
                return true;
            }

            Log::error("Failed to cancel GCP watchdog: {$response->status()}", [
                'watchdog_id' => $watchdogId,
                'response' => $response->body()
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("Exception cancelling GCP watchdog: {$e->getMessage()}", [
                'callout_id' => $callout->id,
                'exception' => $e
            ]);

            return false;
        }
    }

    /**
     * Build payload for watchdog registration.
     *
     * @param Callout $callout
     * @return array
     */
    private function buildPayload(Callout $callout): array
    {
        $callout->load(['user', 'participants', 'cave', 'exitCave']);

        return [
            'callout_id' => $callout->id,
            'callout_time' => $callout->callout_time->toIso8601String(),
            'user' => [
                'name' => $callout->user->name,
                'phone' => $callout->user->phone,
                'email' => $callout->user->email,
            ],
            'participants' => $callout->participants->map(fn($p) => [
                'name' => $p->name,
                'phone' => $p->phone,
                'email' => $p->email,
            ])->toArray(),
            'trip_plan' => $callout->trip_plan ?? $callout->description ?? '',
            'cave_name' => $callout->cave->name ?? 'Unknown',
        ];
    }
}
