<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalloutToTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_callout_creates_private_trip_automatically()
    {
        $user = User::factory()->create();
        $friend = User::factory()->create();
        $cave = Cave::factory()->create();

        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'cave_id' => $cave->id,
            'status' => 'active',
            'description' => 'Morning trip',
            'created_at' => now()->subHours(2),
        ]);

        // Add a registered participant
        $callout->participants()->create([
            'user_id' => $friend->id,
            'name' => $friend->name,
        ]);

        // Add a guest participant
        $callout->participants()->create([
            'name' => 'Guest Person',
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel");

        $response->assertStatus(200);
        $response->assertJsonStructure(['trip_id']);

        $tripId = $response->json('trip_id');
        $this->assertNotNull($tripId);

        // Verify Trip was created
        $this->assertDatabaseHas('trips', [
            'short_id' => $tripId,
            'entrance_cave_id' => $cave->id,
            'visibility' => 'private',
            'description' => 'Morning trip',
        ]);

        $trip = Trip::where('short_id', $tripId)->first();

        // Verify participants: Creator + Friend (Guest is ignored as Trip only supports User participants)
        $this->assertCount(2, $trip->participants);
        $this->assertTrue($trip->participants->contains($user->id));
        $this->assertTrue($trip->participants->contains($friend->id));

        // Verify callout status is changed to cancelled
        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_callout_trip_does_not_tag_a_club_only_participant_the_creator_shares_no_club_with()
    {
        $user = User::factory()->create();
        $minor = User::factory()->create(['visibility_addable' => 'club']);
        $cave = Cave::factory()->create();

        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'cave_id' => $cave->id,
            'status' => 'active',
        ]);
        $callout->participants()->create(['user_id' => $minor->id, 'name' => $minor->name]);

        // Standing the callout down must still succeed; the participant is
        // simply left off the logged trip.
        $tripId = $this->actingAs($user)
            ->postJson("/api/callouts/{$callout->id}/cancel")
            ->assertOk()
            ->json('trip_id');

        $trip = Trip::where('short_id', $tripId)->first();
        $this->assertSame([$user->id], $trip->participants->pluck('id')->all());
    }
}
