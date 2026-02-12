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
                $response = Http::timeout(10)->get($url, [
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

        // Loop for the last 7 days
        for ($i = 0; $i < 7; ++$i) {
            // For Time Machine requests, we need a specific timestamp.
            // We'll use noon of that day to get the daily summary effectively.
            $date = $now->copy()->subDays($i + 1)->setHour(12)->setMinute(0)->setSecond(0);
            $timestamp = $date->timestamp;
            $dateString = $date->format('Y-m-d');

            $cacheKey = "weather_historic_{$normalizedLatitude}_{$normalizedLongitude}_{$dateString}";

            $dayData = Cache::get($cacheKey);

            if (!$dayData) {
                try {
                    // Use the dedicated Time Machine endpoint for historic data to avoid "Time is in the Past" errors
                    $url = "https://timemachine.pirateweather.net/forecast/{$this->apiKey}/{$normalizedLatitude},{$normalizedLongitude},{$timestamp}";

                    // We primarily want hourly rain data for this day
                    $response = Http::timeout(10)->get($url, [
                        'units' => 'si',
                        'exclude' => 'minutely,currently,alerts,flags',
                    ]);

                    if ($response->successful()) {
                        $json = $response->json();
                        // Just extract what we need: daily summary and hourly precip
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

                        // Cache historic data for a long time since it won't change (1 year)
                        Cache::put($cacheKey, $dayData, 31536000);
                    } else {
                        Log::error('Pirate Weather Historic API error', [
                            'status' => $response->status(),
                            'body' => $response->body(),
                            'url' => $url,
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Weather service historic exception', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            if ($dayData) {
                // Key by date string for frontend
                $historicData[$dateString] = $dayData;
            }
        }

        return $historicData;
    }
}
