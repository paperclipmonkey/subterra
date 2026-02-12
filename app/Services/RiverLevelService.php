<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RiverLevelService
{
    private const BASE_URL = 'https://environment.data.gov.uk/flood-monitoring/id';
    private const CACHE_TTL = 900; // 15 minutes (API updates every 15 mins)

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

        // Parallel fetch or separate caches?
        // Let's keep it simple: get readings, get metadata
        $readings = $this->getReadings($stationRef);
        $metadata = $this->getStationMetadata($stationRef);

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
     * Get readings for a station reference.
     */
    public function getReadings(string $stationRef): array
    {
        $cacheKey = "river_level_readings_{$stationRef}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($stationRef) {
            try {
                $url = self::BASE_URL."/stations/{$stationRef}/readings?_limit=100&_sorted";
                $response = Http::timeout(5)->get($url);

                if ($response->successful()) {
                    return $response->json()['items'] ?? [];
                }

                return [];
            } catch (Exception $e) {
                Log::error('River Level Service readings exception', ['message' => $e->getMessage()]);

                return [];
            }
        });
    }

    /**
     * Get station metadata (stageScale).
     * Cached forever (or long time) as typical ranges rarely change.
     */
    private function getStationMetadata(string $stationRef): array
    {
        $cacheKey = "river_level_metadata_{$stationRef}";

        return Cache::remember($cacheKey, 86400, function () use ($stationRef) { // 24h cache
            try {
                $url = self::BASE_URL."/stations/{$stationRef}/stageScale";
                $response = Http::timeout(5)->get($url);

                if ($response->successful()) {
                    $json = $response->json();
                    $items = $json['items'] ?? [];

                    return [
                        'typicalRangeLow' => $items['typicalRangeLow'] ?? null,
                        'typicalRangeHigh' => $items['typicalRangeHigh'] ?? null,
                        'minOnRecord' => $items['minOnRecord']['value'] ?? null,
                        'maxOnRecord' => $items['maxOnRecord']['value'] ?? null,
                    ];
                }

                return [];
            } catch (Exception $e) {
                Log::error('River Level Service metadata exception', ['message' => $e->getMessage()]);

                return [];
            }
        });
    }

    /**
     * Resolve RLOI ID to Station Reference.
     */
    private function resolveStationReference(string $rloiId): ?string
    {
        $cacheKey = "river_level_station_ref_{$rloiId}";

        return Cache::rememberForever($cacheKey, function () use ($rloiId) {
            try {
                $url = self::BASE_URL."/stations?RLOIid={$rloiId}";
                $response = Http::timeout(5)->get($url);

                if ($response->successful()) {
                    $json = $response->json();
                    $items = $json['items'] ?? [];
                    if (count($items) > 0) {
                        return $items[0]['stationReference'] ?? null;
                    }
                }
                Log::warning("Could not resolve RLOI ID {$rloiId} to station reference");

                return;
            } catch (\Exception $e) {
                Log::error('River Level Service resolution exception', ['message' => $e->getMessage()]);

                return;
            }
        });
    }
}
