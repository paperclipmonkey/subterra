<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TripCreated;
use App\Events\TripParticipantTagged;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use App\Services\Assistant\Tools\CreateTripReportTool;
use App\Services\Assistant\Tools\SearchUsersTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssistantTripCreationTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // SearchUsersTool
    // =========================================================================

    #[Test]
    public function search_users_requires_at_least_two_characters(): void
    {
        $user = User::factory()->create();
        $tool = new SearchUsersTool();

        $result = $tool->handle(['query' => 'A'], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertEmpty($result['users']);
    }

    #[Test]
    public function search_users_returns_visibility_addable_users(): void
    {
        $user = User::factory()->create();
        $visible = User::factory()->create(['name' => 'Alice Blackshaw', 'visibility_addable' => true]);
        $hidden = User::factory()->create(['name' => 'Alice Hidden', 'visibility_addable' => false]);

        $tool = new SearchUsersTool();
        $result = $tool->handle(['query' => 'Alice'], $user);

        $ids = array_column($result['users'], 'id');
        $this->assertContains($visible->id, $ids);
        $this->assertNotContains($hidden->id, $ids);
    }

    #[Test]
    public function search_users_returns_club_members_even_if_not_visibility_addable(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create();

        // Both users are in the same club
        \Illuminate\Support\Facades\DB::table('club_user')->insert([
            ['user_id' => $user->id, 'club_id' => $club->id, 'status' => 'approved'],
        ]);

        $clubMate = User::factory()->create(['name' => 'Bob Caveman', 'visibility_addable' => false]);
        \Illuminate\Support\Facades\DB::table('club_user')->insert([
            ['user_id' => $clubMate->id, 'club_id' => $club->id, 'status' => 'approved'],
        ]);

        $tool = new SearchUsersTool();
        $result = $tool->handle(['query' => 'Bob'], $user);

        $ids = array_column($result['users'], 'id');
        $this->assertContains($clubMate->id, $ids);
    }

    #[Test]
    public function search_users_does_not_return_current_user(): void
    {
        $user = User::factory()->create(['name' => 'Charlie Caver', 'visibility_addable' => true]);

        $tool = new SearchUsersTool();
        $result = $tool->handle(['query' => 'Charlie'], $user);

        $ids = array_column($result['users'], 'id');
        $this->assertNotContains($user->id, $ids);
    }

    #[Test]
    public function search_users_includes_club_names_for_disambiguation(): void
    {
        $user = User::factory()->create();
        $club = Club::factory()->create(['name' => 'Mendip Cave Club', 'is_active' => true]);
        $target = User::factory()->create(['name' => 'Dave Digger', 'visibility_addable' => true]);

        \Illuminate\Support\Facades\DB::table('club_user')->insert([
            ['user_id' => $target->id, 'club_id' => $club->id, 'status' => 'approved'],
        ]);

        $tool = new SearchUsersTool();
        $result = $tool->handle(['query' => 'Dave'], $user);

        $found = collect($result['users'])->firstWhere('id', $target->id);
        $this->assertNotNull($found);
        $this->assertContains('Mendip Cave Club', $found['clubs']);
    }

    #[Test]
    public function search_users_returns_empty_when_no_matches(): void
    {
        $user = User::factory()->create();

        $tool = new SearchUsersTool();
        $result = $tool->handle(['query' => 'zzz-no-match'], $user);

        $this->assertEmpty($result['users']);
        $this->assertArrayHasKey('note', $result);
    }

    // =========================================================================
    // CreateTripReportTool
    // =========================================================================

    private function makeSystemAndCave(string $systemSlug = 'gaping-gill', string $caveSlug = 'main-shaft'): array
    {
        $system = CaveSystem::factory()->create(['slug' => $systemSlug, 'name' => 'Gaping Gill']);
        $cave = Cave::factory()->create([
            'slug' => $caveSlug,
            'name' => 'Main Shaft',
            'cave_system_id' => $system->id,
        ]);

        return [$system, $cave];
    }

    #[Test]
    public function create_trip_report_creates_trip_in_database(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $result = $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Test Trip',
            'description' => 'A great trip.',
            'date' => '2024-06-15',
        ], $user);

        $this->assertTrue($result['success'] ?? false);
        $this->assertDatabaseHas('trips', [
            'name' => 'Test Trip',
            'cave_system_id' => $system->id,
            'entrance_cave_id' => $cave->id,
        ]);
    }

    #[Test]
    public function create_trip_report_always_includes_current_user_as_participant(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $result = $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Solo Trip',
            'description' => 'Solo cave.',
            'date' => '2024-06-15',
        ], $user);

        $trip = Trip::where('name', 'Solo Trip')->first();
        $this->assertNotNull($trip);
        $this->assertTrue($trip->participants->contains($user->id));
    }

    #[Test]
    public function create_trip_report_tags_additional_participants(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $companion = User::factory()->create();
        $tool = new CreateTripReportTool();

        $result = $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Group Trip',
            'description' => 'A group trip.',
            'date' => '2024-06-15',
            'participant_ids' => [$companion->id],
        ], $user);

        $trip = Trip::where('name', 'Group Trip')->first();
        $this->assertNotNull($trip);
        $this->assertTrue($trip->participants->contains($companion->id));
    }

    #[Test]
    public function additional_participants_not_in_system_are_appended_to_description(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Guest Trip',
            'description' => 'Trip with guests.',
            'date' => '2024-06-15',
            'additional_participants' => ['Jane Smith', 'Tom Jones'],
        ], $user);

        $trip = Trip::where('name', 'Guest Trip')->first();
        $this->assertNotNull($trip);
        $this->assertStringContainsString('Jane Smith', $trip->description);
        $this->assertStringContainsString('Tom Jones', $trip->description);
        $this->assertStringContainsString('not on Subterra', $trip->description);
    }

    #[Test]
    public function create_trip_report_fires_trip_created_event(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Event Test Trip',
            'description' => 'Testing events.',
            'date' => '2024-06-15',
        ], $user);

        Event::assertDispatched(TripCreated::class);
        Event::assertDispatched(TripParticipantTagged::class);
    }

    #[Test]
    public function create_trip_report_returns_trip_url_and_edit_url(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $result = $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'URL Test Trip',
            'description' => 'Checking URLs.',
            'date' => '2024-06-15',
        ], $user);

        $this->assertArrayHasKey('trip_url', $result);
        $this->assertArrayHasKey('edit_url', $result);
        $this->assertStringContainsString('/trips/', $result['trip_url']);
        $this->assertStringContainsString('/edit', $result['edit_url']);
    }

    #[Test]
    public function create_trip_report_returns_error_for_unknown_cave_system(): void
    {
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $result = $tool->handle([
            'cave_system_slug' => 'no-such-system',
            'entrance_cave_slug' => 'some-cave',
            'name' => 'Trip',
            'description' => 'Desc.',
            'date' => '2024-06-15',
        ], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('no-such-system', $result['error']);
    }

    #[Test]
    public function create_trip_report_returns_error_for_unknown_entrance_cave(): void
    {
        $system = CaveSystem::factory()->create(['slug' => 'gaping-gill', 'name' => 'Gaping Gill']);
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $result = $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'no-such-cave',
            'name' => 'Trip',
            'description' => 'Desc.',
            'date' => '2024-06-15',
        ], $user);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('no-such-cave', $result['error']);
    }

    #[Test]
    public function create_trip_report_defaults_to_public_visibility(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Visibility Test',
            'description' => 'Testing default visibility.',
            'date' => '2024-06-15',
        ], $user);

        $this->assertDatabaseHas('trips', [
            'name' => 'Visibility Test',
            'visibility' => 'public',
        ]);
    }

    #[Test]
    public function create_trip_report_respects_explicit_visibility(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Private Trip',
            'description' => 'Private.',
            'date' => '2024-06-15',
            'visibility' => 'private',
        ], $user);

        $this->assertDatabaseHas('trips', [
            'name' => 'Private Trip',
            'visibility' => 'private',
        ]);
    }

    #[Test]
    public function create_trip_report_calculates_end_time_from_duration(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Duration Test',
            'description' => 'Testing duration.',
            'date' => '2024-06-15',
            'start_time' => '10:00',
            'duration_minutes' => 180,
        ], $user);

        $trip = Trip::where('name', 'Duration Test')->first();
        $this->assertNotNull($trip);
        $this->assertNotNull($trip->end_time);
    }

    #[Test]
    public function create_trip_report_ignores_invalid_participant_ids_gracefully(): void
    {
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        [$system, $cave] = $this->makeSystemAndCave();
        $user = User::factory()->create();
        $tool = new CreateTripReportTool();

        $result = $tool->handle([
            'cave_system_slug' => 'gaping-gill',
            'entrance_cave_slug' => 'main-shaft',
            'name' => 'Bad IDs Trip',
            'description' => 'Testing bad participant IDs.',
            'date' => '2024-06-15',
            'participant_ids' => ['00000000-0000-0000-0000-000000000000'],
        ], $user);

        // Should succeed — bad IDs are silently skipped
        $this->assertTrue($result['success'] ?? false);
        $trip = Trip::where('name', 'Bad IDs Trip')->first();
        $this->assertNotNull($trip);
        $this->assertCount(1, $trip->participants); // only current user
    }
}
