<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Cave;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

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
                    ]
                ],
                'daily' => [
                    'data' => [
                        ['time' => 1234567890, 'temperatureHigh' => 18.0, 'temperatureLow' => 12.0],
                    ]
                ],
            ], 200)
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'currently',
                'hourly',
                'daily'
            ]
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_historical_weather_for_cave()
    {
        $this->actingAs(User::factory()->create());
        
        // Set the API key for the test
        config(['services.pirate_weather.api_key' => 'test-key']);
        
        $cave = Cave::factory()->create([
            'location_lat' => 51.4545,
            'location_lng' => -2.5879,
        ]);

        // Mock the Pirate Weather API responses
        Http::fake([
            'api.pirateweather.net/*' => Http::response([
                'daily' => [
                    'data' => [
                        [
                            'time' => 1234567890,
                            'temperatureHigh' => 18.0,
                            'temperatureLow' => 12.0,
                            'summary' => 'Partly Cloudy'
                        ],
                    ]
                ],
            ], 200)
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/historical");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'time',
                    'temperatureHigh',
                    'temperatureLow',
                ]
            ]
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
            'api.pirateweather.net/*' => Http::response(null, 500)
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/forecast");

        $response->assertStatus(503);
        $response->assertJson([
            'error' => 'Unable to fetch weather data'
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
    public function it_handles_historical_weather_api_failure_gracefully()
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
            'api.pirateweather.net/*' => Http::response(null, 500)
        ]);

        $response = $this->getJson("/api/caves/{$cave->slug}/weather/historical");

        $response->assertStatus(503);
        $response->assertJson([
            'error' => 'Unable to fetch historical weather data'
        ]);
    }
}
