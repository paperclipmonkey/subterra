<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Medal;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MedalProgressTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function guests_cannot_view_medal_progress()
    {
        $this->getJson('/api/me/medals')->assertUnauthorized();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function lists_all_medals_with_earned_state_and_progress()
    {
        $user = User::factory()->create();
        $explorer = Medal::create(['name' => 'Explorer', 'description' => 'Visit 5 different caves']);
        $veteran = Medal::create(['name' => 'Veteran', 'description' => 'Participate in 20 trips']);

        // 3 trips to 3 different caves, then award Explorer manually
        for ($i = 0; $i < 3; ++$i) {
            $cave = Cave::factory()->create();
            $trip = Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user);
        }
        $user->medals()->attach($explorer->id, ['awarded_at' => Carbon::now()]);

        $response = $this->actingAs($user)->getJson('/api/me/medals');
        $response->assertOk();

        $medals = collect($response->json('data'))->keyBy('name');
        $this->assertCount(2, $medals);

        $this->assertTrue($medals['Explorer']['earned']);
        $this->assertNotNull($medals['Explorer']['awarded_at']);

        $this->assertFalse($medals['Veteran']['earned']);
        $this->assertNull($medals['Veteran']['awarded_at']);
        $this->assertSame(['current' => 3, 'target' => 20], $medals['Veteran']['progress']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function progress_is_capped_at_the_target()
    {
        $user = User::factory()->create();
        Medal::create(['name' => 'First Trip', 'description' => 'Awarded for your first trip!']);

        for ($i = 0; $i < 2; ++$i) {
            $trip = Trip::factory()->create();
            $trip->participants()->attach($user);
        }

        $response = $this->actingAs($user)->getJson('/api/me/medals');

        $this->assertSame(
            ['current' => 1, 'target' => 1],
            collect($response->json('data'))->firstWhere('name', 'First Trip')['progress']
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function multi_part_medals_report_parts_completed()
    {
        $user = User::factory()->create();
        Medal::create(['name' => 'Ham pasta aficionado', 'description' => 'Do Hunters\' Hole and Hunters\' Lodge Inn Sink']);

        $huntersHole = Cave::factory()->create(['name' => 'Hunters\' Hole']);
        $trip = Trip::factory()->create(['entrance_cave_id' => $huntersHole->id]);
        $trip->participants()->attach($user);

        $response = $this->actingAs($user)->getJson('/api/me/medals');

        $this->assertSame(
            ['current' => 1, 'target' => 2],
            collect($response->json('data'))->firstWhere('name', 'Ham pasta aficionado')['progress']
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function completionist_tracks_the_closest_collection()
    {
        $user = User::factory()->create();
        Medal::create(['name' => 'Completionist', 'description' => 'Complete any cave collection']);

        $caves = Cave::factory()->count(3)->create();
        $collection = \App\Models\Collection::factory()->create();
        $collection->caves()->attach($caves->pluck('id'));

        $trip = Trip::factory()->create(['entrance_cave_id' => $caves[0]->id]);
        $trip->participants()->attach($user);

        $response = $this->actingAs($user)->getJson('/api/me/medals');

        $this->assertSame(
            ['current' => 1, 'target' => 3],
            collect($response->json('data'))->firstWhere('name', 'Completionist')['progress']
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function medals_with_unknown_criteria_have_null_progress()
    {
        $user = User::factory()->create();
        Medal::create(['name' => 'Mystery Medal', 'description' => 'Awarded by hand']);

        $response = $this->actingAs($user)->getJson('/api/me/medals');

        $this->assertNull(collect($response->json('data'))->firstWhere('name', 'Mystery Medal')['progress']);
    }
}
