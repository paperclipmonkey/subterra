<?php
Namespace Tests\Feature;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_a_collection_of_users()
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $users = User::factory()->count(3)->create();

        $response = $this->getJson(route('users.index'));

        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_a_user_if_not_exists()
    {
        Storage::fake('media');

        $payload = [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ];

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

        $response = $this->getJson(route('users.show', $user));

        $response->assertOk();
        $response->assertJsonFragment(['id' => $user->id]);
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
}