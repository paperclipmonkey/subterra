<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cave;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;

class CaveWeatherController extends Controller
{
    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly \App\Services\RiverLevelService $riverLevelService
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

        // Fetch River Levels if applicable
        $riverLevels = [];
        $cave->load('system.catchment');
        
        if ($cave->system && $cave->system->catchment && !empty($cave->system->catchment->gauges)) {
            foreach ($cave->system->catchment->gauges as $gauge) {
                if (!empty($gauge['rloi_id'])) {
                    $enhancedData = $this->riverLevelService->getEnhancedReading($gauge['rloi_id']);
                    if ($enhancedData) {
                        $riverLevels[] = [
                            'name' => $gauge['name'],
                            'rloi_id' => $gauge['rloi_id'],
                            'reading' => $enhancedData['reading'],
                            'latest_value' => $enhancedData['latest_value'],
                            'latest_time' => $enhancedData['latest_time'],
                            'trend' => $enhancedData['trend'],
                            'state' => $enhancedData['state'],
                            'metadata' => $enhancedData['metadata']
                        ];
                    }
                }
            }
        }

        return response()->json([
            'data' => [
                'cave_name' => $cave->name,
                'latitude' => $cave->location_lat,
                'longitude' => $cave->location_lng,
                'currently' => $forecast['currently'] ?? null,
                'hourly' => $forecast['hourly'] ?? null,
                'daily' => $forecast['daily'] ?? null,
                'river_levels' => $riverLevels,
            ]
        ]);
    }



    /**
     * Get historic rain data for a cave (last 7 days)
     */
    public function historic(Cave $cave): JsonResponse
    {
        if (!$cave->location_lat || !$cave->location_lng) {
            return response()->json([
                'error' => 'Cave location coordinates not available'
            ], 404);
        }

        $historicData = $this->weatherService->getHistoricRain(
            $cave->location_lat,
            $cave->location_lng
        );

        if (!$historicData) {
            return response()->json([
                'error' => 'Unable to fetch historic weather data'
            ], 503);
        }

        return response()->json([
            'data' => $historicData
        ]);
    }

}
