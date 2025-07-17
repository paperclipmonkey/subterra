<?php
namespace Tests\Feature;

use App\Models\Trip;
use App\Models\User;
use App\Models\Cave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;
use Tests\Support\JsonSchemaValidator;


class TripTest extends TestCase {
    use RefreshDatabase, JsonSchemaValidator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_all_trips()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        // Create public trip (should be visible)
        $publicTrip = Trip::factory()->create(['visibility' => 'public']);
        
        // Create private trip where user is not participant (should not be visible)
        $privateTrip = Trip::factory()->create(['visibility' => 'private']);
        
        $response = $this->getJson('/api/trips');
        $response->assertOk()->assertJsonStructure(['data']);
        
        // Only public trip should be returned
        $tripIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($publicTrip->id, $tripIds);
        $this->assertNotContains($privateTrip->id, $tripIds);
        $this->assertResponseMatchesSchema($response, 'endpoints/trips-index');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_authenticated_users_trips()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']); // Ensure visibility is public
        $trip->participants()->attach($user);

        $this->actingAs($user);
        $response = $this->getJson('/api/me/trips');
        $response->assertOk()->assertJsonFragment(['id' => $trip->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_downloads_my_trips_csv()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);

        $this->actingAs($user);
        $response = $this->get('/api/me/trips/download');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('Trip ID', $response->streamedContent());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stores_a_trip_with_participants_and_media()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $entrance = Cave::factory()->create();
        Event::fake([\App\Events\TripCreated::class]);
        $tripData = [
            'name' => 'Test Trip',
            'start_time' => "2024-01-01 10:00:00",
            'end_time' => "2024-01-02 10:00:00",
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Test description',
            'participants' => [$participant->id],
            'media' => [
                [
                    'data' => 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../Fixtures/test.png'))
                ]
            ]
        ];

        $this->actingAs($user);
        $response = $this->postJson('/api/trips', $tripData);
        $response->assertCreated()->assertJsonFragment(['name' => 'Test Trip']);
        $this->assertDatabaseHas('trips', ['name' => 'Test Trip']);
        $this->assertDatabaseHas('trip_user', ['user_id' => $participant->id]);
        $trip = Trip::where('name', 'Test Trip')->first();
        $this->assertCount(1, $trip->media);
        Event::assertDispatched(\App\Events\TripCreated::class, function ($event) use ($trip) {
            return $event->trip->id === $trip->id;
        });
        Storage::disk('media')->assertExists($trip->media->first()->filename);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_a_trip()
    {
        $this->actingAs(User::factory()->create());
        $trip = Trip::factory()->create(['visibility' => 'public']); // Ensure visibility is public
        $response = $this->getJson('/api/trips/' . $trip->id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->id]);
        $this->assertResponseMatchesSchema($response, 'endpoints/trips-show');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_a_trip_and_participants_and_media()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $entrance = Cave::factory()->create();
        $trip = Trip::factory()->create(['entrance_cave_id' => $entrance->id]);
        $trip->participants()->attach($user);

        $media = [
            [
                'data' => 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../Fixtures/test.png'))
            ]
        ];

        $updateData = [
            'name' => 'Updated Trip',
            'start_time' => "2024-01-01 10:00:00",
            'end_time' => "2024-01-02 10:00:00",
            'entrance_id' => $entrance->id,
            'description' => 'Updated description',
            'participants' => [$participant->id],
            'media' => $media,
            'existing_media' => [],
        ];

        $this->actingAs($user);
        $response = $this->putJson('/api/trips/' . $trip->id, $updateData);
        $response->assertOk();
        $this->assertDatabaseHas('trips', ['name' => 'Updated Trip']);
        $this->assertDatabaseHas('trip_user', ['user_id' => $participant->id]);
        $trip = $trip->fresh();
        $this->assertCount(1, $trip->media);
        Storage::disk('media')->assertExists($trip->media->first()->filename);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_doesnt_update_a_trip_not_participant()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $this->actingAs($user);
        $response = $this->putJson('/api/trips/' . $trip->id, ['name' => 'Updated Trip']);
        $response->assertStatus(403)->assertJsonFragment(['message' => 'This action is unauthorized.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_a_trip_as_admin()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $entrance = Cave::factory()->create();
        $trip = Trip::factory()->create(['entrance_cave_id' => $entrance->id]);
        $trip->participants()->attach($user);

        $media = [
            [
                'data' => 'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../Fixtures/test.png'))
            ]
        ];

        $updateData = [
            'name' => 'Updated Trip',
            'start_time' => "2024-01-01 10:00:00",
            'end_time' => "2024-01-02 10:00:00",
            'entrance_id' => $entrance->id,
            'description' => 'Updated description',
            'participants' => [$participant->id],
            'media' => $media,
            'existing_media' => [],
        ];

        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $response = $this->putJson('/api/trips/' . $trip->id, $updateData);
        $response->assertOk();
        $this->assertDatabaseHas('trips', ['name' => 'Updated Trip']);
        $this->assertDatabaseHas('trip_user', ['user_id' => $participant->id]);
        $trip = $trip->fresh();
        $this->assertCount(1, $trip->media);
        Storage::disk('media')->assertExists($trip->media->first()->filename);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_deletes_a_trip()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach($user);
        $this->actingAs($user);
        $response = $this->deleteJson('/api/trips/' . $trip->id);
        $response->assertOk()->assertJsonFragment(['message' => 'Trip deleted successfully']);
        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_doesnt_delete_a_trip_not_participant()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $this->actingAs($user);
        $response = $this->deleteJson('/api/trips/' . $trip->id);
        $response->assertStatus(403)->assertJsonFragment(['message' => 'This action is unauthorized.']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_trip_with_default_public_visibility()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $entrance = Cave::factory()->create();
        Event::fake([\App\Events\TripCreated::class]);
        
        $tripData = [
            'name' => 'Test Trip',
            'start_time' => "2024-01-01 10:00:00",
            'end_time' => "2024-01-02 10:00:00",
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Test description',
            'participants' => [$participant->id],
            // No visibility specified - should default to 'public'
        ];

        $this->actingAs($user);
        $response = $this->postJson('/api/trips', $tripData);
        $response->assertCreated();
        
        $trip = Trip::where('name', 'Test Trip')->first();
        $this->assertEquals('public', $trip->visibility);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_creates_trip_with_specified_visibility()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $entrance = Cave::factory()->create();
        Event::fake([\App\Events\TripCreated::class]);
        
        $tripData = [
            'name' => 'Private Trip',
            'start_time' => "2024-01-01 10:00:00",
            'end_time' => "2024-01-02 10:00:00",
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Test description',
            'participants' => [$participant->id],
            'visibility' => 'private',
        ];

        $this->actingAs($user);
        $response = $this->postJson('/api/trips', $tripData);
        $response->assertCreated();
        
        $trip = Trip::where('name', 'Private Trip')->first();
        $this->assertEquals('private', $trip->visibility);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_visibility_field()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $entrance = Cave::factory()->create();
        
        $tripData = [
            'name' => 'Test Trip',
            'start_time' => "2024-01-01 10:00:00",
            'end_time' => "2024-01-02 10:00:00",
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Test description',
            'participants' => [$participant->id],
            'visibility' => 'invalid_visibility',
        ];

        $this->actingAs($user);
        $response = $this->postJson('/api/trips', $tripData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visibility']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_public_trip_to_any_user()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips/' . $trip->id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_private_trip_to_participant()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'private']);
        $trip->participants()->attach($user);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips/' . $trip->id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hides_private_trip_from_non_participant()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'private']);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips/' . $trip->id);
        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_club_trip_to_club_member()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $club = \App\Models\Club::factory()->create();
        
        // Add both users to the same club
        $user->clubs()->attach($club, ['status' => 'approved']);
        $participant->clubs()->attach($club, ['status' => 'approved']);
        
        $trip = Trip::factory()->create(['visibility' => 'club']);
        $trip->participants()->attach($participant);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips/' . $trip->id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hides_club_trip_from_non_club_member()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $club = \App\Models\Club::factory()->create();
        
        // Only participant is in the club, not the viewing user
        $participant->clubs()->attach($club, ['status' => 'approved']);
        
        $trip = Trip::factory()->create(['visibility' => 'club']);
        $trip->participants()->attach($participant);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips/' . $trip->id);
        $response->assertStatus(404);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_trips_index_by_visibility()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $club = \App\Models\Club::factory()->create();
        
        $user->clubs()->attach($club, ['status' => 'approved']);
        $participant->clubs()->attach($club, ['status' => 'approved']);
        
        // Create trips with different visibility levels
        $publicTrip = Trip::factory()->create(['visibility' => 'public']);
        
        $privateTrip = Trip::factory()->create(['visibility' => 'private']);
        $privateTrip->participants()->attach($user);
        
        $clubTrip = Trip::factory()->create(['visibility' => 'club']);
        $clubTrip->participants()->attach($participant);
        
        $hiddenPrivateTrip = Trip::factory()->create(['visibility' => 'private']);
        $hiddenClubTrip = Trip::factory()->create(['visibility' => 'club']);
        
        $this->actingAs($user);
        $response = $this->getJson('/api/trips');
        $response->assertOk();
        
        $tripIds = collect($response->json('data'))->pluck('id')->toArray();
        
        // Should see public, private (participant), and club trips
        $this->assertContains($publicTrip->id, $tripIds);
        $this->assertContains($privateTrip->id, $tripIds);
        $this->assertContains($clubTrip->id, $tripIds);
        
        // Should not see private trip where not participant or club trip where not club member
        $this->assertNotContains($hiddenPrivateTrip->id, $tripIds);
        $this->assertNotContains($hiddenClubTrip->id, $tripIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stores_a_trip_with_heic_media()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $entrance = Cave::factory()->create();
        Event::fake([\App\Events\TripCreated::class]);
        
        // Create a base64-encoded HEIC image data
        $heicPath = __DIR__ . '/../../Fixtures/test.heic';
        $heicData = 'data:image/heic;base64,' . base64_encode(file_get_contents($heicPath));
        
        $tripData = [
            'name' => 'Test Trip with HEIC',
            'start_time' => "2024-01-01 10:00:00",
            'end_time' => "2024-01-02 10:00:00",
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Test description',
            'participants' => [$participant->id],
            'media' => [
                [
                    'data' => $heicData
                ]
            ]
        ];

        $this->actingAs($user);
        $response = $this->postJson('/api/trips', $tripData);
        $response->assertCreated()->assertJsonFragment(['name' => 'Test Trip with HEIC']);
        $this->assertDatabaseHas('trips', ['name' => 'Test Trip with HEIC']);
        $this->assertDatabaseHas('trip_user', ['user_id' => $participant->id]);
        $trip = Trip::where('name', 'Test Trip with HEIC')->first();
        $this->assertCount(1, $trip->media);
        Event::assertDispatched(\App\Events\TripCreated::class, function ($event) use ($trip) {
            return $event->trip->id === $trip->id;
        });
        Storage::disk('media')->assertExists($trip->media->first()->filename);
    }
}