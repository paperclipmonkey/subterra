<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RainfallService
{
    private const BASE_URL = 'https://environment.data.gov.uk/flood-monitoring/id';
    private const CACHE_TTL = 900; // 15 minutes
    private const METADATA_CACHE_TTL = 86400; // 24h
    private const TIMEOUT = 10; // EA flood-monitoring API is slow for non-hot data

    /**
     * Fetch readings and station metadata concurrently, hitting only the
     * endpoints whose cached value is missing.
     *
     * @return array{readings: ?array, metadata: ?array}
     */
    public function getStationData(string $stationId): array
    {
        $readingsKey = "rainfall_readings_{$stationId}";
        $metadataKey = "rainfall_metadata_{$stationId}";

        $readings = Cache::get($readingsKey);
        $metadata = Cache::get($metadataKey);

        $needReadings = empty($readings);
        $needMetadata = $metadata === null;

        if (!$needReadings && !$needMetadata) {
            return ['readings' => $readings, 'metadata' => $metadata];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($stationId, $needReadings, $needMetadata) {
                $requests = [];
                if ($needReadings) {
                    $requests[] = $pool->as('readings')->timeout(self::TIMEOUT)
                        ->get(self::BASE_URL."/stations/{$stationId}/readings?_limit=100&_sorted");
                }
                if ($needMetadata) {
                    $requests[] = $pool->as('metadata')->timeout(self::TIMEOUT)
                        ->get(self::BASE_URL."/stations/{$stationId}");
                }

                return $requests;
            });
        } catch (Exception $e) {
            Log::error('Rainfall Service fetch exception', ['message' => $e->getMessage(), 'station' => $stationId]);

            return ['readings' => $readings, 'metadata' => $metadata];
        }

        if ($needReadings) {
            $fetched = $this->parseReadings($responses['readings'] ?? null, $stationId);
            // Don't cache a failed/empty fetch, so a transient timeout doesn't
            // blank the gauge for the whole cache window.
            if (!empty($fetched)) {
                Cache::put($readingsKey, $fetched, self::CACHE_TTL);
                $readings = $fetched;
            }
        }

        if ($needMetadata) {
            $fetched = $this->parseMetadata($responses['metadata'] ?? null, $stationId);
            if ($fetched !== null) {
                Cache::put($metadataKey, $fetched, self::METADATA_CACHE_TTL);
                $metadata = $fetched;
            }
        }

        return ['readings' => $readings, 'metadata' => $metadata];
    }

    /**
     * Get readings for a rainfall station.
     *
     * @param string $stationId The Station ID (e.g. 52201)
     */
    public function getReadings(string $stationId): ?array
    {
        return $this->getStationData($stationId)['readings'];
    }

    /**
     * A pooled request that times out comes back as a Throwable, not a Response.
     *
     * @param Response|\Throwable|null $response
     */
    private function parseReadings($response, string $stationId): ?array
    {
        if ($response instanceof Response) {
            if (!$response->successful()) {
                return null;
            }

            $items = $response->json()['items'] ?? [];

            return array_map(function ($item) {
                return [
                    'dateTime' => $item['dateTime'],
                    'value' => $item['value'],
                    'measure' => $item['measure'] ?? '',
                ];
            }, $items);
        }

        if ($response instanceof \Throwable) {
            Log::error('Rainfall Service readings exception', ['message' => $response->getMessage(), 'station' => $stationId]);
        }

        return null;
    }

    /**
     * @param Response|\Throwable|null $response
     */
    private function parseMetadata($response, string $stationId): ?array
    {
        if ($response instanceof Response) {
            if (!$response->successful()) {
                return null;
            }

            $items = $response->json()['items'] ?? [];

            return [
                'label' => $items['label'] ?? $stationId,
                'lat' => $items['lat'] ?? null,
                'long' => $items['long'] ?? null,
                'measures' => $items['measures'] ?? [],
            ];
        }

        if ($response instanceof \Throwable) {
            Log::error('Rainfall Service metadata exception', ['message' => $response->getMessage(), 'station' => $stationId]);
        }

        return null;
    }
}
