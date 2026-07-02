<?php

declare(strict_types=1);

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

    public function test_callout_creation_requires_a_verified_phone()
    {
        $user = User::factory()->withApprovedClub()->create([
            'phone' => '+447700900900',
            'phone_verified_at' => null,
        ]);
        $cave = Cave::factory()->create();
        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        $response = $this->actingAs($user)->postJson('/api/callouts', [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'description' => 'Unverified attempt',
            'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'participants' => [['name' => 'Alice', 'phone' => '+447999000111']],
        ]);

        $response->assertStatus(422)->assertJsonFragment(['code' => 'phone_unverified']);
        $this->assertDatabaseMissing('callouts', ['description' => 'Unverified attempt']);
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

    public function test_saving_registered_users_and_manual_guests_to_callout()
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
            'name' => 'Existing User',
            'email' => 'existing@test.com',
            'phone' => '07777777777', // Will be sent as '🔒 Hidden'
        ]);

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'description' => 'Test Trip with mix of users',
            'trip_plan' => 'Detailed Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Bull Pot Farm',
            'participants' => [
                // 1. Current user (automatically added)
                ['user_id' => $user->id, 'name' => 'Current User', 'phone' => '07111111111'],
                // 2. Existing user (phone hidden from UI payload, meaning backend should fetch it)
                ['user_id' => $registeredParticipant->id, 'name' => $registeredParticipant->name, 'phone' => '🔒 Hidden'],
                // 3. Manual guest with a provided phone number
                ['name' => 'Manual Guest', 'phone' => '+447999999999'],
            ],
        ];

        $response = $this->actingAs($user)
            ->postJson('/api/callouts', $payload);

        $response->assertStatus(201);

        $callout = Callout::where('user_id', $user->id)->first();
        $this->assertCount(3, $callout->participants);

        // Verify the current user is stored
        $this->assertDatabaseHas('callout_participants', [
            'callout_id' => $callout->id,
            'user_id' => $user->id,
            'phone' => '07111111111',
        ]);

        // Verify the existing user is stored and their phone number wasn't literally saved as '🔒 Hidden'
        $dbParticipant = $callout->participants()->where('user_id', $registeredParticipant->id)->first();
        $this->assertEquals('Existing User', $dbParticipant->name);
        $this->assertNotEquals('🔒 Hidden', $dbParticipant->phone);
        // Backend doesn't currently auto-fill this if passed '🔒 Hidden' unless CalloutController does it,
        // but let's test what the Controller *actually* does with '🔒 Hidden' payload to ensure it is handled.

        // Verify the manual guest is stored with string fields
        $this->assertDatabaseHas('callout_participants', [
            'callout_id' => $callout->id,
            'user_id' => null,
            'name' => 'Manual Guest',
            'phone' => '+447999999999',
        ]);
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

    public function test_cancelling_callout_twice_is_idempotent()
    {
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel")
            ->assertStatus(200);

        $tripsAfterFirst = \App\Models\Trip::count();

        // A second cancel must be a harmless no-op: still 200, no duplicate trip,
        // no second batch of cancellation emails.
        $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel")
            ->assertStatus(200);

        $this->assertSame(
            $tripsAfterFirst,
            \App\Models\Trip::count(),
            'A repeated cancel must not create a duplicate trip'
        );
        $this->assertDatabaseHas('callouts', ['id' => $callout->id, 'status' => 'cancelled']);
    }

    public function test_cancelling_a_resolved_callout_is_a_no_op()
    {
        // Regression (H1): Incident::resolve() sets the callout to 'resolved'. A
        // re-clicked guest cancel link must NOT create a second Trip, re-send
        // cancellation emails, or flip the status resolved -> cancelled.
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'triggered']);
        $incident = \App\Models\Incident::create(['callout_id' => $callout->id, 'status' => 'open']);

        $incident->resolve();
        $this->assertEquals('resolved', $callout->fresh()->status);

        $tripsBefore = \App\Models\Trip::count();

        $this->postJson("/api/callouts/{$callout->id}/cancel")
            ->assertStatus(200);

        $this->assertEquals('resolved', $callout->fresh()->status, 'A resolved callout must never be flipped back to cancelled.');
        $this->assertSame($tripsBefore, \App\Models\Trip::count(), 'Cancelling a resolved callout must not create a trip.');
        Mail::assertNothingSent();
    }

    public function test_concurrent_cancels_with_stale_models_only_proceed_once()
    {
        // Regression (H2): two requests can both pass the in-memory status guard with
        // stale models. The transactional row-lock re-check must let only one proceed —
        // no duplicate Trip and no second email blast.
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $service = app(\App\Services\CalloutService::class);

        // Two independent (and soon mutually stale) in-memory copies of the same callout.
        $copyA = Callout::find($callout->id);
        $copyB = Callout::find($callout->id);

        $this->assertNotNull($service->cancel($copyA), 'First cancel should proceed.');

        $tripsAfterFirst = \App\Models\Trip::count();
        $emailsAfterFirst = Mail::sent(\App\Mail\CalloutCancelled::class)->count();

        // copyB still believes the callout is active; the DB gate must stop it.
        $this->assertNull($service->cancel($copyB), 'Second cancel must be rejected by the atomic status gate.');

        $this->assertSame($tripsAfterFirst, \App\Models\Trip::count(), 'The losing cancel must not create a duplicate trip.');
        $this->assertSame($emailsAfterFirst, Mail::sent(\App\Mail\CalloutCancelled::class)->count(), 'The losing cancel must not re-send cancellation emails.');
        $this->assertEquals('cancelled', $callout->fresh()->status);
    }

    public function test_repeated_cancel_does_not_overwrite_forensic_metadata()
    {
        // The forensic snapshot (IP / user agent / location) must record the request
        // that actually cancelled the callout, not whichever request hit the link last.
        Mail::fake();
        $user = User::factory()->create();
        $callout = Callout::factory()->create(['user_id' => $user->id, 'status' => 'active']);

        $this->postJson("/api/callouts/{$callout->id}/cancel", ['location' => 'Original location'])
            ->assertStatus(200);

        $this->assertEquals('Original location', $callout->fresh()->cancelled_location);

        $this->postJson("/api/callouts/{$callout->id}/cancel", ['location' => 'Overwritten location'])
            ->assertStatus(200);

        $this->assertEquals('Original location', $callout->fresh()->cancelled_location, 'A repeated cancel must not overwrite the original forensic record.');
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

    public function test_callout_show_never_exposes_forensic_metadata()
    {
        $owner = User::factory()->create();
        $callout = Callout::factory()->create([
            'user_id' => $owner->id,
            'request_data' => ['ip' => '203.0.113.7', 'user_agent' => 'Mozilla/5.0'],
            'cancelled_ip' => '203.0.113.7',
            'cancelled_user_agent' => 'Mozilla/5.0',
            'cancelled_location' => 'Bull Pot Farm',
        ]);

        // Even the creator (who sees the most) must never receive IP / user-agent
        // forensic metadata — it is personal data with no place in an API response.
        $response = $this->actingAs($owner)->getJson("/api/callouts/{$callout->id}");

        $response->assertStatus(200);
        $response->assertJsonMissing(['ip' => '203.0.113.7']);
        $response->assertJsonMissingPath('data.request_data');
        $response->assertJsonMissingPath('data.cancelled_ip');
        $response->assertJsonMissingPath('data.cancelled_user_agent');
        $response->assertJsonMissingPath('data.cancelled_location');
    }

    public function test_unrelated_authenticated_user_cannot_see_participant_contact_details()
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $callout = Callout::factory()->create([
            'user_id' => $owner->id,
            'car_registration' => 'AB12 CDE',
        ]);
        $callout->participants()->create([
            'name' => 'Visible Person',
            'phone' => '+447777777777',
            'email' => 'visible@test.com',
        ]);

        // A logged-in user who is neither creator, participant, nor a duty
        // officer/admin may identify the callout but not harvest contact details.
        $response = $this->actingAs($stranger)->getJson("/api/callouts/{$callout->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['name' => 'Visible Person']);
        $response->assertJsonMissing(['phone' => '+447777777777']);
        $response->assertJsonMissing(['email' => 'visible@test.com']);
        $response->assertJsonMissing(['car_registration' => 'AB12 CDE']);
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

    public function test_prevent_duplicate_active_callouts()
    {
        $user = User::factory()->withApprovedClub()->create(['phone' => '07111111111']);
        $cave = Cave::factory()->create();

        // Active DO shift
        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        // Create first callout
        Callout::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // Try to create another
        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'trip_plan' => 'Duplicate Trip',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Parking',
            'participants' => [['name' => 'Self', 'phone' => '07111111111']],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $payload);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'One or more participants (or you) are already in an active callout. Please resolve the existing callout first.',
        ]);
    }

    public function test_duplicate_check_matches_differently_formatted_phone_numbers()
    {
        // Regression (M4): "+447700900123" and "07700 900 123" are the same phone.
        // Raw string comparison missed this and allowed a second active callout.
        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        $cave = Cave::factory()->create();

        $activeCallout = Callout::factory()->create([
            'status' => 'active',
            'callout_time' => Carbon::now()->addHours(2),
        ]);
        $activeCallout->participants()->create([
            'name' => 'Bob',
            'phone' => '+447700900123',
        ]);

        $user = User::factory()->withApprovedClub()->create();

        $response = $this->actingAs($user)->postJson('/api/callouts', [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Parking',
            'participants' => [
                ['name' => 'Bob Again', 'phone' => '07700 900 123'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'One or more participants (or you) are already in an active callout. Please resolve the existing callout first.',
        ]);
    }

    public function test_participant_phone_numbers_are_normalised_on_write()
    {
        // Regression (M4): a verbatim "07700 900 123" could never be suffix-matched by
        // the SMS webhook during a live rescue. Phones are normalised before storage.
        Mail::fake();
        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::factory()->create();

        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        $response = $this->actingAs($user)->postJson('/api/callouts', [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'cave_id' => $cave->id,
            'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Parking',
            'participants' => [
                ['name' => 'Spacey', 'phone' => '07700 900 123'],
                ['name' => 'Formatted', 'phone' => '+44 (0770) 090-0124'],
            ],
        ]);

        $response->assertStatus(201);

        $callout = Callout::where('user_id', $user->id)->firstOrFail();
        $this->assertDatabaseHas('callout_participants', [
            'callout_id' => $callout->id,
            'name' => 'Spacey',
            'phone' => '07700900123',
        ]);
        $this->assertDatabaseHas('callout_participants', [
            'callout_id' => $callout->id,
            'name' => 'Formatted',
            'phone' => '+4407700900124',
        ]);
    }

    public function test_callout_time_without_timezone_offset_is_rejected()
    {
        // Regression (M8): a naive "2026-07-02 18:30:00" is parsed as UTC, so a BST
        // client's panic alarm would fire an hour late. An explicit offset is required.
        $user = User::factory()->withApprovedClub()->create();
        $cave = Cave::factory()->create();

        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        $response = $this->actingAs($user)->postJson('/api/callouts', [
            'callout_time' => Carbon::now()->addHours(2)->format('Y-m-d H:i:s'), // naive: no offset
            'cave_id' => $cave->id,
            'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE',
            'car_parking' => 'Parking',
            'participants' => [['name' => 'Alice']],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['callout_time']);
        $this->assertDatabaseMissing('callouts', ['user_id' => $user->id]);
    }

    public function test_callout_creation_fails_when_watchdog_is_configured_but_unavailable()
    {
        // Backup coverage is mandatory: a callout must be watched by BOTH the primary
        // scheduler and the independent GCP backup. If the backup is configured but can't
        // be registered, creation hard-fails and rolls back — we never create a callout
        // that only one system is watching.
        Mail::fake();
        $user = User::factory()->withApprovedClub()->create();

        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        // Watchdog is configured but registration fails (returns null).
        $this->mock(\App\Services\GcpWatchdogService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('register')->once()->andReturn(null);
        });

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'description' => 'Trip Plan', 'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE', 'car_parking' => 'Bull Pot Farm',
            'participants' => [['name' => 'Alice']],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $payload);

        // Creation is rejected and nothing is left behind.
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'We could not register this callout with the backup safety system, so it was not created. Please try again in a moment. If the problem continues, leave your plans with a trusted person and contact a duty officer directly.']);
        $this->assertDatabaseMissing('callouts', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('callout_participants', ['name' => 'Alice']);
    }

    public function test_callout_creation_records_watchdog_registration_on_success()
    {
        Mail::fake();
        $user = User::factory()->withApprovedClub()->create();

        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        // Watchdog registers successfully and returns its id.
        $this->mock(\App\Services\GcpWatchdogService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('register')->once()->andReturn('watchdog-123');
        });

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'description' => 'Trip Plan', 'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE', 'car_parking' => 'Bull Pot Farm',
            'participants' => [['name' => 'Alice']],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $payload);

        $response->assertStatus(201);
        $callout = Callout::where('user_id', $user->id)->firstOrFail();
        $this->assertNotNull($callout->watchdog_registered_at, 'Successful watchdog registration should be recorded.');
    }

    public function test_callout_creation_leaves_watchdog_registered_at_null_when_not_configured()
    {
        // When the watchdog is not configured (e.g. local development or CI), registration
        // is skipped entirely and the callout is created without backup coverage.
        Mail::fake();
        $user = User::factory()->withApprovedClub()->create();

        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        $this->mock(\App\Services\GcpWatchdogService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(false);
            $mock->shouldNotReceive('register');
        });

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'description' => 'Trip Plan', 'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE', 'car_parking' => 'Bull Pot Farm',
            'participants' => [['name' => 'Alice']],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $payload);

        $response->assertStatus(201);
        $callout = Callout::where('user_id', $user->id)->firstOrFail();
        $this->assertNull($callout->watchdog_registered_at);
    }

    public function test_callout_creation_succeeds_even_if_email_service_fails()
    {
        $user = User::factory()->withApprovedClub()->create();

        $admin = User::factory()->dutyOfficer()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => Carbon::now()->subHour(),
            'end_at' => Carbon::now()->addHours(5),
        ]);

        // Mock Mailer to throw exception
        $this->mock(\Illuminate\Contracts\Mail\Mailer::class, function (MockInterface $mock) {
            $mock->shouldReceive('to')->andReturnSelf();
            $mock->shouldReceive('send')->andThrow(new \Exception('Mail service is down'));
        });

        // Mock Watchdog so we don't accidentally hit the real one (registers successfully).
        $this->mock(\App\Services\GcpWatchdogService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('register')->andReturn('watchdog-123');
        });

        $payload = [
            'callout_time' => Carbon::now()->addHours(2)->toIso8601String(),
            'description' => 'Trip Plan', 'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE', 'car_parking' => 'Bull Pot Farm',
            'participants' => [['name' => 'Alice', 'email' => 'test@test.com']],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $payload);

        // Assert it catches the email exception and succeeds anyway
        $response->assertStatus(201);
        $this->assertDatabaseHas('callouts', ['user_id' => $user->id]);
    }

    public function test_callout_fails_if_duty_officer_shift_ends_before_callout_time()
    {
        $user = User::factory()->withApprovedClub()->create();
        $admin = User::factory()->dutyOfficer()->create();

        // Fixed time testing
        $shiftStart = Carbon::parse('2030-01-01 10:00:00');
        $shiftEnd = Carbon::parse('2030-01-01 15:00:00');
        $calloutTime = Carbon::parse('2030-01-01 15:01:00');

        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => $shiftStart,
            'end_at' => $shiftEnd,
        ]);

        $payload = [
            'callout_time' => $calloutTime->toIso8601String(),
            'description' => 'Test', 'trip_plan' => 'Plan',
            'car_registration' => 'AB12 CDE', 'car_parking' => 'Parking',
            'participants' => [['name' => 'Alice']],
        ];

        $response = $this->actingAs($user)->postJson('/api/callouts', $payload);

        $response->assertStatus(422);
        // Carbon formats the string correctly out of the Carbon class, so '2030-01-01 15:01:00' should match
        $response->assertJson([
            'message' => 'Cannot create callout: No administrator is on-call at '.$calloutTime->toDateTimeString(),
        ]);
    }
}
