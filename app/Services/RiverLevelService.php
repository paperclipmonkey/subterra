<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RiverLevelService
{
    private const BASE_URL = 'https://environment.data.gov.uk/flood-monitoring/id';
    private const CACHE_TTL = 900; // 15 minutes (API updates every 15 mins)
    private const METADATA_CACHE_TTL = 86400; // 24h - typical ranges rarely change
    private const TIMEOUT = 10; // EA flood-monitoring API is slow for non-hot data

    /**
     * Get enhanced reading for a catchment gauge.
     * Includes trend, state, and normal range.
     *
     * @param string $rloiId The RLOI ID (e.g. 3059)
     * @return array|null The latest reading and station details
     */
    public function getEnhancedReading(string $rloiId): ?array
    {
        $stationRef = $this->resolveStationReference($rloiId);

        if (!$stationRef) {
            return null;
        }

        ['readings' => $readings, 'metadata' => $metadata] = $this->fetchReadingsAndMetadata($stationRef);

        if (empty($readings)) {
            return null;
        }

        $latest = $readings[0];
        $previous = $readings[1] ?? null;

        // Calculate Trend
        $trend = 'Steady';
        if ($previous) {
            if ($latest['value'] > $previous['value']) {
                $trend = 'Rising';
            } elseif ($latest['value'] < $previous['value']) {
                $trend = 'Falling';
            }
        }

        // Determine State
        $state = 'Normal';
        $low = $metadata['typicalRangeLow'] ?? null;
        $high = $metadata['typicalRangeHigh'] ?? null;

        if ($low !== null && $high !== null) {
            if ($latest['value'] < $low) {
                $state = 'Low';
            } elseif ($latest['value'] > $high) {
                $state = 'High';
            }
        }

        return [
            'reading' => $readings,
            'latest_value' => $latest['value'],
            'latest_time' => $latest['dateTime'],
            'trend' => $trend,
            'state' => $state,
            'metadata' => $metadata,
        ];
    }

    /**
     * Fetch readings and station metadata, hitting only the endpoints whose
     * cached value is missing and firing those concurrently in a single pool.
     *
     * Failed or empty responses are deliberately NOT cached, so a transient API
     * timeout doesn't blank the gauge for the whole cache window.
     *
     * @return array{readings: array, metadata: array}
     */
    private function fetchReadingsAndMetadata(string $stationRef): array
    {
        $readingsKey = "river_level_readings_{$stationRef}";
        $metadataKey = "river_level_metadata_{$stationRef}";

        $readings = Cache::get($readingsKey);
        $metadata = Cache::get($metadataKey);

        $needReadings = empty($readings);
        $needMetadata = $metadata === null;

        if (!$needReadings && !$needMetadata) {
            return ['readings' => $readings, 'metadata' => $metadata];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($stationRef, $needReadings, $needMetadata) {
                $requests = [];
                if ($needReadings) {
                    $requests[] = $pool->as('readings')->timeout(self::TIMEOUT)
                        ->get(self::BASE_URL."/stations/{$stationRef}/readings?_limit=100&_sorted");
                }
                if ($needMetadata) {
                    $requests[] = $pool->as('metadata')->timeout(self::TIMEOUT)
                        ->get(self::BASE_URL."/stations/{$stationRef}/stageScale");
                }

                return $requests;
            });
        } catch (Exception $e) {
            Log::error('River Level Service fetch exception', ['message' => $e->getMessage()]);

            return ['readings' => $readings ?: [], 'metadata' => $metadata ?: []];
        }

        if ($needReadings) {
            $fetched = $this->parseReadings($responses['readings'] ?? null);
            if (!empty($fetched)) {
                Cache::put($readingsKey, $fetched, self::CACHE_TTL);
                $readings = $fetched;
            }
        }

        if ($needMetadata) {
            $fetched = $this->parseMetadata($responses['metadata'] ?? null);
            if ($fetched !== null) {
                Cache::put($metadataKey, $fetched, self::METADATA_CACHE_TTL);
                $metadata = $fetched;
            }
        }

        return ['readings' => $readings ?: [], 'metadata' => $metadata ?: []];
    }

    /**
     * A pooled request that times out comes back as a Throwable, not a Response.
     *
     * @param Response|\Throwable|null $response
     */
    private function parseReadings($response): ?array
    {
        if ($response instanceof Response) {
            return $response->successful() ? ($response->json()['items'] ?? []) : null;
        }

        if ($response instanceof \Throwable) {
            Log::error('River Level Service readings exception', ['message' => $response->getMessage()]);
        }

        return null;
    }

    /**
     * @param Response|\Throwable|null $response
     */
    private function parseMetadata($response): ?array
    {
        if ($response instanceof Response) {
            if (!$response->successful()) {
                return null;
            }

            $items = $response->json()['items'] ?? [];

            return [
                'typicalRangeLow' => $items['typicalRangeLow'] ?? null,
                'typicalRangeHigh' => $items['typicalRangeHigh'] ?? null,
                'minOnRecord' => $items['minOnRecord']['value'] ?? null,
                'maxOnRecord' => $items['maxOnRecord']['value'] ?? null,
            ];
        }

        if ($response instanceof \Throwable) {
            Log::error('River Level Service metadata exception', ['message' => $response->getMessage()]);
        }

        return null;
    }

    /**
     * Resolve RLOI ID to Station Reference.
     */
    private function resolveStationReference(string $rloiId): ?string
    {
        $cacheKey = "river_level_station_ref_{$rloiId}";

        $ref = Cache::get($cacheKey);
        if ($ref !== null) {
            return $ref;
        }

        try {
            $url = self::BASE_URL."/stations?RLOIid={$rloiId}";
            $response = Http::timeout(self::TIMEOUT)->get($url);

            if ($response->successful()) {
                $items = $response->json()['items'] ?? [];
                if (count($items) > 0 && !empty($items[0]['stationReference'])) {
                    $ref = $items[0]['stationReference'];
                    // Station references are stable, so this can be cached for a long time.
                    Cache::put($cacheKey, $ref, self::METADATA_CACHE_TTL);

                    return $ref;
                }
            }

            Log::warning("Could not resolve RLOI ID {$rloiId} to station reference");

            return null;
        } catch (Exception $e) {
            Log::error('River Level Service resolution exception', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
