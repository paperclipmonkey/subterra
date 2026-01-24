<?php
Namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Support\JsonSchemaValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase, JsonSchemaValidator;

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
    public function it_returns_a_collection_of_users()
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $users = User::factory()->count(3)->create();

        $response = $this->getJson(route('users.index'));

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
        $admin = User::factory()->create(['is_admin' => true]);
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
            'photo' => 'profile/custom-photo.jpg'
        ]);
        
        // Ensure the file exists in our fake storage
        Storage::disk('public')->put('profile/custom-photo.jpg', 'fake-image-content');

        // Create an incident note from this user
        $callout = \App\Models\Callout::factory()->create();
        $incident = \App\Models\Incident::factory()->create(['callout_id' => $callout->id]);
        $note = \App\Models\IncidentNote::factory()->create([
            'incident_id' => $incident->id,
            'user_id' => $user->id,
            'content' => 'This is an important safety note.'
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
            'content' => 'This is an important safety note.'
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function user_deletion_is_completely_thorough_and_preserves_defaults()
    {
        Storage::fake('public');

        // Create a user with the DEFAULT photo
        $user = User::factory()->create([
            'photo' => 'profile/default.webp'
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
        $admin = User::factory()->create(['is_admin' => true]);
        $callout = \App\Models\Callout::factory()->create();
        $incident = \App\Models\Incident::factory()->create([
            'callout_id' => $callout->id,
            'incident_controller_id' => $admin->id
        ]);

        $this->actingAs($admin, 'sanctum');
        $response = $this->deleteJson("/api/users/{$admin->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
        
        // Verify incident controller is now NULL
        $this->assertDatabaseHas('incidents', [
            'id' => $incident->id,
            'incident_controller_id' => null
        ]);
    }
}