<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cave;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class CaveWeatherController extends Controller
{
    public function __construct(
        private readonly WeatherService $weatherService
    ) {}

    /**
     * Get current weather forecast for a cave
     */
    public function forecast(Cave $cave): JsonResponse
    {
        if (!$cave->location_lat || !$cave->location_lng) {
            return response()->json([
                'error' => 'Cave location coordinates not available'
            ], 404);
        }

        $forecast = $this->weatherService->getForecast(
            $cave->location_lat,
            $cave->location_lng
        );

        if (!$forecast) {
            return response()->json([
                'error' => 'Unable to fetch weather data'
            ], 503);
        }

        return response()->json([
            'data' => [
                'currently' => $forecast['currently'] ?? null,
                'hourly' => $forecast['hourly'] ?? null,
                'daily' => $forecast['daily'] ?? null,
            ]
        ]);
    }

    /**
     * Get historical weather for a cave (last 7 days)
     */
    public function historical(Cave $cave): JsonResponse
    {
        if (!$cave->location_lat || !$cave->location_lng) {
            return response()->json([
                'error' => 'Cave location coordinates not available'
            ], 404);
        }

        $historicalData = $this->weatherService->getLastWeekWeather(
            $cave->location_lat,
            $cave->location_lng
        );

        if (empty($historicalData)) {
            return response()->json([
                'error' => 'Unable to fetch historical weather data'
            ], 503);
        }
        return response()->json([
            'data' => $historicalData
        ]);
    }
}
