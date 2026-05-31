<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JsonSchemaValidator;
use Tests\TestCase;

class TripTest extends TestCase
{
    use RefreshDatabase;
    use JsonSchemaValidator;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');
        \Illuminate\Support\Facades\Bus::fake([\App\Jobs\ProcessImageCloudJob::class]);
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
        $this->assertContains($publicTrip->short_id, $tripIds);
        $this->assertNotContains($privateTrip->short_id, $tripIds);
        $this->assertResponseMatchesSchema($response, 'endpoints/trips-index');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_includes_entrance_coordinates_in_trip_summary()
    {
        $user = User::factory()->create();
        $entrance = Cave::factory()->create([
            'location_lat' => 54.1234,
            'location_lng' => -2.5678,
        ]);
        $trip = Trip::factory()->create([
            'visibility' => 'public',
            'entrance_cave_id' => $entrance->id,
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/trips');

        $response->assertOk();
        $tripData = collect($response->json('data'))->firstWhere('id', $trip->short_id);
        $this->assertNotNull($tripData);
        $this->assertEquals(54.1234, $tripData['entrance']['location_lat']);
        $this->assertEquals(-2.5678, $tripData['entrance']['location_lng']);
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
        $response->assertOk()->assertJsonFragment(['id' => $trip->short_id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_downloads_my_trips_csv()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);
        $trip->participants()->attach($user);

        $this->actingAs($user);
        $response = $this->get('/api/me/trips/download');
        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
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
        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

        $tripData = [
            'name' => 'Test Trip',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Test description',
            'participants' => [$participant->id],
            'media' => [
                [
                    'data' => $imageFile,
                ],
            ],
        ];

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips', $tripData);
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
        $response = $this->getJson('/api/trips/'.$trip->short_id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->short_id]);
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

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

        $media = [
            [
                'data' => $imageFile,
            ],
        ];

        $updateData = [
            'name' => 'Updated Trip',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Updated description',
            'visibility' => 'public',
            'participants' => [$participant->id],
            'media' => $media,
            'existing_media' => [],
            '_method' => 'PUT',
        ];

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips/'.$trip->short_id, $updateData);
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
        $response = $this->putJson('/api/trips/'.$trip->short_id, ['name' => 'Updated Trip']);
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

        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test.png');

        $media = [
            [
                'data' => $imageFile,
            ],
        ];

        $updateData = [
            'name' => 'Updated Trip',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Updated description',
            'visibility' => 'public',
            'participants' => [$participant->id],
            'media' => $media,
            'existing_media' => [],
            '_method' => 'PUT',
        ];

        $this->actingAs(User::factory()->admin()->create());
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips/'.$trip->short_id, $updateData);
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
        $response = $this->deleteJson('/api/trips/'.$trip->short_id);
        $response->assertOk()->assertJsonFragment(['message' => 'Trip deleted successfully']);
        $this->assertDatabaseMissing('trips', ['id' => $trip->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_doesnt_delete_a_trip_not_participant()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
        $this->actingAs($user);
        $response = $this->deleteJson('/api/trips/'.$trip->short_id);
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
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
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
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
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
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
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
        $response = $this->getJson('/api/trips/'.$trip->short_id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->short_id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_public_trip_to_unauthenticated_user()
    {
        $trip = Trip::factory()->create(['visibility' => 'public']);

        $response = $this->getJson('/api/trips/'.$trip->short_id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->short_id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_private_trip_to_participant()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'private']);
        $trip->participants()->attach($user);

        $this->actingAs($user);
        $response = $this->getJson('/api/trips/'.$trip->short_id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->short_id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hides_private_trip_from_non_participant()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'private']);

        $this->actingAs($user);
        $response = $this->getJson('/api/trips/'.$trip->short_id);
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
        $response = $this->getJson('/api/trips/'.$trip->short_id);
        $response->assertOk()->assertJsonFragment(['id' => $trip->short_id]);
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
        $response = $this->getJson('/api/trips/'.$trip->short_id);
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
        $this->assertContains($publicTrip->short_id, $tripIds);
        $this->assertContains($privateTrip->short_id, $tripIds);
        $this->assertContains($clubTrip->short_id, $tripIds);

        // Should not see private trip where not participant or club trip where not club member
        $this->assertNotContains($hiddenPrivateTrip->short_id, $tripIds);
        $this->assertNotContains($hiddenClubTrip->short_id, $tripIds);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_heic_media()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();
        $entrance = Cave::factory()->create();
        Event::fake([\App\Events\TripCreated::class]);

        $pngContent = file_get_contents(__DIR__.'/../../Fixtures/test.png');
        $heicFile = \Illuminate\Http\UploadedFile::fake()->createWithContent('test.heic', $pngContent)->mimeType('image/heic');

        $tripData = [
            'name' => 'Test Trip with HEIC',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'description' => 'Test description',
            'participants' => [$participant->id],
            'visibility' => 'public',
            'media' => [
                [
                    'data' => $heicFile,
                    'taken_at' => '2024-01-01 12:00:00',
                    'photographer' => 'John Doe',
                    'copyright' => '© 2024 John Doe',
                ],
            ],
        ];

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])->post('/api/trips', $tripData);

        // HEIC is not a supported format — should be rejected at validation
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['media.0.data']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function media_resource_includes_metadata()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);
        $trip->participants()->attach($user);

        // Create media with metadata
        $trip->media()->create([
            'filename' => 'test.webp',
            'taken_at' => '2024-01-01 12:00:00',
            'photographer' => 'Jane Smith',
            'copyright' => '© 2024 Jane Smith',
        ]);

        $this->actingAs($user);
        $response = $this->getJson('/api/trips/'.$trip->short_id);

        $response->assertOk()
            ->assertJsonPath('data.media.0.taken_at', '2024-01-01T12:00:00.000000Z')
            ->assertJsonPath('data.media.0.photographer', 'Jane Smith')
            ->assertJsonPath('data.media.0.copyright', '© 2024 Jane Smith');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_filters_trips_index_by_user_id()
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $viewer = User::factory()->create();

        $tripA = Trip::factory()->create(['visibility' => 'public', 'start_time' => now()]);
        $tripA->participants()->attach($userA);

        $tripB = Trip::factory()->create(['visibility' => 'public', 'start_time' => now()->subDay()]);
        $tripB->participants()->attach($userB);

        $this->actingAs($viewer);

        // Filter by User A
        $responseA = $this->getJson("/api/trips?user_id={$userA->id}");
        $responseA->assertOk();
        $tripIdsA = collect($responseA->json('data'))->pluck('id')->toArray();
        $this->assertContains($tripA->short_id, $tripIdsA);
        $this->assertNotContains($tripB->short_id, $tripIdsA);

        // Filter by User B
        $responseB = $this->getJson("/api/trips?user_id={$userB->id}");
        $responseB->assertOk();
        $tripIdsB = collect($responseB->json('data'))->pluck('id')->toArray();
        $this->assertContains($tripB->short_id, $tripIdsB);
        $this->assertNotContains($tripA->short_id, $tripIdsB);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prevents_public_trips_for_closed_caves()
    {
        $user = User::factory()->create();
        $participant = User::factory()->create();

        // Create closed tag
        $closedTag = \App\Models\Tag::factory()->create(['tag' => 'Closed', 'type' => 'cave', 'category' => 'access']);

        // Create cave with closed tag
        $closedCave = Cave::factory()->create();
        $closedCave->tags()->attach($closedTag);

        $tripData = [
            'name' => 'Should Fail',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $closedCave->cave_system_id,
            'entrance_cave_id' => $closedCave->id,
            'exit_cave_id' => $closedCave->id,
            'description' => 'Test description',
            'participants' => [$participant->id],
            'visibility' => 'public',
        ];

        $this->actingAs($user);

        // Attempt public trip
        $response = $this->postJson('/api/trips', $tripData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['visibility']);

        // Attempt private trip (should succeed)
        $tripData['visibility'] = 'private';
        $tripData['name'] = 'Should Succeed';
        $response = $this->postJson('/api/trips', $tripData);
        $response->assertCreated();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_rejects_files_that_are_too_large()
    {
        $user = User::factory()->create();
        $entrance = Cave::factory()->create();
        // Create a 600MB fake file (validation limit is 512000 KB)
        $largeFile = \Illuminate\Http\UploadedFile::fake()->create('huge.mp4', 600000);

        $tripData = [
            'name' => 'Large Media Trip',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$user->id],
            'media' => [
                [
                    'data' => $largeFile,
                ],
            ],
        ];

        $this->actingAs($user);
        $response = $this->post('/api/trips', $tripData);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['media.0.data']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_stores_media_even_without_string_metadata()
    {
        $user = User::factory()->create();
        $entrance = Cave::factory()->create();
        $imageFile = \Illuminate\Http\UploadedFile::fake()->image('test_no_meta.png');

        $tripData = [
            'name' => 'Trip No Meta',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$user->id],
            'media' => [
                [
                    'data' => $imageFile,
                    // No title, copyright, photographer explicitly provided
                ],
            ],
        ];

        $this->actingAs($user);
        $response = $this->post('/api/trips', $tripData);
        $response->assertCreated();

        $trip = Trip::where('name', 'Trip No Meta')->first();
        $this->assertCount(1, $trip->media);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_updates_existing_media_metadata()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);
        $trip->participants()->attach($user);

        $media = $trip->media()->create([
            'filename' => 'test.jpg',
            'title' => 'Old Title',
            'copyright' => 'Old Copyright',
            'photographer' => 'Old Photographer',
        ]);

        $updateData = [
            'name' => 'Updated Trip',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $trip->cave_system_id,
            'entrance_cave_id' => $trip->entrance_cave_id,
            'exit_cave_id' => $trip->exit_cave_id,
            'existing_media' => [
                [
                    'id' => $media->id,
                    'title' => 'New Title',
                    'copyright' => 'New Copyright',
                    'photographer' => 'New Photographer',
                ],
            ],
            '_method' => 'PUT',
        ];

        $this->actingAs($user);
        $response = $this->withHeaders(['Accept' => 'application/json'])
                         ->post('/api/trips/'.$trip->short_id, $updateData);

        $response->assertOk();

        $this->assertDatabaseHas('trip_media', [
            'id' => $media->id,
            'title' => 'New Title',
            'copyright' => 'New Copyright',
            'photographer' => 'New Photographer',
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_dispatches_event_when_new_participant_added_on_update()
    {
        $user = User::factory()->create();
        $existingParticipant = User::factory()->create();
        $newParticipant = User::factory()->create();

        $entrance = Cave::factory()->create();
        $trip = Trip::factory()->create(['entrance_cave_id' => $entrance->id]);
        $trip->participants()->attach([$user->id, $existingParticipant->id]);

        Event::fake([\App\Events\TripParticipantTagged::class]);

        $updateData = [
            'name' => 'Updated Trip',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-02 10:00:00',
            'cave_system_id' => $entrance->cave_system_id,
            'entrance_cave_id' => $entrance->id,
            'exit_cave_id' => $entrance->id,
            'participants' => [$existingParticipant->id, $newParticipant->id],
            '_method' => 'PUT',
        ];

        $this->actingAs($user);
        $this->withHeaders(['Accept' => 'application/json'])
             ->post('/api/trips/'.$trip->short_id, $updateData)
             ->assertOk();

        // The event should be dispatched for the new participant
        Event::assertDispatched(\App\Events\TripParticipantTagged::class, function ($event) use ($newParticipant) {
            return $event->user->id === $newParticipant->id;
        });

        // The event should NOT be dispatched for the existing participant
        Event::assertNotDispatched(\App\Events\TripParticipantTagged::class, function ($event) use ($existingParticipant) {
            return $event->user->id === $existingParticipant->id;
        });
    }
}
