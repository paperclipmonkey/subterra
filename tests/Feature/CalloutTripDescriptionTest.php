<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Callout;
use App\Models\OnCallShift;
use App\Services\CalloutService;
use App\Services\SmsService;
use App\Services\GcpWatchdogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class CalloutTripDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_trip_description_is_populated_from_callout_trip_plan()
    {
        // 1. Setup On-Call Coverage and Location Data
        $user = User::factory()->create(['is_approved' => true]);
        $admin = User::factory()->create(['is_admin' => true]);
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => now()->subDay(),
            'end_at' => now()->addDay(),
        ]);

        $system = \App\Models\CaveSystem::factory()->create();
        $cave = \App\Models\Cave::factory()->create(['cave_system_id' => $system->id]);

        // 2. Mock SMS Service
        $smsMock = Mockery::mock(SmsService::class);
        $smsMock->shouldReceive('sendMessage')->zeroOrMoreTimes();
        // Mock the GCP Watchdog service
        $mockWatchdog = Mockery::mock(GcpWatchdogService::class);
        $mockWatchdog->shouldReceive('register')->andReturn(null);
        $mockWatchdog->shouldReceive('cancel')->andReturn(true);

        $calloutService = new CalloutService($smsMock, $mockWatchdog); // Modified this line

        // 3. Create Callout via Service (simulating frontend)
        // Frontend sends 'trip_plan' but NOT 'description'
        $callout = $calloutService->create($user, [ // Modified this line
            'cave_id' => $cave->id,
            'callout_time' => now()->addHours(5)->toDateTimeString(),
            'trip_plan' => 'Detailed route: Main Entrance to Sump 1',
            'car_registration' => 'ABC 123',
            'car_parking' => 'Main lot',
            'participants' => [
                ['name' => 'John Doe', 'phone' => '07123456789']
            ]
        ]);

        // 4. Assert Callout state
        $this->assertEquals('Detailed route: Main Entrance to Sump 1', $callout->trip_plan);
        // This is the current buggy state: description gets default value
        // $this->assertEquals('Callout created via API', $callout->description);

        // 5. Cancel Callout to generate Trip
        $trip = $calloutService->cancel($callout);

        // 6. Assert Trip Description
        // This is what the user expects: it should NOT be the default message
        $this->assertNotEquals('Callout created via API', $trip->description);
        $this->assertEquals('Detailed route: Main Entrance to Sump 1', $trip->description);
    }
}
