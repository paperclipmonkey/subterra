<?php

namespace Tests\Feature;

use App\Events\TripCreated;
use App\Events\TripParticipantTagged;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SqlBottleneckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fix #2: UserController::index should use SQL GROUP BY instead of loading
     * all trips into memory. With 50 trips and 5 participants each, the query
     * count should stay constant (not scale with trip/participant count).
     */
    public function test_user_search_query_count_with_many_trips(): void
    {
        $currentUser = User::factory()->create();
        $otherUsers = User::factory()->count(5)->create(['visibility_addable' => 'public']);

        // Create a cave system and cave for trips
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        // Create 50 trips, each with the current user and 3 other participants
        for ($i = 0; $i < 50; ++$i) {
            $trip = Trip::factory()->create([
                'cave_system_id' => $system->id,
                'entrance_cave_id' => $cave->id,
                'exit_cave_id' => $cave->id,
                'visibility' => 'public',
            ]);
            $trip->participants()->attach($currentUser->id);
            // Attach 3 random other users
            $trip->participants()->attach(
                $otherUsers->random(min(3, $otherUsers->count()))->pluck('id')->toArray()
            );
        }

        $this->actingAs($currentUser);

        DB::enableQueryLog();

        $response = $this->getJson('/api/users?search='.$otherUsers->first()->name);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertOk();

        // Before fix: would be 50+ queries (1 per trip + 1 per participant batch)
        // After fix: should be < 15 queries (single GROUP BY + user search)
        $this->assertLessThan(
            15,
            $queryCount,
            "User search query count is too high: $queryCount. Expected < 15 with SQL GROUP BY. Likely regression to N+1 trip loading."
        );
    }

    /**
     * Fix #3: ClubDataController::activityHeatmap should not duplicate the
     * approved member IDs query. The cached ID list should be reused.
     */
    public function test_club_heatmap_does_not_duplicate_member_query(): void
    {
        $club = Club::factory()->create();
        $members = User::factory()->count(10)->create();

        foreach ($members as $member) {
            $club->users()->attach($member, ['status' => 'approved']);
        }

        // Create a cave system and cave for trips
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        // Create 50 trips with club members as participants
        for ($i = 0; $i < 50; ++$i) {
            $trip = Trip::factory()->create([
                'cave_system_id' => $system->id,
                'entrance_cave_id' => $cave->id,
                'exit_cave_id' => $cave->id,
                'start_time' => now()->subDays(rand(1, 300)),
                'end_time' => now()->subDays(rand(1, 300))->addHours(3),
            ]);
            $trip->participants()->attach(
                $members->random(min(3, $members->count()))->pluck('id')->toArray()
            );
        }

        $this->actingAs($members->first(), 'sanctum');

        DB::enableQueryLog();

        $response = $this->getJson("/api/clubs/{$club->slug}/activity-heatmap");

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertOk();

        // Count queries that fetch approved users from club_user pivot
        $approvedUserQueries = collect($queries)->filter(function ($query) {
            return str_contains($query['query'], 'club_user')
                && str_contains($query['query'], 'approved');
        })->count();

        // Before fix: 2 queries to club_user for approved users
        // After fix: should only be 1 query (result is cached and reused)
        $this->assertLessThanOrEqual(
            1,
            $approvedUserQueries,
            "Approved member IDs query is duplicated ($approvedUserQueries times). Should query club_user for approved members only once."
        );

        // Overall query count check
        $this->assertLessThan(
            10,
            $queryCount,
            "Heatmap query count is too high: $queryCount. Expected < 10."
        );
    }

    /**
     * CaveController::show should cap the number of trips loaded
     * to prevent unbounded eager-loading as trip count grows.
     */
    public function test_cave_show_limits_trips_loaded(): void
    {
        $system = CaveSystem::factory()->create();
        $cave = Cave::factory()->create(['cave_system_id' => $system->id]);

        // Create 100 trips for this cave system
        for ($i = 0; $i < 100; ++$i) {
            Trip::factory()->create([
                'cave_system_id' => $system->id,
                'entrance_cave_id' => $cave->id,
                'exit_cave_id' => $cave->id,
                'start_time' => now()->subDays($i),
                'end_time' => now()->subDays($i)->addHours(3),
            ]);
        }

        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson("/api/caves/{$cave->slug}");
        $response->assertOk();

        // Verify trips are capped at 25
        $trips = $response->json('data.trips');
        $this->assertLessThanOrEqual(
            25,
            count($trips),
            'Cave show should cap trips at 25, got '.count($trips).'. Unbounded trip loading will cause performance issues.'
        );

        // Verify trips are in descending date order (most recent first)
        if (count($trips) >= 2) {
            $firstTripStart = $trips[0]['start_time'] ?? null;
            $lastTripStart = $trips[count($trips) - 1]['start_time'] ?? null;
            if ($firstTripStart && $lastTripStart) {
                $this->assertGreaterThanOrEqual(
                    $lastTripStart,
                    $firstTripStart,
                    'Trips should be ordered by start_time descending (most recent first).'
                );
            }
        }
    }

    /**
     * TripController::store should batch-resolve participant IDs
     * with a single query instead of N individual queries.
     */
    public function test_trip_store_query_count_with_many_participants(): void
    {
        Storage::fake('media');
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        $creator = User::factory()->create();
        $participants = User::factory()->count(8)->create();
        $cave = Cave::factory()->create();

        $tripData = [
            'name' => 'Performance Test Trip',
            'start_time' => '2024-06-01 10:00:00',
            'end_time' => '2024-06-01 18:00:00',
            'cave_system_id' => $cave->cave_system_id,
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'description' => 'Testing participant batch resolution',
            'participants' => $participants->pluck('id')->toArray(),
            'visibility' => 'public',
        ];

        $this->actingAs($creator);

        DB::enableQueryLog();

        $response = $this->postJson('/api/trips', $tripData);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertCreated();

        // Overall query count should be reasonable
        // Before fix: would scale linearly with participants (2N+1 individual user queries)
        // After fix: constant regardless of participant count (batch whereIn queries)
        // Budget accounts for: auth, validation, trip create, participant sync, events, media, resource
        $this->assertLessThan(
            45,
            $queryCount,
            "Trip store query count is too high: $queryCount with 8 participants. Expected < 45. Likely N+1 in participant resolution."
        );

        // Verify all participants were attached
        $trip = Trip::where('name', 'Performance Test Trip')->first();
        $this->assertNotNull($trip);
        $this->assertEquals(
            $participants->count(),
            $trip->participants()->count(),
            'All participants should be attached to the trip.'
        );
    }

    /**
     * TripController::update should also batch-resolve participant IDs.
     */
    public function test_trip_update_query_count_with_many_participants(): void
    {
        Storage::fake('media');
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        $creator = User::factory()->create();
        $originalParticipants = User::factory()->count(3)->create();
        $newParticipants = User::factory()->count(8)->create();
        $cave = Cave::factory()->create();

        $trip = Trip::factory()->create([
            'cave_system_id' => $cave->cave_system_id,
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'visibility' => 'public',
        ]);
        $trip->participants()->attach($creator->id);
        $trip->participants()->attach($originalParticipants->pluck('id')->toArray());

        $updateData = [
            'name' => 'Updated Performance Trip',
            'start_time' => '2024-06-01 10:00:00',
            'end_time' => '2024-06-01 18:00:00',
            'cave_system_id' => $cave->cave_system_id,
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'description' => 'Updated description',
            'participants' => $newParticipants->pluck('id')->toArray(),
            'visibility' => 'public',
            'existing_media' => [],
        ];

        $this->actingAs($creator);

        DB::enableQueryLog();

        $response = $this->putJson("/api/trips/{$trip->short_id}", $updateData);

        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        $response->assertOk();

        // Overall query count should be reasonable
        // Before fix: would scale linearly with participants (N individual user queries)
        // After fix: constant regardless of participant count (single whereIn query)
        // Budget accounts for: auth, validation, media cleanup, trip update, participant sync, resource serialization
        $this->assertLessThan(
            55,
            $queryCount,
            "Trip update query count is too high: $queryCount with 8 participants. Expected < 55. Likely N+1 in participant resolution."
        );

        // Verify participants were synced
        $trip->refresh();
        $this->assertEquals(
            $newParticipants->count(),
            $trip->participants()->count(),
            'All new participants should be synced to the trip.'
        );
    }
}
