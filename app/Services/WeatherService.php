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
    private const HISTORICAL_CACHE_TTL = 86400; // 24 hours
    private const SECONDS_PER_DAY = 86400;

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

    /**
     * Get historical weather data for coordinates
     */
    public function getHistoricalWeather(float $latitude, float $longitude, int $timestamp): ?array
    {
        if (empty($this->apiKey)) {
            Log::warning('Pirate Weather API key not configured');
            return null;
        }

        $normalizedLatitude = number_format(round($latitude, 2), 2, '.', '');
        $normalizedLongitude = number_format(round($longitude, 2), 2, '.', '');
        $cacheKey = "weather_historical_{$normalizedLatitude}_{$normalizedLongitude}_{$timestamp}";
        
        return Cache::remember($cacheKey, self::HISTORICAL_CACHE_TTL, function () use ($latitude, $longitude, $timestamp) {
            try {
                $url = "{$this->baseUrl}/forecast/{$this->apiKey}/{$latitude},{$longitude},{$timestamp}";
                $response = Http::timeout(10)->get($url, [
                    'units' => 'si',
                    'exclude' => 'currently,minutely,alerts,flags'
                ]);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('Pirate Weather API historical error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            } catch (\Exception $e) {
                Log::error('Weather service historical exception', [
                    'message' => $e->getMessage()
                ]);
                return null;
            }
        });
    }

    /**
     * Get weather for the last 7 days
     */
    public function getLastWeekWeather(float $latitude, float $longitude): array
    {
        $weatherData = [];
        $now = time();
        
        for ($i = 6; $i >= 0; $i--) {
            $timestamp = $now - ($i * self::SECONDS_PER_DAY);
            $data = $this->getHistoricalWeather($latitude, $longitude, $timestamp);
            
            if ($data && isset($data['daily']['data'][0])) {
                $weatherData[] = $data['daily']['data'][0];
            } else {
                // Add a placeholder entry for days where data is not available
                $weatherData[] = [
                    'time' => $timestamp,
                    'precipAccumulation' => 0,
                    'summary' => 'No data available',
                ];
            }
        }
        
        return $weatherData;
    }
}
