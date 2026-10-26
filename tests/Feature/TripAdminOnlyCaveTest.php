<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * admin_only sites (e.g. coal mines) pass `exists:caves,id`, but a trip then
 * serialises its entrance/exit cave back to the caller. Only users who may view
 * those caves can log a trip to one.
 */
class TripAdminOnlyCaveTest extends TestCase
{
    use RefreshDatabase;

    private CaveSystem $system;

    private Cave $public;

    private Cave $mine;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([\App\Events\TripCreated::class]);

        $this->system = CaveSystem::factory()->create();
        $this->public = Cave::factory()->create(['cave_system_id' => $this->system->id]);
        $this->mine = Cave::factory()->create([
            'cave_system_id' => $this->system->id,
            'name' => 'Old Coal Mine',
            'visibility' => 'admin_only',
            'location_lat' => 53.5,
            'location_lng' => -1.9,
            'access_info' => 'Shaft behind the barn',
        ]);
    }

    private function tripData(int $entranceId, int $exitId, User $participant): array
    {
        return [
            'name' => 'Mine trip',
            'start_time' => '2024-01-01 10:00:00',
            'end_time' => '2024-01-01 14:00:00',
            'cave_system_id' => $this->system->id,
            'entrance_cave_id' => $entranceId,
            'exit_cave_id' => $exitId,
            'participants' => [$participant->id],
        ];
    }

    #[Test]
    public function an_ordinary_user_cannot_log_a_trip_to_an_admin_only_cave(): void
    {
        $user = User::factory()->withApprovedClub()->create();

        $this->actingAs($user)
            ->postJson('/api/trips', $this->tripData($this->mine->id, $this->public->id, $user))
            ->assertStatus(422)
            ->assertJsonValidationErrors('entrance_cave_id')
            ->assertJsonMissing(['name' => 'Old Coal Mine']);

        $this->actingAs($user)
            ->postJson('/api/trips', $this->tripData($this->public->id, $this->mine->id, $user))
            ->assertStatus(422)
            ->assertJsonValidationErrors('exit_cave_id');

        $this->assertDatabaseCount('trips', 0);
    }

    #[Test]
    public function an_ordinary_user_cannot_move_a_trip_to_an_admin_only_cave(): void
    {
        $user = User::factory()->withApprovedClub()->create();
        $trip = Trip::factory()->create([
            'cave_system_id' => $this->system->id,
            'entrance_cave_id' => $this->public->id,
            'exit_cave_id' => $this->public->id,
        ]);
        $trip->participants()->attach($user);

        $this->actingAs($user)
            ->putJson('/api/trips/'.$trip->short_id, ['entrance_cave_id' => $this->mine->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('entrance_cave_id');

        $this->assertSame($this->public->id, $trip->fresh()->entrance_cave_id);
    }

    #[Test]
    public function an_existing_trip_to_a_since_restricted_cave_can_still_be_resaved(): void
    {
        $user = User::factory()->withApprovedClub()->create();
        $trip = Trip::factory()->create([
            'cave_system_id' => $this->system->id,
            'entrance_cave_id' => $this->mine->id,
            'exit_cave_id' => $this->mine->id,
        ]);
        $trip->participants()->attach($user);

        $this->actingAs($user)
            ->putJson('/api/trips/'.$trip->short_id, [
                'name' => 'Renamed',
                'entrance_cave_id' => $this->mine->id,
                'exit_cave_id' => $this->mine->id,
            ])
            ->assertOk();
    }

    #[Test]
    public function a_data_admin_can_log_a_trip_to_an_admin_only_cave(): void
    {
        $admin = User::factory()->dataAdmin()->create();

        $this->actingAs($admin)
            ->postJson('/api/trips', $this->tripData($this->mine->id, $this->mine->id, $admin))
            ->assertCreated()
            ->assertJsonPath('data.entrance.name', 'Old Coal Mine');
    }

    #[Test]
    public function a_trip_to_an_admin_only_cave_hides_its_location_from_ordinary_viewers(): void
    {
        $trip = Trip::factory()->create([
            'visibility' => 'public',
            'cave_system_id' => $this->system->id,
            'entrance_cave_id' => $this->mine->id,
            'exit_cave_id' => $this->mine->id,
        ]);

        // Even with an approved club, which otherwise unlocks coordinates.
        $data = $this->actingAs(User::factory()->withApprovedClub()->create())
            ->getJson('/api/trips/'.$trip->short_id)->assertOk()->json('data');

        foreach (['entrance', 'exit'] as $cave) {
            $this->assertNull($data[$cave]['location_lat']);
            $this->assertNull($data[$cave]['location_lng']);
            $this->assertNull($data[$cave]['access_info']);
            $this->assertArrayNotHasKey('visibility', $data[$cave]);
        }
    }
}
