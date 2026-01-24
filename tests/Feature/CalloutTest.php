<?php

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\OnCallShift;
use App\Models\User;
use App\Services\SmsService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class CalloutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Mock SmsService to avoid actual calls and verify behavior
        $this->mock(SmsService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendMessage')->andReturn((object) ['messageid' => 'mocked']);
        });
    }

    public function test_user_can_create_callout_when_admin_on_call()
    {
        Mail::fake();
        $user = User::factory()->create();
        $cave = Cave::factory()->create();

        // Create On-Call Shift coverage
        $admin = User::factory()->create(['is_admin' => true]);
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'description' => 'Test Trip',
            'trip_plan' => 'Detailed Plan', // Added
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'location_data' => ['latitude' => 54.2, 'longitude' => -2.5, 'accuracy' => 10],
            'team_details' => 'Alice, Bob',
            'participants' => [
                ['name' => 'Alice', 'email' => 'alice@test.com', 'phone' => '+111'],
                ['name' => 'Bob', 'email' => 'bob@test.com']
            ]
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/callouts', $payload);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('callouts', [
            'user_id' => $user->id,
            'cave_id' => $cave->id,
            'status' => 'active',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm'
        ]);

        $callout = Callout::where('user_id', $user->id)->first();
        $this->assertEquals(['latitude' => 54.2, 'longitude' => -2.5, 'accuracy' => 10], $callout->location_data);
        $this->assertNotNull($callout->request_data);
        $this->assertArrayHasKey('ip', $callout->request_data);

        // Verify participants stored
        $callout = Callout::where('user_id', $user->id)->first();
        $this->assertCount(2, $callout->participants);
    }

    public function test_create_callout_fails_if_no_admin_coverage()
    {
        $user = User::factory()->create();
        
        // NO OnCallShift created

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'description' => 'Unsafe Trip',
            'trip_plan' => 'Plan', // Added
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'participants' => [
                ['name' => 'Test User', 'phone' => '+111']
            ]
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/callouts', $payload);

        // Controller catches Exception and returns 422
        $response->assertStatus(422); 
        $response->assertJson(['message' => 'Cannot create callout: No administrator is on-call at ' . Carbon::parse($payload['callout_time'])->toDateTimeString()]);
    }

    public function test_user_can_cancel_own_callout()
    {
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $response = $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel");

        $response->assertStatus(200);
        
        // It should be hard deleted (or status changed? Service says delete)
        $this->assertDatabaseMissing('callouts', ['id' => $callout->id]);
    }

    public function test_user_cannot_cancel_others_callout()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $owner->id, 'status' => 'active']);

        $response = $this->actingAs($attacker)
            ->postJson("/api/callouts/{$callout->id}/cancel");

        $response->assertStatus(404); // Scoped findOrFail returns 404
    }

    public function test_user_can_mark_safe_after_rescue_initiated()
    {
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create([
            'user_id' => $user->id, 
            'status' => 'triggered'
        ]);
        
        // Create an incident for this callout (simulating rescue initiated)
        $incident = \App\Models\Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open'
        ]);

        // User cancels their callout
        $response = $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel");

        $response->assertStatus(200);
        
        // Callout should NOT be deleted, but status should be 'cancelled'
        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'status' => 'cancelled'
        ]);

        // Incident should remain OPEN
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => 'open'
        ]);

        // Should have a system note
        $this->assertDatabaseHas('incident_notes', [
            'incident_id' => $incident->id,
            'user_id' => null, // System note
        ]);
    }

    public function test_creator_does_not_receive_redundant_sms_when_in_participants()
    {
        Mail::fake();
        $user = User::factory()->create(['phone' => '07123456789']);
        $cave = Cave::factory()->create();

        // Create On-Call Shift coverage
        $admin = User::factory()->create(['is_admin' => true]);
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'description' => 'Test Trip',
            'trip_plan' => 'Detailed Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'participants' => [
                ['name' => $user->name, 'user_id' => $user->id, 'phone' => $user->phone], // Creator is here
                ['name' => 'Friend', 'phone' => '07987654321']
            ]
        ];

        // Mock SmsService to count calls
        $smsMock = Mockery::mock(SmsService::class);
        
        // Should be called 2 times: once for creator registration, once for Friend (participant)
        // Explicitly NOT called for creator as participant.
        $smsMock->shouldReceive('sendMessage')
            ->times(2)
            ->andReturn((object) ['messageid' => 'mocked']);

        $this->app->instance(SmsService::class, $smsMock);

        $response = $this->actingAs($user)
            ->postJson('/api/callouts', $payload);

        $response->assertStatus(201);
    }
}
