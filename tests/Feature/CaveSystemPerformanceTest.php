<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaveSystemPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_cave_systems_does_not_n_plus_one()
    {
        $regionTag = Tag::factory()->create(['category' => 'region', 'tag' => 'Mendip']);
        $systemTag = Tag::factory()->create(['category' => 'access']);

        $systems = CaveSystem::factory()->count(50)->create();

        foreach ($systems as $system) {
            $system->tags()->attach($systemTag);

            $caves = Cave::factory()->count(3)->create([
                'cave_system_id' => $system->id,
            ]);

            foreach ($caves as $cave) {
                $cave->tags()->attach($regionTag);
            }
        }

        $user = User::factory()->create();
        $this->actingAs($user);

        DB::enableQueryLog();

        $response = $this->getJson('/api/cave_systems');

        $queryCount = count(DB::getQueryLog());

        $response->assertStatus(200);
        $response->assertJsonCount(50, 'data');

        // The caving_region append must come from the eager loaded tags,
        // and the cave tags themselves must stay out of the payload.
        $firstCave = $response->json('data.0.caves.0');
        $this->assertSame('Mendip', $firstCave['caving_region']);
        $this->assertArrayNotHasKey('tags', $firstCave);

        // Query count must stay flat regardless of the number of systems/caves.
        if ($queryCount > 15) {
            $this->fail("Query count is too high: $queryCount (Target: < 15)");
        }
    }
}
