<?php
namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Models\Cave;
use App\Services\TimezoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimezoneTest extends TestCase 
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_derives_timezone_from_cave_coordinates()
    {
        // Test UK coordinates
        $timezone = TimezoneService::getTimezoneFromCoordinates(51.5074, -0.1278); // London
        $this->assertEquals('Europe/London', $timezone);
        
        // Test US coordinates  
        $timezone = TimezoneService::getTimezoneFromCoordinates(40.7128, -74.0060); // New York
        $this->assertEquals('America/New_York', $timezone);
        
        // Test unknown coordinates
        $timezone = TimezoneService::getTimezoneFromCoordinates(0, 0); // Middle of ocean
        $this->assertEquals('UTC', $timezone);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_timezone_in_trip_resource()
    {
        $user = User::factory()->create();
        $cave = Cave::factory()->create([
            'location_lat' => 51.5074,  // London
            'location_lng' => -0.1278
        ]);
        $trip = Trip::factory()->create([
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'visibility' => 'public'
        ]);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips/' . $trip->id);
        
        $response->assertOk();
        $response->assertJsonPath('data.timezone', 'Europe/London');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_utc_timezone_for_cave_without_coordinates()
    {
        $user = User::factory()->create();
        $cave = Cave::factory()->create([
            'location_lat' => null,
            'location_lng' => null
        ]);
        $trip = Trip::factory()->create([
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'visibility' => 'public'
        ]);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips/' . $trip->id);
        
        $response->assertOk();
        $response->assertJsonPath('data.timezone', 'UTC');
    }
}