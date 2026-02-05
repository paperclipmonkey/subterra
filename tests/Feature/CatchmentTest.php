<?php

namespace Tests\Feature;

use App\Models\Catchment;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\User;
use App\Services\RiverLevelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatchmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_catchment()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $response = $this->actingAs($admin)->postJson('/api/admin/catchments', [
            'name' => 'Test Catchment',
            'reference_id' => 'REF123',
            'gauges' => [
                ['name' => 'Test Gauge', 'rloi_id' => '3059']
            ]
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Test Catchment')
            ->assertJsonPath('data.gauges.0.rloi_id', '3059');

        $this->assertDatabaseHas('catchments', ['reference_id' => 'REF123']);
    }

    public function test_cave_weather_includes_river_levels()
    {
        // 1. Setup Data
        $catchment = Catchment::create([
            'name' => 'Mendip Catchment',
            'reference_id' => 'MENDIP1',
            'gauges' => [
                ['name' => 'Wookey', 'rloi_id' => '3059']
            ]
        ]);

        $system = CaveSystem::create([
            'name' => 'Swildons Hole',
            'slug' => 'swildons-hole',
            'length' => 1000,
            'vertical_range' => 100,
            'catchment_id' => $catchment->id
        ]);

        $cave = Cave::factory()->create([
            'cave_system_id' => $system->id,
            'location_lat' => 51.24,
            'location_lng' => -2.67
        ]);

        // 2. Mock river level service internal HTTP calls or mock the service entireley.
        // Easier to mock the external http calls if using Real service, or mock the service class.
        // Let's mock the service class injection to avoid external API calls
        $mockService = $this->mock(RiverLevelService::class);
        $mockService->shouldReceive('getEnhancedReading')
            ->with('3059')
            ->andReturn([
                'reading' => [['dateTime' => '2023-01-01T12:00:00Z', 'value' => 1.5]],
                'latest_value' => 1.5,
                'latest_time' => '2023-01-01T12:00:00Z',
                'trend' => 'Steady',
                'state' => 'High',
                'metadata' => [
                    'typicalRangeLow' => 0.5,
                    'typicalRangeHigh' => 1.2
                ]
            ]);

        // We also need to mock WeatherService as CaveWeatherController uses it
        $this->mock(\App\Services\WeatherService::class)
            ->shouldReceive('getForecast')
            ->andReturn(['currently' => [], 'hourly' => [], 'daily' => []]);

        // 3. Act
        $user = User::factory()->create();
        $response = $this->actingAs($user)->getJson("/api/caves/{$cave->slug}/weather/forecast");

        // 4. Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.river_levels.0.name', 'Wookey')
            ->assertJsonPath('data.river_levels.0.reading.0.value', 1.5);
    }
}
