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
        private readonly \App\Services\RiverLevelService $riverLevelService,
        private readonly \App\Services\RainfallService $rainfallService
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

        // Fetch River Levels and Rain Gauges
        $riverLevels = [];
        $rainGauges = [];
        $cave->load('system.catchment');
        
        if ($cave->system && $cave->system->catchment && !empty($cave->system->catchment->gauges)) {
            foreach ($cave->system->catchment->gauges as $gauge) {
                // River Gauges
                if (empty($gauge['type']) || $gauge['type'] === 'river') {
                    if (!empty($gauge['rloi_id'])) {
                        $enhancedData = $this->riverLevelService->getEnhancedReading($gauge['rloi_id']);
                        if ($enhancedData) {
                            $riverLevels[] = [
                                'name' => $gauge['name'],
                                'rloi_id' => $gauge['rloi_id'],
                                'type' => 'river',
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
                // Rain Gauges
                elseif ($gauge['type'] === 'rain' && !empty($gauge['station_id'])) {
                    $readings = $this->rainfallService->getReadings($gauge['station_id']);
                    $metadata = $this->rainfallService->getStationMetadata($gauge['station_id']);
                    
                    if ($readings) {
                        $rainGauges[] = [
                            'name' => $gauge['name'],
                            'station_id' => $gauge['station_id'],
                            'type' => 'rain',
                            'readings' => $readings,
                            'metadata' => $metadata
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
                'rain_gauges' => $rainGauges,
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
