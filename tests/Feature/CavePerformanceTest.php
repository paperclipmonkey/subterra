<?php

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CavePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_caves_performance_baseline()
    {
        // Seed data: 50 systems, 5 caves each = 250 caves
        // Create 50 systems with 50 caves each = 2500 caves
        $systems = CaveSystem::factory()->count(50)->create();

        foreach ($systems as $system) {
            $caves = Cave::factory()->count(50)->create([
                'cave_system_id' => $system->id,
            ]);

            // Add trips to some caves to test the withExists logic
            foreach ($caves->take(5) as $cave) {
                \App\Models\Trip::factory()->count(2)->create([
                    'cave_system_id' => $system->id,
                    'entrance_cave_id' => $cave->id,
                    'exit_cave_id' => $cave->id,
                    'start_time' => now()->subDays(rand(1, 100)),
                ]);
            }
        }

        $user = User::factory()->create();
        $this->actingAs($user);

        // Debug: Check count
        // echo "Total Caves: " . Cave::count() . "\n";

        DB::enableQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/caves');

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // echo "\nPerformance Results (2500 Caves):\n";
        // echo 'Time: '.number_format($executionTime, 4)."s\n";
        // echo 'Queries: '.$queryCount."\n";
        // echo 'Size: '.number_format(strlen($response->getContent()) / 1024 / 1024, 2)." MB\n";

        if ($queryCount > 15) {
            $this->fail("Query count is too high: $queryCount (Target: < 15)");
        }

        if ($executionTime > 2.0) {
            $this->fail('Execution time is too high: '.number_format($executionTime, 4).'s (Target: < 1.0s, Allowable: 2.0s)');
        }
    }
}
