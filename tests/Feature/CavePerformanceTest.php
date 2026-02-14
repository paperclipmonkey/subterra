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
        $systems = CaveSystem::factory()->count(50)->create();

        foreach ($systems as $system) {
            Cave::factory()->count(5)->create([
                'cave_system_id' => $system->id,
            ]);
        }

        $user = User::factory()->create();

        DB::enableQueryLog();
        $startTime = microtime(true);

        $response = $this->actingAs($user)->getJson('/api/caves');

        $endTime = microtime(true);
        $queryLog = DB::getQueryLog();
        $queryCount = count($queryLog);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        echo "\nPerformance Baseline:\n";
        echo 'Time: '.number_format($executionTime, 4)."s\n";
        echo 'Queries: '.$queryCount."\n";

        if ($queryCount > 50) {
            $this->fail("Query count is too high: $queryCount (Target: < 50)");
        }

        // Identifying N+1: If queries > 10, it's likely an N+1 issue given we have 50 systems
        // With correct eager loading, it should be a fixed small number of queries
    }
}
