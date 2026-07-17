<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubActivityHeatmapTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $adminUser;
    private User $approvedMember;
    private User $pendingMember;
    private User $nonMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->adminUser = User::factory()->admin()->create();
        $this->approvedMember = User::factory()->create();
        $this->pendingMember = User::factory()->create();
        $this->nonMember = User::factory()->create();

        $this->club->users()->attach($this->approvedMember, ['status' => 'approved']);
        $this->club->users()->attach($this->pendingMember, ['status' => 'pending']);

        // Create trips for the approved member to generate heatmap data.
        // Visibility is pinned to public so the counts below are deterministic
        // for viewers who aren't participants (e.g. the platform admin).
        // One trip today - 1 hour
        $now = Carbon::now();
        Trip::factory()->create([
            'visibility' => 'public',
            'start_time' => $now->clone(),
            'end_time' => $now->clone()->addHour(),
        ])->participants()->attach($this->approvedMember->id);

        // Two trips yesterday - 1 hour each
        $yesterday = Carbon::yesterday();
        Trip::factory()->count(2)->create([
            'visibility' => 'public',
            'start_time' => $yesterday->clone(),
            'end_time' => $yesterday->clone()->addHour(),
        ])->each(function ($trip) {
            $trip->participants()->attach($this->approvedMember->id);
        });

        // One trip 10 days ago - 1 hour
        $tenDaysAgo = Carbon::now()->subDays(10);
        Trip::factory()->create([
            'visibility' => 'public',
            'start_time' => $tenDaysAgo->clone(),
            'end_time' => $tenDaysAgo->clone()->addHour(),
        ])->participants()->attach($this->approvedMember->id);

        // One trip more than a year ago (should not be included)
        Trip::factory()->create([
            'visibility' => 'public',
            'start_time' => Carbon::now()->subYear()->subDay(),
            'end_time' => Carbon::now()->subYear()->subDay()->addHour(),
        ])->participants()->attach($this->approvedMember->id);
    }

    private function getEndpointUrl(): string
    {
        return "/api/clubs/{$this->club->slug}/activity-heatmap";
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_cannot_access_activity_heatmap(): void
    {
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertUnauthorized();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_member_cannot_access_activity_heatmap(): void
    {
        $this->actingAs($this->nonMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pending_member_cannot_access_activity_heatmap(): void
    {
        $this->actingAs($this->pendingMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approved_member_can_access_activity_heatmap(): void
    {
        $this->actingAs($this->approvedMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['date', 'count'],
        ]);

        // Expect 3 data points: today, yesterday, 10 days ago
        $response->assertJsonCount(3);

        $response->assertJsonFragment(['date' => Carbon::now()->toDateString(), 'count' => 1]);
        $response->assertJsonFragment(['date' => Carbon::yesterday()->toDateString(), 'count' => 2]);
        $response->assertJsonFragment(['date' => Carbon::now()->subDays(10)->toDateString(), 'count' => 1]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_user_can_access_activity_heatmap(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['date', 'count'],
        ]);
        $response->assertJsonCount(3);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function private_trips_are_excluded_from_heatmap_for_non_participants(): void
    {
        $coMember = User::factory()->create();
        $this->club->users()->attach($coMember, ['status' => 'approved']);

        // A private trip on a day with no other activity: its date must not
        // appear in the heatmap for a co-member who wasn't a participant.
        $fiveDaysAgo = Carbon::now()->subDays(5);
        Trip::factory()->create([
            'visibility' => 'private',
            'start_time' => $fiveDaysAgo->clone(),
            'end_time' => $fiveDaysAgo->clone()->addHour(),
        ])->participants()->attach($this->approvedMember->id);

        $this->actingAs($coMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonCount(3);
        $response->assertJsonMissing(['date' => $fiveDaysAgo->toDateString()]);

        // The participant still sees their own private trip's day.
        $this->actingAs($this->approvedMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonCount(4);
        $response->assertJsonFragment(['date' => $fiveDaysAgo->toDateString(), 'count' => 1]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function activity_heatmap_returns_empty_array_for_club_with_no_trips_in_last_year(): void
    {
        $clubWithNoRecentTrips = Club::factory()->create();
        $memberOfOtherClub = User::factory()->create();
        $clubWithNoRecentTrips->users()->attach($memberOfOtherClub, ['status' => 'approved']);

        // Ensure a trip exists but is older than one year for this specific club context
        Trip::factory()->create([
            'start_time' => Carbon::now()->subYear()->subMonth(),
        ])->participants()->attach($memberOfOtherClub->id);

        $this->actingAs($memberOfOtherClub, 'sanctum');
        $response = $this->getJson("/api/clubs/{$clubWithNoRecentTrips->slug}/activity-heatmap");
        $response->assertOk();
        $response->assertJsonCount(0);
        $response->assertExactJson([]);
    }
}
