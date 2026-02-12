<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RainfallService
{
    private const BASE_URL = 'https://environment.data.gov.uk/flood-monitoring/id';
    private const CACHE_TTL = 900; // 15 minutes

    /**
     * Get readings for a rainfall station.
     *
     * @param string $stationId The Station ID (e.g. 52201)
     * @return array|null
     */
    public function getReadings(string $stationId): ?array
    {
        // For rainfall, the stationId is often the same as the stationReference in the API URL
        // Example: https://environment.data.gov.uk/flood-monitoring/id/stations/52201/readings?_limit=100&_sorted

        $cacheKey = "rainfall_readings_{$stationId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($stationId) {
            try {
                // We want rainfall data. The parameter/measure is usually 'rainfall' or similar.
                // However, fetching /readings for the station usually returns the available measures.
                // Let's filter for rainfall related parameters if possible or just get all readings.
                // It's safer to get all readings and filter if needed, but usually a "Rainfall Station" only has rain.

                $url = self::BASE_URL."/stations/{$stationId}/readings?_limit=100&_sorted";
                $response = Http::timeout(5)->get($url);

                if ($response->successful()) {
                    $items = $response->json()['items'] ?? [];

                    // Format readings to be consistent
                    return array_map(function ($item) {
                        return [
                            'dateTime' => $item['dateTime'],
                            'value' => $item['value'],
                            'measure' => $item['measure'] ?? '',
                        ];
                    }, $items);
                }

                return;
            } catch (Exception $e) {
                Log::error('Rainfall Service readings exception', ['message' => $e->getMessage(), 'station' => $stationId]);

                return;
            }
        });
    }

    /**
     * Get station metadata.
     *
     * @param string $stationId
     * @return array|null
     */
    public function getStationMetadata(string $stationId): ?array
    {
        $cacheKey = "rainfall_metadata_{$stationId}";

        return Cache::remember($cacheKey, 86400, function () use ($stationId) { // 24h cache
            try {
                $url = self::BASE_URL."/stations/{$stationId}";
                $response = Http::timeout(5)->get($url);

                if ($response->successful()) {
                    $json = $response->json();
                    $items = $json['items'] ?? [];

                    return [
                        'label' => $items['label'] ?? $stationId,
                        'lat' => $items['lat'] ?? null,
                        'long' => $items['long'] ?? null,
                        'measures' => $items['measures'] ?? [],
                    ];
                }

                return;
            } catch (\Exception $e) {
                Log::error('Rainfall Service metadata exception', ['message' => $e->getMessage()]);

                return;
            }
        });
    }
}
