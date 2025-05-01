<?php
// File: tests/Feature/Api/TripTagTest.php

namespace Tests\Feature\Api;

use App\Models\Tag;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripTagTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Trip $trip;
    protected Tag $tripTypeTag;
    protected Tag $tackleTag;
    protected Tag $difficultyTag;
    protected Tag $caveTag; // A non-trip tag for testing

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->trip = Trip::factory()->create();

        // Create some assignable trip tags
        $this->tripTypeTag = Tag::factory()->create(['type' => 'trip', 'category' => 'type', 'tag' => 'Sport', 'assignable' => true]);
        $this->tackleTag = Tag::factory()->create(['type' => 'trip', 'category' => 'tackle', 'tag' => 'SRT', 'assignable' => true]);
        $this->difficultyTag = Tag::factory()->create(['type' => 'trip', 'category' => 'difficulty', 'tag' => 'Hard', 'assignable' => true]);

        // Create a non-assignable trip tag
        Tag::factory()->create(['type' => 'trip', 'category' => 'other', 'tag' => 'Not Assignable', 'assignable' => false]);

        // Create a non-trip tag
        $this->caveTag = Tag::factory()->create(['type' => 'cave', 'category' => 'region', 'tag' => 'Mendip', 'assignable' => true]);
    }

    /** @test */
    public function unauthenticated_user_cannot_list_trip_tags(): void
    {
        $response = $this->getJson(route('api.tags.trip.index'));
        $response->assertUnauthorized();
    }

    /** @test */
    public function authenticated_user_can_list_assignable_trip_tags(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
                         ->getJson(route('api.tags.trip.index'));

        $response->assertOk()
                 ->assertJsonStructure([
                     'type',
                     'tackle',
                     'difficulty',
                 ])
                 ->assertJsonCount(1, 'type')
                 ->assertJsonCount(1, 'tackle')
                 ->assertJsonCount(1, 'difficulty')
                 ->assertJsonFragment(['id' => $this->tripTypeTag->id, 'tag' => 'Sport'])
                 ->assertJsonFragment(['id' => $this->tackleTag->id, 'tag' => 'SRT'])
                 ->assertJsonFragment(['id' => $this->difficultyTag->id, 'tag' => 'Hard'])
                 ->assertJsonMissing(['tag' => 'Not Assignable']) // Ensure non-assignable are excluded
                 ->assertJsonMissing(['tag' => 'Mendip']); // Ensure non-trip tags are excluded
    }

    /** @test */
    public function unauthenticated_user_cannot_update_trip_tags(): void
    {
        $response = $this->putJson(route('api.trips.tags.update', $this->trip), [
            'tags' => [$this->tackleTag->id],
        ]);
        $response->assertUnauthorized();
    }

    /** @test */
    public function authenticated_user_can_update_trip_tags(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson(route('api.trips.tags.update', $this->trip), [
                             'tags' => [$this->tripTypeTag->id, $this->difficultyTag->id],
                         ]);

        $response->assertOk()
                 ->assertJsonCount(2)
                 ->assertJsonFragment(['id' => $this->tripTypeTag->id])
                 ->assertJsonFragment(['id' => $this->difficultyTag->id]);

        $this->assertDatabaseHas('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->tripTypeTag->id]);
        $this->assertDatabaseHas('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->difficultyTag->id]);
    }

    /** @test */
    public function updating_trip_tags_replaces_existing_trip_tags(): void
    {
        // Assign initial tags
        $this->trip->tags()->attach([$this->tackleTag->id, $this->caveTag->id]); // Attach a trip and a non-trip tag

        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson(route('api.trips.tags.update', $this->trip), [
                             'tags' => [$this->tripTypeTag->id, $this->difficultyTag->id],
                         ]);

        $response->assertOk()
                 ->assertJsonCount(2)
                 ->assertJsonFragment(['id' => $this->tripTypeTag->id])
                 ->assertJsonFragment(['id' => $this->difficultyTag->id]);

        $this->assertDatabaseHas('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->tripTypeTag->id]);
        $this->assertDatabaseHas('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->difficultyTag->id]);
        $this->assertDatabaseMissing('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->tackleTag->id]); // Old trip tag removed
        $this->assertDatabaseHas('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->caveTag->id]); // Non-trip tag should remain untouched
    }

     /** @test */
    public function updating_with_empty_array_removes_all_trip_tags(): void
    {
        $this->trip->tags()->attach([$this->tackleTag->id, $this->difficultyTag->id, $this->caveTag->id]);

        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson(route('api.trips.tags.update', $this->trip), [
                             'tags' => [], // Empty array
                         ]);

        $response->assertOk()->assertJsonCount(0); // Expecting empty array back

        $this->assertDatabaseMissing('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->tackleTag->id]);
        $this->assertDatabaseMissing('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->difficultyTag->id]);
        $this->assertDatabaseHas('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->caveTag->id]); // Non-trip tag remains
    }

    /** @test */
    public function update_validates_tags_input(): void
    {
        // Test missing 'tags' key
        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson(route('api.trips.tags.update', $this->trip), []);
        $response->assertJsonValidationErrors('tags');

        // Test 'tags' not an array
        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson(route('api.trips.tags.update', $this->trip), ['tags' => 'not-an-array']);
        $response->assertJsonValidationErrors('tags');

        // Test invalid tag ID (non-integer)
        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson(route('api.trips.tags.update', $this->trip), ['tags' => ['abc']]);
        $response->assertJsonValidationErrors('tags.0');

        // Test non-existent tag ID
        $nonExistentId = Tag::max('id') + 999;
        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson(route('api.trips.tags.update', $this->trip), ['tags' => [$nonExistentId]]);
        $response->assertJsonValidationErrors('tags.0');
    }

    /** @test */
    public function update_only_attaches_assignable_trip_tags(): void
    {
        $nonAssignableTripTag = Tag::where('type', 'trip')->where('assignable', false)->first();

        $response = $this->actingAs($this->user, 'sanctum')
                         ->putJson(route('api.trips.tags.update', $this->trip), [
                             'tags' => [
                                 $this->tackleTag->id, // Assignable trip tag
                                 $this->caveTag->id, // Non-trip tag
                                 $nonAssignableTripTag->id, // Non-assignable trip tag
                             ],
                         ]);

        $response->assertOk()
                 ->assertJsonCount(1) // Only the assignable trip tag should be returned
                 ->assertJsonFragment(['id' => $this->tackleTag->id])
                 ->assertJsonMissing(['id' => $this->caveTag->id])
                 ->assertJsonMissing(['id' => $nonAssignableTripTag->id]);

        $this->assertDatabaseHas('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->tackleTag->id]);
        $this->assertDatabaseMissing('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $this->caveTag->id]);
        $this->assertDatabaseMissing('tag_trip', ['trip_id' => $this->trip->id, 'tag_id' => $nonAssignableTripTag->id]);
    }
}
