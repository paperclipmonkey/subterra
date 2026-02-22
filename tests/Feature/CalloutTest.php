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
use Mockery\MockInterface;
use Tests\TestCase;

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
        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::factory()->create();

        // Create On-Call Shift coverage
        $admin = User::factory()->dutyOfficer()->create();
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
                ['name' => 'Bob', 'email' => 'bob@test.com'],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/callouts', $payload);

        if ($response->status() !== 201) {
            dump($response->json());
        }$response->assertStatus(201);

        $this->assertDatabaseHas('callouts', [
            'user_id' => $user->id,
            'cave_id' => $cave->id,
            'status' => 'active',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
        ]);

        $callout = Callout::where('user_id', $user->id)->first();
        $this->assertEquals(['latitude' => 54.2, 'longitude' => -2.5, 'accuracy' => 10], $callout->location_data);
        $this->assertNotNull($callout->request_data);
        $this->assertArrayHasKey('ip', $callout->request_data);

        // Verify participants stored
        $callout = Callout::where('user_id', $user->id)->first();
        $this->assertCount(2, $callout->participants);

        // Verify everyone received the CalloutStarted email
        Mail::assertSent(\App\Mail\CalloutStarted::class, function ($mail) use ($user) {
            return $mail->hasTo('alice@test.com') || $mail->hasTo('bob@test.com') || $mail->hasTo($user->email);
        });
        Mail::assertSentCount(3);
    }

    public function test_participants_with_only_userid_receive_emails()
    {
        Mail::fake();
        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::factory()->create();

        // Admin coverage
        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        // Create a registered user to be the participant
        $registeredParticipant = User::factory()->create([
            'name' => 'Registered Charlie',
            'email' => 'charlie@test.com',
            'phone' => '07777777777',
        ]);

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'description' => 'Test Trip',
            'trip_plan' => 'Detailed Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'participants' => [
                // Frontend autocomplete only provides user_id and name
                ['user_id' => $registeredParticipant->id, 'name' => $registeredParticipant->name],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/callouts', $payload);

        $response->assertStatus(201);

        // Verify the registered participant received the email despite the payload lacking the explicit string
        Mail::assertSent(\App\Mail\CalloutStarted::class, function ($mail) use ($registeredParticipant) {
            return $mail->hasTo('charlie@test.com');
        });

        // 1 for creator, 1 for participant
        Mail::assertSentCount(2);
    }

    public function test_create_callout_fails_if_no_admin_coverage()
    {
        $user = User::factory()->withApprovedClub()->create();

        // NO OnCallShift created

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'description' => 'Unsafe Trip',
            'trip_plan' => 'Plan', // Added
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'participants' => [
                ['name' => 'Test User', 'phone' => '+111'],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/callouts', $payload);

        // Controller catches Exception and returns 422
        $response->assertStatus(422);
    }

    public function test_user_can_cancel_own_callout()
    {
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $response = $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel");

        $response->assertStatus(200);

        // Callout status should be 'cancelled' (not missing)
        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_user_cannot_cancel_others_callout()
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $owner->id, 'status' => 'active']);

        $response = $this->actingAs($attacker)
            ->postJson("/api/callouts/{$callout->id}/cancel");

        $response->assertStatus(403); // Permission check returns 403
    }

    public function test_guest_can_cancel_callout_with_valid_id()
    {
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $response = $this->postJson("/api/callouts/{$callout->id}/cancel", [
            'location' => 'Somewhere',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'status' => 'cancelled',
            'cancelled_location' => 'Somewhere',
        ]);
        $this->assertNotNull($callout->fresh()->cancelled_ip);
    }

    public function test_guest_can_view_callout_details()
    {
        $user = User::factory()->create();
        $cave = Cave::factory()->create();
        $exitCave = Cave::factory()->create();
        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'cave_id' => $cave->id,
            'exit_cave_id' => $exitCave->id,
        ]);

        $response = $this->getJson("/api/callouts/{$callout->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $callout->id);
        $response->assertJsonPath('data.cave.id', $cave->id);
        $response->assertJsonPath('data.exit_cave.id', $exitCave->id);
        // Guests should see limited data (participant_count instead of full participants)
        $response->assertJsonPath('data.participant_count', 0);
    }

    public function test_guest_callout_show_does_not_expose_participant_details()
    {
        $user = User::factory()->create();
        $cave = Cave::factory()->create();
        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'cave_id' => $cave->id,
        ]);
        $callout->participants()->create([
            'name' => 'Secret Person',
            'phone' => '+447777777777',
            'email' => 'secret@test.com',
        ]);

        $response = $this->getJson("/api/callouts/{$callout->id}");

        $response->assertStatus(200);
        // Guests should NOT see participant details
        $response->assertJsonMissing(['phone' => '+447777777777']);
        $response->assertJsonMissing(['email' => 'secret@test.com']);
        $response->assertJsonMissing(['name' => 'Secret Person']);
        // But should see participant count
        $response->assertJsonPath('data.participant_count', 1);
    }

    public function test_authenticated_user_sees_full_callout_details()
    {
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id]);
        $callout->participants()->create([
            'name' => 'Visible Person',
            'phone' => '+447777777777',
        ]);

        $response = $this->actingAs($user)->getJson("/api/callouts/{$callout->id}");

        $response->assertStatus(200);
        // Authenticated users should see full data
        $response->assertJsonFragment(['name' => 'Visible Person']);
    }

    public function test_user_can_mark_safe_after_rescue_initiated()
    {
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'triggered',
        ]);

        // Create an incident for this callout (simulating rescue initiated)
        $incident = \App\Models\Incident::create([
            'callout_id' => $callout->id,
            'status' => 'open',
        ]);

        // User cancels their callout
        $response = $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel");

        $response->assertStatus(200);

        // Callout should NOT be deleted, but status should be 'cancelled'
        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'status' => 'cancelled',
        ]);

        // Incident should remain OPEN
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'status' => 'open',
        ]);

        // Should have a system note
        $this->assertDatabaseHas('incident_notes', [
            'incident_id' => $incident->id,
            'user_id' => null, // System note
        ]);
    }

    public function test_cannot_create_concurrent_callouts_for_same_participant()
    {
        // 1. Setup Admin & Coverage
        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        // 2. Setup Existing Callout with Participant "Bob"
        $user1 = User::factory()->create();
        $cave = Cave::factory()->create();

        // Use service directly or just create models?
        // Better to use models to check validation against them.
        $bobPhone = '+447999123456';

        $activeCallout = Callout::factory()->create([
            'status' => 'active',
            'callout_time' => Carbon::now()->addHours(2),
        ]);
        $activeCallout->participants()->create([
            'name' => 'Bob',
            'phone' => $bobPhone,
        ]);

        // 3. Attempt to create NEW callout with "Bob"
        $user2 = User::factory()->withApprovedClub()->create();

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'description' => 'Concurrent Trip',
            'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Parking',
            'participants' => [
                ['name' => 'Should Fail', 'phone' => $bobPhone],
            ],
        ];

        $response = $this->actingAs($user2)
            ->postJson('/api/callouts', $payload);

        $response->assertStatus(422);
        $response->assertJson([
             'message' => 'One or more participants (or you) are already in an active callout. Please resolve the existing callout first.',
        ]);
    }
}
