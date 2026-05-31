<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TripPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_trips_performance()
    {
        // Seed data: 1000 trips
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        $user = User::factory()->create();

        // Create 1000 trips
        Trip::factory()->count(1000)->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
        ])->each(function ($trip) use ($user) {
            $trip->participants()->attach($user->id);
        });

        $this->actingAs($user);

        DB::enableQueryLog();
        $startTime = microtime(true);

        $response = $this->getJson('/api/trips');

        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        $executionTime = $endTime - $startTime;

        $response->assertStatus(200);

        // echo "\nTrip Performance Results (1000 Trips):\n";
        // echo 'Time: '.number_format($executionTime, 4)."s\n";
        // echo 'Queries: '.$queryCount."\n";

        if ($queryCount > 50) {
            $this->fail("Query count is too high: $queryCount (Target: < 50). Likely N+1 issues in TripResource or relating to Cave appends.");
        }
    }
}
