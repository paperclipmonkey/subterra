<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectIndividualMemberClubTest extends TestCase
{
    use RefreshDatabase;

    private Club $dim;
    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dim = Club::factory()->enabled()->create([
            'name' => 'Direct Individual Member',
            'slug' => Club::SLUG_DIRECT_INDIVIDUAL,
        ]);

        $this->member = User::factory()->create();
        $this->dim->users()->attach($this->member, ['status' => 'approved']);

        // A real trip taken by an approved member — a normal club would surface
        // this in its trips, stats and heatmap.
        $trip = Trip::factory()->create([
            'start_time' => Carbon::now()->subDays(3)->setTime(10, 0),
            'end_time' => Carbon::now()->subDays(3)->setTime(13, 0),
        ]);
        $trip->participants()->attach($this->member->id);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function club_detail_flags_individual_membership(): void
    {
        $this->actingAs($this->member, 'sanctum');

        $this->getJson('/api/clubs/dim')
            ->assertOk()
            ->assertJsonPath('is_individual_membership', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function normal_club_is_not_flagged_as_individual_membership(): void
    {
        $normal = Club::factory()->enabled()->create();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->getJson("/api/clubs/{$normal->slug}")
            ->assertOk()
            ->assertJsonPath('is_individual_membership', false);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function members_roster_is_not_exposed(): void
    {
        $this->actingAs($this->member, 'sanctum');

        $this->getJson('/api/clubs/dim/members')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function recent_trips_are_empty(): void
    {
        $this->actingAs($this->member, 'sanctum');

        $this->getJson('/api/clubs/dim/recent-trips')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function summary_stats_are_empty(): void
    {
        $this->actingAs($this->member, 'sanctum');

        $this->getJson('/api/clubs/dim/summary')
            ->assertOk()
            ->assertJsonPath('stats.trips_logged', 0)
            ->assertJsonPath('stats.hours_underground', 0)
            ->assertJsonPath('stats.most_active', null)
            ->assertJsonPath('allied_clubs', [])
            ->assertJsonPath('photos', []);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function activity_heatmap_is_empty(): void
    {
        $this->actingAs($this->member, 'sanctum');

        $this->getJson('/api/clubs/dim/activity-heatmap')
            ->assertOk()
            ->assertExactJson([]);
    }
}
