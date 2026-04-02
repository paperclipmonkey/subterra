<?php

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
    public function it_returns_historic_rain_data()
    {
        $this->actingAs(User::factory()->create());
        config(['services.pirate_weather.api_key' => 'test-key']);

        $cave = Cave::factory()->create([
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        // Mock Pirate Weather Time Machine calls (7 calls for 7 days)
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

        // Verify we got 7 days of data
        $this->assertCount(7, $response->json('data'));
    }
}
