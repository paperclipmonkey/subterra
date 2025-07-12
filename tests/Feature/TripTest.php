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
        $this->actingAs(User::factory()->create());
        Trip::factory()->count(2)->create();
        $response = $this->getJson('/api/trips');
        $response->assertOk()->assertJsonStructure(['data']);
        $this->assertResponseMatchesSchema($response, 'endpoints/trips-index');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_authenticated_users_trips()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create();
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
            'start_time' => now()->toDateTimeString(),
            'end_time' => now()->addDay()->toDateTimeString(),
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
        $trip = Trip::factory()->create();
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
            'start_time' => now()->toDateTimeString(),
            'end_time' => now()->addDay()->toDateTimeString(),
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
            'start_time' => now()->toDateTimeString(),
            'end_time' => now()->addDay()->toDateTimeString(),
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
}