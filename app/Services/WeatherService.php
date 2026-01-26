<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.pirateweather.net';

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

        $cacheKey = "weather_forecast_{$latitude}_{$longitude}";
        
        return Cache::remember($cacheKey, 1800, function () use ($latitude, $longitude) {
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

        $cacheKey = "weather_historical_{$latitude}_{$longitude}_{$timestamp}";
        
        return Cache::remember($cacheKey, 86400, function () use ($latitude, $longitude, $timestamp) {
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
     * Get weather for the last week
     */
    public function getLastWeekWeather(float $latitude, float $longitude): array
    {
        $weatherData = [];
        $now = time();
        
        for ($i = 7; $i >= 0; $i--) {
            $timestamp = $now - ($i * 86400);
            $data = $this->getHistoricalWeather($latitude, $longitude, $timestamp);
            
            if ($data && isset($data['daily']['data'][0])) {
                $weatherData[] = $data['daily']['data'][0];
            }
        }
        
        return $weatherData;
    }
}
