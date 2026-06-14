<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CaveWeatherTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_weather_forecast_for_cave_with_coordinates()
    {
        $this->actingAs(User::factory()->create());

        // Set the API key for the test
        config(['services.pirate_weather.api_key' => 'test-key']);

        $cave = Cave::factory()->create([
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        // Mock the Pirate Weather API response
        Http::fake([
            'api.pirateweather.net/*' => Http::response([
                'currently' => [
                    'temperature' => 15.5,
                    'summary' => 'Partly Cloudy',
                ],
                'hourly' => [
                    'data' => [
                        ['time' => 1234567890, 'temperature' => 15.0],
                    ],
                ],
                'daily' => [
                    'data' => [
                        ['time' => 1234567890, 'temperatureHigh' => 18.0, 'temperatureLow' => 12.0],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'currently',
                'hourly',
                'daily',
            ],
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_weather_api_failure_gracefully()
    {
        $this->actingAs(User::factory()->create());

        // Set the API key for the test
        config(['services.pirate_weather.api_key' => 'test-key']);

        $cave = Cave::factory()->create([
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        // Mock a failed API response
        Http::fake([
            'api.pirateweather.net/*' => Http::response(null, 500),
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");

        $response->assertStatus(503);
        $response->assertJson([
            'error' => 'Unable to fetch weather data',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_requires_authentication_to_access_weather()
    {
        $cave = Cave::factory()->create([
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_rain_gauge_data_for_catchment_with_rain_gauge()
    {
        $this->actingAs(User::factory()->create());
        config(['services.pirate_weather.api_key' => 'test-key']);

        $catchment = \App\Models\Catchment::factory()->create([
            'gauges' => [
                [
                    'name' => 'Test Rain Gauge',
                    'station_id' => '12345',
                    'type' => 'rain',
                ],
            ],
        ]);

        $system = \App\Models\CaveSystem::factory()->create([
            'catchment_id' => $catchment->id,
        ]);

        $cave = Cave::factory()->create([
            'cave_system_id' => $system->id,
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        // Mock Pirate Weather
        Http::fake([
            'api.pirateweather.net/*' => Http::response([
                'currently' => ['temperature' => 15.5],
            ], 200),
            // Mock Rain Gauge API
            'environment.data.gov.uk/flood-monitoring/id/stations/12345/readings*' => Http::response([
                 'items' => [
                     ['dateTime' => '2023-10-27T10:00:00Z', 'value' => 0.2, 'measure' => 'rainfall'],
                 ],
            ], 200),
            'environment.data.gov.uk/flood-monitoring/id/stations/12345' => Http::response([
                'items' => [
                    'label' => 'Test Rain Gauge',
                    'lat' => 51.0,
                    'long' => -2.0,
                ],
            ], 200),
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'rain_gauges' => [
                    '*' => [
                        'name',
                        'station_id',
                        'type',
                        'readings',
                        'metadata',
                    ],
                ],
            ],
        ]);

        $this->assertEquals('Test Rain Gauge', $response->json('data.rain_gauges.0.name'));
        $this->assertEquals('12345', $response->json('data.rain_gauges.0.station_id'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_river_level_data_for_catchment_with_river_gauge()
    {
        $this->actingAs(User::factory()->create());
        config(['services.pirate_weather.api_key' => 'test-key']);

        $catchment = \App\Models\Catchment::factory()->create([
            'gauges' => [
                [
                    'name' => 'Wookey',
                    'rloi_id' => '3059',
                    'type' => 'river',
                ],
            ],
        ]);

        $system = \App\Models\CaveSystem::factory()->create([
            'catchment_id' => $catchment->id,
        ]);

        $cave = Cave::factory()->create([
            'cave_system_id' => $system->id,
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        Http::fake([
            'api.pirateweather.net/*' => Http::response(['currently' => ['temperature' => 15.5]], 200),
            // RLOI 3059 resolves to station reference 52205.
            'environment.data.gov.uk/flood-monitoring/id/stations?RLOIid=3059' => Http::response([
                'items' => [['stationReference' => '52205']],
            ], 200),
            'environment.data.gov.uk/flood-monitoring/id/stations/52205/readings*' => Http::response([
                'items' => [
                    ['dateTime' => '2026-06-14T10:00:00Z', 'value' => 0.55],
                    ['dateTime' => '2026-06-14T09:45:00Z', 'value' => 0.50],
                ],
            ], 200),
            'environment.data.gov.uk/flood-monitoring/id/stations/52205/stageScale' => Http::response([
                'items' => ['typicalRangeLow' => 0.1, 'typicalRangeHigh' => 0.9],
            ], 200),
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.river_levels'));
        $this->assertEquals('Wookey', $response->json('data.river_levels.0.name'));
        $this->assertEquals('3059', $response->json('data.river_levels.0.rloi_id'));
        $this->assertEquals(0.55, $response->json('data.river_levels.0.latest_value'));
        $this->assertEquals('Rising', $response->json('data.river_levels.0.trend'));
        $this->assertEquals('Normal', $response->json('data.river_levels.0.state'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_does_not_cache_a_failed_river_readings_fetch()
    {
        $this->actingAs(User::factory()->create());
        config(['services.pirate_weather.api_key' => 'test-key']);

        $catchment = \App\Models\Catchment::factory()->create([
            'gauges' => [['name' => 'Wookey', 'rloi_id' => '3059', 'type' => 'river']],
        ]);
        $system = \App\Models\CaveSystem::factory()->create(['catchment_id' => $catchment->id]);
        $cave = Cave::factory()->create([
            'cave_system_id' => $system->id,
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        // First request: the readings endpoint times out, so no river data and —
        // critically — nothing is cached. Second request: it recovers and data
        // comes through, proving a transient timeout doesn't blank the gauge for
        // the full 15-minute cache window.
        $readingsCall = 0;
        Http::fake([
            'api.pirateweather.net/*' => Http::response(['currently' => ['temperature' => 15.5]], 200),
            'environment.data.gov.uk/flood-monitoring/id/stations?RLOIid=3059' => Http::response([
                'items' => [['stationReference' => '52205']],
            ], 200),
            'environment.data.gov.uk/flood-monitoring/id/stations/52205/readings*' => function () use (&$readingsCall) {
                if (++$readingsCall === 1) {
                    throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out');
                }

                return Http::response(['items' => [['dateTime' => '2026-06-14T10:00:00Z', 'value' => 0.55]]], 200);
            },
            'environment.data.gov.uk/flood-monitoring/id/stations/52205/stageScale' => Http::response([
                'items' => ['typicalRangeLow' => 0.1, 'typicalRangeHigh' => 0.9],
            ], 200),
        ]);

        $first = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");
        $first->assertStatus(200);
        $this->assertCount(0, $first->json('data.river_levels'));

        $second = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");
        $second->assertStatus(200);
        $this->assertCount(1, $second->json('data.river_levels'));
        $this->assertEquals(0.55, $second->json('data.river_levels.0.latest_value'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_historic_rain_data()
    {
        $this->actingAs(User::factory()->create());
        config(['services.pirate_weather.api_key' => 'test-key']);

        $cave = Cave::factory()->create([
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        // Mock Pirate Weather Time Machine calls (8 calls: 7 past days + today so far)
        Http::fake([
            'timemachine.pirateweather.net/*' => Http::response([
                'daily' => [
                    'data' => [
                        ['time' => 1234567890, 'precipIntensity' => 0.5],
                    ],
                ],
                'hourly' => [
                    'data' => [
                        ['time' => 1234567890, 'precipIntensity' => 0.1],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/historic");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'day_stats',
                    'hourly' => [
                        '*' => [
                            'time',
                            'precipIntensity',
                            'precipProbability',
                        ],
                    ],
                ],
            ],
        ]);

        // Verify we got 8 days of data (7 past days + today so far)
        $this->assertCount(8, $response->json('data'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_handles_a_pooled_request_connection_failure_gracefully()
    {
        $this->actingAs(User::factory()->create());
        config(['services.pirate_weather.api_key' => 'test-key']);

        $cave = Cave::factory()->create([
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        // One pooled Time Machine request times out (returned as a
        // ConnectionException, not a Response). It must not crash the whole batch
        // (regression: ConnectionException::successful() fatal error) — the other
        // days should still come through.
        $callCount = 0;
        Http::fake([
            'timemachine.pirateweather.net/*' => function () use (&$callCount) {
                ++$callCount;
                if ($callCount === 1) {
                    throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Connection timed out');
                }

                return Http::response([
                    'daily' => ['data' => [['time' => 1234567890, 'precipIntensity' => 0.5]]],
                    'hourly' => ['data' => [['time' => 1234567890, 'precipIntensity' => 0.1]]],
                ], 200);
            },
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/historic");

        $response->assertStatus(200);
        // 8 days requested (incl. today so far), 1 failed — the remaining 7 still come through.
        $this->assertCount(7, $response->json('data'));
    }
}
