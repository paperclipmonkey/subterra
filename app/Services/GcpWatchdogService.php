<?php

namespace App\Services;

use App\Models\Callout;
use Exception;
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
                ->withHeaders(['X-Watchdog-Key' => $this->apiKey])
                ->post("{$this->baseUrl}/watchdog", $payload);

            if ($response->successful()) {
                $data = $response->json();
                Log::info("GCP Watchdog registered: {$callout->id}", $data);

                return $callout->id; // Use callout ID as watchdog ID
            }

            Log::error("Failed to register GCP watchdog: {$response->status()}", [
                'callout_id' => $callout->id,
                'response' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error("Exception registering GCP watchdog: {$e->getMessage()}", [
                'callout_id' => $callout->id,
                'exception' => $e,
            ]);

            return null;
        }
    }

    public function cancel(Callout $callout): bool
    {
        // Skip if watchdog is not configured (e.g., in tests)
        if (!$this->baseUrl) {
            return true;
        }

        try {
            $watchdogId = $callout->id;

            $response = Http::timeout(10)
                ->withHeaders(['X-Watchdog-Key' => $this->apiKey])
                ->withQueryParameters([
                    'callout_id' => $watchdogId,
                ])
                ->delete("{$this->baseUrl}/watchdog");

            if ($response->successful()) {
                Log::info("GCP Watchdog cancelled: {$watchdogId}");

                return true;
            }

            Log::error("Failed to cancel GCP watchdog: {$response->status()}", [
                'watchdog_id' => $watchdogId,
                'response' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error("Exception cancelling GCP watchdog: {$e->getMessage()}", [
                'callout_id' => $callout->id,
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Get the count of active watchdogs from the watchdog service.
     */
    public function getActiveWatchdogCount(): int
    {
        if (!$this->baseUrl) {
            return -2; // Not configured
        }

        try {
            $response = Http::timeout(5)
                ->withHeaders(['X-Watchdog-Key' => $this->apiKey])
                ->get("{$this->baseUrl}/watchdog");

            if ($response->successful()) {
                return $response->json('count', 0);
            }
        } catch (\Exception $e) {
            Log::error("Failed to fetch watchdog count: {$e->getMessage()}");
        }

        return -1; // Indicate connection error
    }

    /**
     * Send a test callout request to the watchdog service.
     */
    public function sendTestCallout(array $data): bool
    {
        if (!$this->baseUrl) {
            return false;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Watchdog-Key' => $this->apiKey])
                ->post("{$this->baseUrl}/watchdog/test", $data);

            return $response->successful();
        } catch (Exception $e) {
            Log::error("Failed to send test watchdog callout: {$e->getMessage()}");

            return false;
        }
    }

    /**
     * Build payload for watchdog registration.

    /**
     * Build payload for watchdog registration.
     *
     * @param Callout $callout
     * @return array
     */
    private function buildPayload(Callout $callout): array
    {
        $callout->load(['user', 'participants', 'cave', 'exitCave']);
        
        $dutyOfficers = \App\Models\User::whereHas('roles', function ($query) {
            $query->where('slug', 'duty_officer');
        })->get()->map(fn ($do) => [
            'name' => $do->name,
            'phone' => $do->phone,
            'email' => $do->email,
        ])->toArray();

        return [
            'callout_id' => $callout->id,
            'callout_time' => $callout->callout_time->toIso8601String(),
            'user' => [
                'name' => $callout->user->name,
                'phone' => $callout->user->phone,
                'email' => $callout->user->email,
            ],
            'duty_officers' => $dutyOfficers,
            'participants' => $callout->participants->map(fn ($p) => [
                'name' => $p->name,
                'phone' => $p->phone,
                'email' => $p->email,
            ])->toArray(),
            'trip_plan' => $callout->trip_plan ?? $callout->description ?? '',
            'cave_name' => $callout->cave->name ?? 'Unknown',
            'car_registration' => $callout->car_registration,
            'car_parking' => $callout->car_parking,
            'team_details' => $callout->team_details,
            'location_data' => $callout->location_data,
        ];
    }
}
