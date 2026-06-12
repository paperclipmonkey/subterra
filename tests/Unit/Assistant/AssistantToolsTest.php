<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Tools;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Route;
use App\Models\Trip;
use App\Models\User;
use App\Services\Assistant\Tools\GetCaveDetailsTool;
use App\Services\Assistant\Tools\GetCaveSystemActivityTool;
use App\Services\Assistant\Tools\GetUpcomingPermitsTool;
use App\Services\Assistant\Tools\GetUserExperienceTool;
use App\Services\Assistant\Tools\SearchCavesTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssistantToolsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // GetUserExperienceTool
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_user_experience_returns_zero_stats_for_new_user(): void
    {
        $user = User::factory()->create();
        $tool = new GetUserExperienceTool();

        $result = $tool->handle([], $user);

        $this->assertSame(0, $result['total_trips']);
        $this->assertSame(0, $result['unique_systems_visited']);
        $this->assertCount(0, $result['clubs']);
        $this->assertCount(0, $result['medals']);
        $this->assertCount(0, $result['recent_trips']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_user_experience_counts_trips_correctly(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        // Create 3 trips for this user
        $trips = Trip::factory()->count(3)->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
        ]);
        foreach ($trips as $trip) {
            $trip->participants()->attach($user->id);
        }

        $tool = new GetUserExperienceTool();
        $result = $tool->handle([], $user);

        $this->assertSame(3, $result['total_trips']);
        $this->assertSame(1, $result['unique_systems_visited']);
        $this->assertCount(3, $result['recent_trips']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_user_experience_result_contains_expected_keys(): void
    {
        $user = User::factory()->create();
        $tool = new GetUserExperienceTool();

        $result = $tool->handle([], $user);

        $this->assertArrayHasKey('total_trips', $result);
        $this->assertArrayHasKey('unique_systems_visited', $result);
        $this->assertArrayHasKey('clubs', $result);
        $this->assertArrayHasKey('medals', $result);
        $this->assertArrayHasKey('recent_trips', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_user_experience_recent_trips_include_duration(): void
    {
        $user = User::factory()->create();
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        $trip = Trip::factory()->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
            'start_time' => now()->subHours(2),
            'end_time' => now()->subHour(),
        ]);
        $trip->participants()->attach($user->id);

        $tool = new GetUserExperienceTool();
        $result = $tool->handle([], $user);

        $recentTrip = $result['recent_trips'][0];
        $this->assertArrayHasKey('duration_minutes', $recentTrip);
        $this->assertGreaterThan(0, $recentTrip['duration_minutes']);
    }

    // =========================================================================
    // SearchCavesTool
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_returns_all_systems_when_no_filter_given(): void
    {
        CaveSystem::factory()->count(3)->create();
        $user = User::factory()->create();
        $tool = new SearchCavesTool();

        // include_obscure bypasses the curated filter so this test stays focused
        // on the "no filter = no exclusion" semantics rather than the curated default.
        $result = $tool->handle(['include_obscure' => true], $user);

        $this->assertArrayHasKey('cave_systems', $result);
        $this->assertCount(3, $result['cave_systems']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_filters_to_curated_only_by_default(): void
    {
        $curatedTag = \App\Models\Tag::firstOrCreate(
            ['tag' => 'Curated', 'type' => 'cave', 'category' => 'curated']
        );

        $curated = CaveSystem::factory()->create(['name' => 'Curated Cave']);
        $curated->tags()->attach($curatedTag->id);

        CaveSystem::factory()->create(['name' => 'Uncurated Sinkhole']);

        $user = User::factory()->create();
        $tool = new SearchCavesTool();

        $result = $tool->handle([], $user);

        $names = array_column($result['cave_systems'], 'name');
        $this->assertContains('Curated Cave', $names);
        $this->assertNotContains('Uncurated Sinkhole', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_filters_by_min_length(): void
    {
        CaveSystem::factory()->create(['name' => 'Short Cave', 'length' => 100]);
        CaveSystem::factory()->create(['name' => 'Long Cave', 'length' => 5000]);
        $user = User::factory()->create();
        $tool = new SearchCavesTool();

        $result = $tool->handle(['min_length' => 1000, 'include_obscure' => true], $user);

        $names = array_column($result['cave_systems'], 'name');
        $this->assertContains('Long Cave', $names);
        $this->assertNotContains('Short Cave', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_filters_by_max_length(): void
    {
        CaveSystem::factory()->create(['name' => 'Short Cave', 'length' => 100]);
        CaveSystem::factory()->create(['name' => 'Long Cave', 'length' => 5000]);
        $user = User::factory()->create();
        $tool = new SearchCavesTool();

        $result = $tool->handle(['max_length' => 500, 'include_obscure' => true], $user);

        $names = array_column($result['cave_systems'], 'name');
        $this->assertContains('Short Cave', $names);
        $this->assertNotContains('Long Cave', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_excludes_visited_systems_when_not_visited_flag_set(): void
    {
        $user = User::factory()->create();

        $visitedSystem = CaveSystem::factory()->create(['name' => 'Visited Cave']);
        $unvisitedSystem = CaveSystem::factory()->create(['name' => 'Unvisited Cave']);

        $cave = Cave::factory()->create(['cave_system_id' => $visitedSystem->id]);
        $trip = Trip::factory()->create([
            'cave_system_id' => $visitedSystem->id,
            'entrance_cave_id' => $cave->id,
        ]);
        $trip->participants()->attach($user->id);

        $tool = new SearchCavesTool();
        $result = $tool->handle(['not_visited' => true, 'include_obscure' => true], $user);

        $names = array_column($result['cave_systems'], 'name');
        $this->assertContains('Unvisited Cave', $names);
        $this->assertNotContains('Visited Cave', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_filters_by_region(): void
    {
        $yorkshireSystem = CaveSystem::factory()->create(['name' => 'Yorkshire Cave']);
        $yorkshireCave = Cave::factory()->create([
            'cave_system_id' => $yorkshireSystem->id,
            'location_name' => 'Yorkshire Dales',
        ]);

        $walesSystem = CaveSystem::factory()->create(['name' => 'Wales Cave']);
        $walesCave = Cave::factory()->create([
            'cave_system_id' => $walesSystem->id,
            'location_name' => 'South Wales',
        ]);

        $user = User::factory()->create();
        $tool = new SearchCavesTool();

        $result = $tool->handle(['region' => 'Yorkshire', 'include_obscure' => true], $user);

        $names = array_column($result['cave_systems'], 'name');
        $this->assertContains('Yorkshire Cave', $names);
        $this->assertNotContains('Wales Cave', $names);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_returns_at_most_ten_results(): void
    {
        CaveSystem::factory()->count(15)->create();
        $user = User::factory()->create();
        $tool = new SearchCavesTool();

        $result = $tool->handle(['include_obscure' => true], $user);

        $this->assertLessThanOrEqual(10, count($result['cave_systems']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_returns_grades_from_routes(): void
    {
        $system = CaveSystem::factory()->create(['name' => 'Graded Cave']);
        $entrance = Cave::factory()->create(['cave_system_id' => $system->id]);

        // Create a route with a numeric grade (integer column — must not be passed as empty string)
        Route::factory()->create([
            'cave_system_id' => $system->id,
            'entrance_id' => $entrance->id,
            'exit_id' => $entrance->id,
            'grade' => 3,
        ]);

        $user = User::factory()->create();
        $tool = new SearchCavesTool();
        $result = $tool->handle(['include_obscure' => true], $user);

        $found = collect($result['cave_systems'])->firstWhere('name', 'Graded Cave');
        $this->assertNotNull($found, 'Graded Cave should appear in results');
        $this->assertNotNull($found['grades'], 'grades field should be populated');
        $this->assertStringContainsString('3', (string) $found['grades']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function search_caves_does_not_error_when_no_systems_match_filters(): void
    {
        // When filters produce zero results, the grades query must not run a
        // "grade != ''" comparison against an integer column (PostgreSQL regression).
        CaveSystem::factory()->create(['name' => 'Short Cave', 'length' => 50]);
        $user = User::factory()->create();
        $tool = new SearchCavesTool();

        // min_length is larger than every cave — result set is empty
        $result = $tool->handle(['min_length' => 999999, 'include_obscure' => true], $user);

        $this->assertSame(0, $result['count']);
        $this->assertEmpty($result['cave_systems']);
    }

    // =========================================================================
    // GetCaveDetailsTool
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_cave_details_returns_error_for_unknown_system(): void
    {
        $user = User::factory()->create();
        $tool = new GetCaveDetailsTool();

        $result = $tool->handle(['cave_system_id' => 99999], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('99999', $result['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_cave_details_returns_system_data(): void
    {
        $system = CaveSystem::factory()->create([
            'name' => 'Ogof Ffynnon Ddu',
            'description' => 'The deepest cave in the UK.',
            'length' => 50000,
        ]);
        Cave::factory()->create(['cave_system_id' => $system->id, 'name' => 'OFD1 Entrance']);

        $user = User::factory()->create();
        $tool = new GetCaveDetailsTool();

        $result = $tool->handle(['cave_system_id' => $system->id], $user);

        $this->assertSame('Ogof Ffynnon Ddu', $result['name']);
        $this->assertArrayHasKey('entrances', $result);
        $this->assertCount(1, $result['entrances']);
        $this->assertSame('OFD1 Entrance', $result['entrances'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_cave_details_returns_zero_id_error(): void
    {
        $user = User::factory()->create();
        $tool = new GetCaveDetailsTool();

        $result = $tool->handle(['cave_system_id' => 0], $user);

        $this->assertArrayHasKey('error', $result);
    }

    // =========================================================================
    // GetUpcomingPermitsTool
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_upcoming_permits_returns_error_for_unknown_cave(): void
    {
        $user = User::factory()->create();
        $tool = new GetUpcomingPermitsTool();

        $result = $tool->handle(['cave_id' => 99999, 'date_from' => '2026-07-01'], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('99999', $result['error']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_upcoming_permits_reports_no_permit_required(): void
    {
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);
        $user = User::factory()->create();
        $tool = new GetUpcomingPermitsTool();

        $result = $tool->handle(['cave_id' => $cave->id, 'date_from' => '2026-07-01'], $user);

        $this->assertFalse($result['has_permit']);
        $this->assertSame($cave->name, $result['cave_name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_upcoming_permits_returns_permit_data_when_permit_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        $permit = \App\Models\Permit::factory()->create([
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $cave->permit()->attach($permit->id);

        $user = User::factory()->create();
        $tool = new GetUpcomingPermitsTool();

        $result = $tool->handle(['cave_id' => $cave->id, 'date_from' => '2026-07-01'], $user);

        $this->assertTrue($result['has_permit']);
        $this->assertSame($cave->name, $result['cave_name']);
        $this->assertArrayHasKey('bookings_by_date', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_upcoming_permits_defaults_date_to_30_days_after_date_from(): void
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        $permit = \App\Models\Permit::factory()->create([
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
        $cave->permit()->attach($permit->id);

        $user = User::factory()->create();
        $tool = new GetUpcomingPermitsTool();

        $result = $tool->handle(['cave_id' => $cave->id, 'date_from' => '2026-07-01'], $user);

        $this->assertSame('2026-07-01', $result['date_from']);
        $this->assertSame('2026-07-31', $result['date_to']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_upcoming_permits_ignores_inactive_permits(): void
    {
        $admin = User::factory()->admin()->create();
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        $permit = \App\Models\Permit::factory()->inactive()->create([
            'created_by' => $admin->id,
        ]);
        $cave->permit()->attach($permit->id);

        $user = User::factory()->create();
        $tool = new GetUpcomingPermitsTool();

        $result = $tool->handle(['cave_id' => $cave->id, 'date_from' => '2026-07-01'], $user);

        // An inactive permit should be treated as no permit
        $this->assertFalse($result['has_permit']);
    }

    // =========================================================================
    // GetCaveSystemActivityTool
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_cave_system_activity_returns_zeroes_for_system_with_no_trips(): void
    {
        $system = CaveSystem::factory()->create();
        $user = User::factory()->create();
        $tool = new GetCaveSystemActivityTool();

        $result = $tool->handle(['cave_system_id' => $system->id], $user);

        $this->assertSame(0, $result['trips_last_90_days']);
        $this->assertSame(0, $result['total_trips_logged']);
        $this->assertNull($result['last_trip_date']);
        $this->assertNull($result['most_popular_entrance']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_cave_system_activity_counts_trips_in_last_90_days(): void
    {
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);
        $user = User::factory()->create();

        // Two recent trips (within 90 days)
        Trip::factory()->count(2)->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
            'start_time' => now()->subDays(10),
            'end_time' => now()->subDays(10)->addHours(3),
        ]);

        // One old trip (over 90 days ago) — should NOT appear in the 90-day count
        Trip::factory()->create([
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
            'start_time' => now()->subDays(100),
            'end_time' => now()->subDays(100)->addHours(2),
        ]);

        $tool = new GetCaveSystemActivityTool();
        $result = $tool->handle(['cave_system_id' => $system->id], $user);

        $this->assertSame(2, $result['trips_last_90_days']);
        $this->assertSame(3, $result['total_trips_logged']);
        $this->assertNotNull($result['last_trip_date']);
        $this->assertSame($cave->name, $result['most_popular_entrance']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_cave_system_activity_result_contains_expected_keys(): void
    {
        $system = CaveSystem::factory()->create();
        $user = User::factory()->create();
        $tool = new GetCaveSystemActivityTool();

        $result = $tool->handle(['cave_system_id' => $system->id], $user);

        $this->assertArrayHasKey('cave_system_id', $result);
        $this->assertArrayHasKey('trips_last_90_days', $result);
        $this->assertArrayHasKey('total_trips_logged', $result);
        $this->assertArrayHasKey('last_trip_date', $result);
        $this->assertArrayHasKey('most_popular_entrance', $result);
        $this->assertArrayHasKey('avg_trip_duration_mins', $result);
    }

    // =========================================================================
    // GetMedalProgressTool
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_medal_progress_splits_earned_and_unearned_with_progress(): void
    {
        $user = User::factory()->create();
        $firstTrip = \App\Models\Medal::create(['name' => 'First Trip', 'description' => 'Awarded for your first trip']);
        \App\Models\Medal::create(['name' => 'Explorer', 'description' => 'Visit 5 different caves']);
        \App\Models\Medal::create(['name' => 'Veteran', 'description' => 'Participate in 20 trips']);

        for ($i = 0; $i < 3; ++$i) {
            $cave = Cave::factory()->create();
            $trip = Trip::factory()->create(['entrance_cave_id' => $cave->id]);
            $trip->participants()->attach($user->id);
        }
        $user->medals()->attach($firstTrip->id, ['awarded_at' => now()]);

        $tool = $this->app->make(\App\Services\Assistant\Tools\GetMedalProgressTool::class);
        $result = $tool->handle([], $user);

        $this->assertSame('1 of 3 medals earned.', $result['summary']);
        $this->assertSame('First Trip', $result['earned'][0]['name']);
        $this->assertNotNull($result['earned'][0]['awarded_at']);

        // Unearned sorted nearest-first: Explorer (3/5) before Veteran (3/20)
        $this->assertSame('Explorer', $result['unearned'][0]['name']);
        $this->assertSame(['current' => 3, 'target' => 5], $result['unearned'][0]['progress']);
        $this->assertSame('Veteran', $result['unearned'][1]['name']);
        $this->assertSame(['current' => 3, 'target' => 20], $result['unearned'][1]['progress']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function get_medal_progress_result_contains_expected_keys(): void
    {
        $user = User::factory()->create();
        $tool = $this->app->make(\App\Services\Assistant\Tools\GetMedalProgressTool::class);

        $result = $tool->handle([], $user);

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('earned', $result);
        $this->assertArrayHasKey('unearned', $result);
        $this->assertArrayHasKey('note', $result);
    }
}
