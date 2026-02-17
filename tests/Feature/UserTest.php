<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JsonSchemaValidator;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;
    use JsonSchemaValidator;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_current_user()
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        $response = $this->getJson(route('users.me'));

        $response->assertOk();
        $response->assertJsonFragment(['email' => auth()->user()->email]);
        $this->assertResponseMatchesSchema($response, 'endpoints/users-me');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_401_when_accessing_me_unauthenticated()
    {
        $response = $this->getJson(route('users.me'));

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_a_collection_of_users()
    {
        $this->actingAs(User::factory()->create(['name' => 'Test User 1']), 'sanctum');
        $users = User::factory()->count(3)->create([
            'name' => fake()->name().' Test',
        ]);

        // Search for 'test' which appears in all user names
        $response = $this->getJson(route('users.index', ['search' => 'test']));

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
        $this->assertResponseMatchesSchema($response, 'endpoints/users-index');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_a_user_if_not_exists()
    {
        Storage::fake('media');

        $payload = [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];

        $this->actingAs(User::factory()->create(), 'sanctum');

        $response = $this->postJson(route('users.create'), $payload);

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
            'is_active' => false,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_user_detail_resource()
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum');

        $response = $this->getJson(route('users.show', $user));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $user->id]);
        $this->assertResponseMatchesSchema($response, 'endpoints/users-show');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_doesnt_update_user_bio_if_not_user()
    {
        $user = User::factory()->create([
            'bio' => null,
        ]);

        $payload = [
            'bio' => 'I love chess.',
        ];

        $this->actingAs(User::factory()->create(), 'sanctum');

        $response = $this->putJson(route('users.store', $user), $payload);

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_user_bio()
    {
        $user = User::factory()->create([
            'bio' => null,
        ]);

        $payload = [
            'bio' => 'I love chess.',
        ];

        $this->actingAs($user, 'sanctum');

        $response = $this->putJson(route('users.store', $user), $payload);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'bio' => 'I love chess.',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_user_preferences()
    {
        $user = User::factory()->create([
            'phone' => null,
            'email_trophies' => true,
            'email_tagged' => true,
            'email_platform_news' => true,
            'visibility_addable' => 'public',
        ]);

        $payload = [
            'phone' => '07123456789',
            'email_trophies' => false,
            'email_tagged' => false,
            'email_platform_news' => false,
            'visibility_addable' => 'club',
        ];

        $this->actingAs($user, 'sanctum');

        $response = $this->putJson(route('users.store', $user), $payload);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '07123456789',
            'email_trophies' => false,
            'email_tagged' => false,
            'email_platform_news' => false,
            'visibility_addable' => 'club',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_users_by_visibility()
    {
        $club = \App\Models\Club::factory()->create();
        $currentUser = User::factory()->create(['name' => 'Test Current User']);
        $currentUser->clubs()->attach($club->id, ['status' => 'approved']);

        // User in same club with 'club' visibility
        $clubUser = User::factory()->create(['visibility_addable' => 'club', 'name' => 'Test Club User']);
        $clubUser->clubs()->attach($club->id, ['status' => 'approved']);

        // User in different club with 'club' visibility
        $otherClubUser = User::factory()->create(['visibility_addable' => 'club', 'name' => 'Test Other User']);

        // Public user
        $publicUser = User::factory()->create(['visibility_addable' => 'public', 'name' => 'Test Public User']);

        $this->actingAs($currentUser, 'sanctum');

        // Search for 'test' which appears in all user names
        $response = $this->getJson(route('users.index', ['search' => 'test']));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $clubUser->id]);
        $response->assertJsonFragment(['id' => $publicUser->id]);
        $response->assertJsonMissing(['id' => $otherClubUser->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_current_user_preferences()
    {
        $user = User::factory()->create([
            'phone' => null,
            'email_trophies' => true,
        ]);

        $payload = [
            'phone' => '07123456789',
            'email_trophies' => false,
        ];

        $this->actingAs($user, 'sanctum');

        $response = $this->putJson(route('users.me.update'), $payload);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '07123456789',
            'email_trophies' => false,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_current_user()
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum');

        $response = $this->deleteJson(route('users.me.destroy'));

        $response->assertOk();
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_uk_phone_numbers()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Valid 07... (11 digits)
        $response = $this->putJson(route('users.me.update'), ['phone' => '07123456789']);
        $response->assertOk();

        // Valid +44... (13 chars)
        $response = $this->putJson(route('users.me.update'), ['phone' => '+447123456789']);
        $response->assertOk();

        // Invalid: too short (10 digits)
        $response = $this->putJson(route('users.me.update'), ['phone' => '0712345678']);
        $response->assertStatus(422);

        // Invalid: too long (12 digits)
        $response = $this->putJson(route('users.me.update'), ['phone' => '071234567890']);
        $response->assertStatus(422);

        // Invalid: +44 too short (12 chars)
        $response = $this->putJson(route('users.me.update'), ['phone' => '+44712345678']);
        $response->assertStatus(422);

        // Invalid: +44 too long (14 chars)
        $response = $this->putJson(route('users.me.update'), ['phone' => '+4471234567890']);
        $response->assertStatus(422);

        // Invalid: wrong prefix
        $response = $this->putJson(route('users.me.update'), ['phone' => '01123456789']);
        $response->assertStatus(422);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_deletion_removes_user_and_deletes_solo_trips()
    {
        $this->withoutExceptionHandling();
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        // Trip where user is the only participant
        $soloTrip = \App\Models\Trip::factory()->create();
        $soloTrip->participants()->attach($user->id);

        // Trip where user and another are participants
        $sharedTrip = \App\Models\Trip::factory()->create();
        $sharedTrip->participants()->attach([$user->id, $otherUser->id]);

        // User is in a club
        $club = \App\Models\Club::factory()->create();
        $user->clubs()->attach($club->id);

        $this->actingAs($user, 'sanctum');
        $response = $this->deleteJson("/api/users/{$user->id}");
        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('trips', ['id' => $soloTrip->id]);
        $this->assertDatabaseHas('trips', ['id' => $sharedTrip->id]);
        $this->assertDatabaseMissing('club_user', ['user_id' => $user->id, 'club_id' => $club->id]);
        $this->assertDatabaseMissing('trip_user', ['user_id' => $user->id, 'trip_id' => $sharedTrip->id]);
        $this->assertDatabaseHas('trip_user', ['user_id' => $otherUser->id, 'trip_id' => $sharedTrip->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_owner_cannot_delete_user_account()
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $this->actingAs($otherUser, 'sanctum');
        $response = $this->deleteJson("/api/users/{$user->id}");
        $response->assertStatus(403);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_can_delete_any_user_account()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->actingAs($admin, 'sanctum');
        $response = $this->deleteJson("/api/users/{$user->id}");
        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_deletion_removes_photo_and_orphans_incident_notes()
    {
        Storage::fake('public');

        $user = User::factory()->create([
            'photo' => 'profile/custom-photo.jpg',
        ]);

        // Ensure the file exists in our fake storage
        Storage::disk('public')->put('profile/custom-photo.jpg', 'fake-image-content');

        // Create an incident note from this user
        $callout = \App\Models\Callout::factory()->create();
        $incident = \App\Models\Incident::factory()->create(['callout_id' => $callout->id]);
        $note = \App\Models\IncidentNote::factory()->create([
            'incident_id' => $incident->id,
            'user_id' => $user->id,
            'content' => 'This is an important safety note.',
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->deleteJson("/api/users/{$user->id}");
        $response->assertOk();

        // Verify photo is gone
        Storage::disk('public')->assertMissing('profile/custom-photo.jpg');

        // Verify note still exists but user_id is null
        $this->assertDatabaseHas('incident_notes', [
            'id' => $note->id,
            'user_id' => null,
            'content' => 'This is an important safety note.',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_deletion_is_completely_thorough_and_preserves_defaults()
    {
        Storage::fake('public');

        // Create a user with the DEFAULT photo
        $user = User::factory()->create([
            'photo' => 'profile/default.webp',
        ]);
        Storage::disk('public')->put('profile/default.webp', 'shared-default-image');

        // 1. Check Medals Detachment
        $medal = \App\Models\Medal::factory()->create();
        $user->medals()->attach($medal->id, ['awarded_at' => now()]);

        // 2. Check Callouts and Cascades
        $callout = \App\Models\Callout::factory()->create(['user_id' => $user->id]);
        $incident = \App\Models\Incident::factory()->create(['callout_id' => $callout->id]);

        // 3. Check Collections Cascade
        $collection = \App\Models\Collection::factory()->create(['user_id' => $user->id]);

        // 4. Check OnCallShifts Cascade
        $shift = \App\Models\OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->addDay(),
            'end_at' => now()->addDay()->addHours(8),
        ]);

        $this->actingAs($user, 'sanctum');
        $response = $this->deleteJson("/api/users/{$user->id}");
        $response->assertOk();

        // ASSERTIONS

        // Account is gone
        $this->assertDatabaseMissing('users', ['id' => $user->id]);

        // DEFAULT PHOTO IS PRESERVED (IMPORTANT!)
        Storage::disk('public')->assertExists('profile/default.webp');

        // Detachments
        $this->assertDatabaseMissing('medal_user', ['user_id' => $user->id, 'medal_id' => $medal->id]);

        // Cascades
        $this->assertDatabaseMissing('callouts', ['id' => $callout->id]);
        $this->assertDatabaseMissing('incidents', ['id' => $incident->id]);
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
        $this->assertDatabaseMissing('on_call_shifts', ['id' => $shift->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_deletion_clears_incident_controller_role()
    {
        $admin = User::factory()->admin()->create();
        $callout = \App\Models\Callout::factory()->create();
        $incident = \App\Models\Incident::factory()->create([
            'callout_id' => $callout->id,
            'incident_controller_id' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum');
        $response = $this->deleteJson("/api/users/{$admin->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);

        // Verify incident controller is now NULL
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'incident_controller_id' => null,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_on_call_information_in_user_resource()
    {
        $user = User::factory()->admin()->create();

        // 1. Create an on-call shift for this user
        \App\Models\OnCallShift::create([
            'user_id' => $user->id,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(2),
        ]);

        // 2. Create some open callouts (active and triggered)
        \App\Models\Callout::factory()->create(['status' => 'active']);
        \App\Models\Callout::factory()->create(['status' => 'triggered']);
        \App\Models\Callout::factory()->create(['status' => 'cancelled']); // Should not be counted

        $this->actingAs($user, 'sanctum');

        $response = $this->getJson(route('users.me'));

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'on_call' => true,
                'open_callouts_count' => 2,
            ],
        ]);

        $this->assertNotNull($response->json('data.on_call_until'));
    }
}
