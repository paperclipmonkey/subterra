<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubSummaryTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $approvedMember;
    private User $secondMember;
    private User $nonMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->approvedMember = User::factory()->create(['name' => 'Active Annie']);
        $this->secondMember = User::factory()->create(['name' => 'Bystander Bob']);
        $this->nonMember = User::factory()->create();

        $this->club->users()->attach($this->approvedMember, ['status' => 'approved']);
        $this->club->users()->attach($this->secondMember, ['status' => 'approved']);
    }

    private function url(): string
    {
        return "/api/clubs/{$this->club->slug}/summary";
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_cannot_access_summary(): void
    {
        $this->getJson($this->url())->assertUnauthorized();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_member_cannot_access_summary(): void
    {
        $this->actingAs($this->nonMember, 'sanctum');
        $this->getJson($this->url())->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function summary_reports_headline_stats(): void
    {
        $caveOne = Cave::factory()->create();
        $caveTwo = Cave::factory()->create();

        // Trip 1: 5 days ago, cave one, 2 hours, just Annie.
        $tripOne = Trip::factory()->create([
            'entrance_cave_id' => $caveOne->id,
            'start_time' => Carbon::now()->subDays(5)->setTime(10, 0),
            'end_time' => Carbon::now()->subDays(5)->setTime(12, 0),
        ]);
        $tripOne->participants()->attach($this->approvedMember->id);

        // Trip 2: 10 days ago, cave two, 3 hours, Annie + Bob.
        $tripTwo = Trip::factory()->create([
            'entrance_cave_id' => $caveTwo->id,
            'start_time' => Carbon::now()->subDays(10)->setTime(9, 0),
            'end_time' => Carbon::now()->subDays(10)->setTime(12, 0),
        ]);
        $tripTwo->participants()->attach([$this->approvedMember->id, $this->secondMember->id]);

        // An old trip (>1 year) should be excluded from the rolling-year stats.
        $oldTrip = Trip::factory()->create([
            'entrance_cave_id' => $caveOne->id,
            'start_time' => Carbon::now()->subMonths(14)->setTime(10, 0),
            'end_time' => Carbon::now()->subMonths(14)->setTime(13, 0),
        ]);
        $oldTrip->participants()->attach($this->approvedMember->id);

        $this->actingAs($this->approvedMember, 'sanctum');
        $response = $this->getJson($this->url())->assertOk();

        $response->assertJsonPath('stats.hours_underground', 5)
            ->assertJsonPath('stats.trips_logged', 2)
            ->assertJsonPath('stats.caves_visited', 2)
            ->assertJsonPath('stats.most_active.name', 'Active Annie')
            ->assertJsonPath('stats.most_active.trip_count', 2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function summary_surfaces_allied_clubs_and_photos(): void
    {
        $alliedClub = Club::factory()->create(['name' => 'Craven Pothole Club']);
        $guest = User::factory()->create();
        $alliedClub->users()->attach($guest, ['status' => 'approved']);

        $trip = Trip::factory()->create([
            'start_time' => Carbon::now()->subDays(3)->setTime(10, 0),
            'end_time' => Carbon::now()->subDays(3)->setTime(13, 0),
        ]);
        $trip->participants()->attach([$this->approvedMember->id, $guest->id]);
        $trip->media()->create(['filename' => 'photo_desktop.webp', 'title' => 'In the chamber']);

        $this->actingAs($this->approvedMember, 'sanctum');
        $response = $this->getJson($this->url())->assertOk();

        $response->assertJsonPath('allied_clubs.0.name', 'Craven Pothole Club')
            ->assertJsonPath('allied_clubs.0.trip_count', 1)
            ->assertJsonPath('photo_count', 1)
            ->assertJsonPath('photos.0.trip_id', $trip->short_id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function trips_with_no_start_time_do_not_inflate_new_cave_count(): void
    {
        $cave = Cave::factory()->create();

        // A dated visit this year — the only cave that should count.
        $dated = Trip::factory()->create([
            'entrance_cave_id' => Cave::factory()->create()->id,
            'start_time' => Carbon::now()->subDays(2)->setTime(10, 0),
            'end_time' => Carbon::now()->subDays(2)->setTime(12, 0),
        ]);
        $dated->participants()->attach($this->approvedMember->id);

        // A "Marked as Done" style trip with no start_time must be ignored, not
        // treated as a brand-new cave visited "now".
        $undated = Trip::factory()->create([
            'entrance_cave_id' => $cave->id,
            'start_time' => null,
            'end_time' => null,
        ]);
        $undated->participants()->attach($this->approvedMember->id);

        $this->actingAs($this->approvedMember, 'sanctum');
        $response = $this->getJson($this->url())->assertOk();

        $response->assertJsonPath('stats.caves_visited', 1)
            ->assertJsonPath('stats.new_caves_this_year', 1);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function summary_handles_a_club_with_no_members(): void
    {
        $emptyClub = Club::factory()->create();

        $this->actingAs($this->approvedMember, 'sanctum');
        // approvedMember isn't in emptyClub, so they can't view it — use an admin.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');

        $response = $this->getJson("/api/clubs/{$emptyClub->slug}/summary")->assertOk();
        $response->assertJsonPath('stats.trips_logged', 0)
            ->assertJsonPath('stats.most_active', null)
            ->assertJsonPath('allied_clubs', [])
            ->assertJsonPath('photos', []);
    }
}
