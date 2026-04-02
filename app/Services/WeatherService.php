<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private ?string $apiKey;
    private string $baseUrl = 'https://api.pirateweather.net';
    private const FORECAST_CACHE_TTL = 1800; // 30 minutes

    public function __construct()
    {
        $this->apiKey = config('services.pirate_weather.api_key');
    }

    /**
     * Get weather forecast for coordinates.
     */
    public function getForecast(float $latitude, float $longitude): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('Pirate Weather API key not configured');

            return null;
        }

        $normalizedLatitude = number_format(round($latitude, 2), 2, '.', '');
        $normalizedLongitude = number_format(round($longitude, 2), 2, '.', '');
        $cacheKey = "weather_forecast_{$normalizedLatitude}_{$normalizedLongitude}";

        return Cache::remember($cacheKey, self::FORECAST_CACHE_TTL, function () use ($latitude, $longitude) {
            try {
                $url = "{$this->baseUrl}/forecast/{$this->apiKey}/{$latitude},{$longitude}";
                $response = Http::timeout(30)->get($url, [
                    'units' => 'si',
                    'exclude' => 'minutely,alerts,flags',
                ]);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('Pirate Weather API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return;
            } catch (\Exception $e) {
                Log::error('Weather service exception', [
                    'message' => $e->getMessage(),
                ]);

                return;
            }
        });
    }

    /**
     * Get historic rain data for the last 7 days from Pirate Weather
     * Uses Time Machine requests.
     */
    public function getHistoricRain(float $latitude, float $longitude): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('Pirate Weather API key not configured');

            return null;
        }

        $normalizedLatitude = number_format(round($latitude, 2), 2, '.', '');
        $normalizedLongitude = number_format(round($longitude, 2), 2, '.', '');

        $historicData = [];
        $now = now();
        $requests = [];
        $datesToFetch = [];

        // Identify which days are not in cache
        for ($i = 0; $i < 7; ++$i) {
            $date = $now->copy()->subDays($i + 1)->setHour(12)->setMinute(0)->setSecond(0);
            $dateString = $date->format('Y-m-d');
            $cacheKey = "weather_historic_{$normalizedLatitude}_{$normalizedLongitude}_{$dateString}";

            $dayData = Cache::get($cacheKey);
            if ($dayData) {
                $historicData[$dateString] = $dayData;
            } else {
                $datesToFetch[$dateString] = [
                    'url' => "https://timemachine.pirateweather.net/forecast/{$this->apiKey}/{$normalizedLatitude},{$normalizedLongitude},{$date->timestamp}",
                    'cacheKey' => $cacheKey,
                ];
            }
        }

        // Fetch missing days in parallel
        if (!empty($datesToFetch)) {
            try {
                $responses = Http::pool(fn ($pool) => array_map(
                    fn ($fetchInfo) => $pool->timeout(30)->get($fetchInfo['url'], [
                        'units' => 'si',
                        'exclude' => 'minutely,currently,alerts,flags',
                    ]),
                    $datesToFetch
                ));

                foreach ($datesToFetch as $dateString => $fetchInfo) {
                    $response = $responses[array_search($dateString, array_keys($datesToFetch))];

                    if ($response->successful()) {
                        $json = $response->json();
                        $dayData = [
                            'day_stats' => $json['daily']['data'][0] ?? null,
                            'hourly' => array_map(function ($hour) {
                                return [
                                   'time' => $hour['time'],
                                   'precipIntensity' => $hour['precipIntensity'] ?? 0,
                                   'precipProbability' => $hour['precipProbability'] ?? 0,
                                ];
                            }, $json['hourly']['data'] ?? []),
                        ];

                        Cache::put($fetchInfo['cacheKey'], $dayData, 31536000); // 1 year
                        $historicData[$dateString] = $dayData;
                    } else {
                        Log::error('Pirate Weather Historic API error', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                            'url' => $fetchInfo['url'],
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Weather service historic exception', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Sort by date descending (to match existing behavior of subDays order)
        krsort($historicData);

        return $historicData;
    }
}
