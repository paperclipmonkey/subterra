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
     * Get weather forecast for coordinates
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
                    'exclude' => 'minutely,alerts,flags'
                ]);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('Pirate Weather API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            } catch (\Exception $e) {
                Log::error('Weather service exception', [
                    'message' => $e->getMessage()
                ]);
                return null;
            }
        });
    }
}
