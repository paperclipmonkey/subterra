<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\Cave;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\RainfallService;
use App\Services\RiverLevelService;
use App\Services\WeatherService;
use Illuminate\Support\Facades\Log;

class GetWeatherForecastTool implements AssistantTool
{
    public function __construct(
        private readonly WeatherService $weatherService,
        private readonly RiverLevelService $riverLevelService,
        private readonly RainfallService $rainfallService,
    ) {
    }

    public static function definition(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => 'get_weather_forecast',
                'description' => 'Get the weather forecast and live river/rain gauge readings for a cave. ALWAYS call this before recommending any streamway, rising phreatic, or sump-containing cave. High river levels or recent heavy rainfall indicate serious flood risk.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'cave_id' => [
                            'type'        => 'integer',
                            'description' => 'The numeric ID of the cave entrance to check (returned in the "entrances" array from get_cave_details).',
                        ],
                    ],
                    'required' => ['cave_id'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $caveId = (int) ($arguments['cave_id'] ?? 0);
        $cave = Cave::with('system.catchment')->find($caveId);

        if (!$cave) {
            return ['error' => "Cave with ID {$caveId} not found."];
        }

        if (!$cave->location_lat || !$cave->location_lng) {
            return [
                'cave_name' => $cave->name,
                'error'     => 'This cave does not have location coordinates. Weather data is unavailable.',
            ];
        }

        $forecast = $this->weatherService->getForecast($cave->location_lat, $cave->location_lng);

        $dailyForecast = [];
        if ($forecast && isset($forecast['daily']['data'])) {
            foreach (array_slice($forecast['daily']['data'], 0, 7) as $day) {
                $dailyForecast[] = [
                    'date'         => date('Y-m-d', $day['time']),
                    'summary'      => $day['summary'] ?? null,
                    'precip_mm'    => isset($day['precipIntensity']) ? round((float) $day['precipIntensity'] * 24, 1) : null,
                    'precip_prob'  => isset($day['precipProbability']) ? round((float) $day['precipProbability'] * 100) : null,
                    'temp_max_c'   => isset($day['temperatureHigh']) ? round((float) $day['temperatureHigh'], 1) : null,
                ];
            }
        }

        $historicRain = $this->weatherService->getHistoricRain($cave->location_lat, $cave->location_lng);
        $antecedentMm = null;
        if ($historicRain) {
            $totalMm = 0.0;
            foreach ($historicRain as $dayData) {
                // Sum hourly precipIntensity (mm/hr × 1 hr per reading = mm)
                if (!empty($dayData['hourly'])) {
                    foreach ($dayData['hourly'] as $hour) {
                        $totalMm += (float) ($hour['precipIntensity'] ?? 0);
                    }
                } elseif (!empty($dayData['day_stats']['precipIntensity'])) {
                    // Fallback: daily average intensity × 24 hours
                    $totalMm += (float) $dayData['day_stats']['precipIntensity'] * 24;
                }
            }
            $antecedentMm = round($totalMm, 1);
        }

        $riverGauges = [];
        $rainGauges = [];

        if ($cave->system && $cave->system->catchment && !empty($cave->system->catchment->gauges)) {
            foreach ($cave->system->catchment->gauges as $gauge) {
                $type = empty($gauge['type']) ? 'river' : $gauge['type'];

                if ($type === 'river' && !empty($gauge['rloi_id'])) {
                    try {
                        $reading = $this->riverLevelService->getEnhancedReading((string) $gauge['rloi_id']);
                        if ($reading) {
                            $riverGauges[] = [
                                'name'          => $gauge['name'] ?? 'River gauge',
                                'state'         => $reading['state'],
                                'trend'         => $reading['trend'],
                                'latest_value'  => $reading['latest_value'],
                                'latest_time'   => $reading['latest_time'],
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Assistant: river gauge fetch failed', ['gauge' => $gauge, 'error' => $e->getMessage()]);
                    }
                }

                if ($type === 'rain' && !empty($gauge['station_id'])) {
                    try {
                        $readings = $this->rainfallService->getReadings((string) $gauge['station_id']);
                        if ($readings) {
                            $total24h = array_sum(array_column(array_slice($readings, 0, 96), 'value'));
                            $rainGauges[] = [
                                'name'           => $gauge['name'] ?? 'Rain gauge',
                                'readings_24h_mm' => round((float) $total24h, 1),
                            ];
                        }
                    } catch (\Throwable $e) {
                        Log::warning('Assistant: rain gauge fetch failed', ['gauge' => $gauge, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        return [
            'cave_name'              => $cave->name,
            'cave_system'            => $cave->system?->name,
            'location'               => ['lat' => $cave->location_lat, 'lng' => $cave->location_lng],
            'forecast_available'     => !empty($dailyForecast),
            'daily_forecast'         => $dailyForecast,
            'antecedent_rain_7d_mm'  => $antecedentMm,
            'river_gauges'           => $riverGauges,
            'rain_gauges'            => $rainGauges,
        ];
    }
}
