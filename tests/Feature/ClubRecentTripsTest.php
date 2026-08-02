<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubRecentTripsTest extends TestCase
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

        // Create some trips for the club, ensuring some are recent.
        // Visibility is pinned to public so the counts below are deterministic
        // for viewers who aren't participants (e.g. the platform admin).
        Trip::factory()->count(3)->create(['visibility' => 'public'])->each(function ($trip) {
            $trip->participants()->attach($this->approvedMember->id);
            $trip->start_time = Carbon::now()->subDays(rand(1, 30)); // Recent trips
            $trip->save();
        });
        // Create older trips that shouldn't appear in "recent" if logic is strict (e.g. last year)
        Trip::factory()->count(2)->create(['visibility' => 'public'])->each(function ($trip) {
            $trip->participants()->attach($this->approvedMember->id);
            $trip->start_time = Carbon::now()->subMonths(13); // Older trips
            $trip->save();
        });
    }

    private function getEndpointUrl(): string
    {
        return "/api/clubs/{$this->club->slug}/recent-trips";
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_cannot_access_recent_trips(): void
    {
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertUnauthorized();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_member_cannot_access_recent_trips(): void
    {
        $this->actingAs($this->nonMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pending_member_cannot_access_recent_trips(): void
    {
        $this->actingAs($this->pendingMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approved_member_can_access_recent_trips(): void
    {
        $this->actingAs($this->approvedMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'start_time'], // Assuming TripResource has these
            ],
        ]);
        // Controller logic limits to 10, and within last year.
        // We created 3 recent trips.
        $response->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_user_can_access_recent_trips(): void
    {
        $this->actingAs($this->adminUser, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'start_time'],
            ],
        ]);
        $response->assertJsonCount(3, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function private_trips_are_hidden_from_co_members_who_did_not_participate(): void
    {
        $coMember = User::factory()->create();
        $this->club->users()->attach($coMember, ['status' => 'approved']);

        $privateTrip = Trip::factory()->create([
            'visibility' => 'private',
            'start_time' => Carbon::now()->subDays(2),
        ]);
        $privateTrip->participants()->attach($this->approvedMember->id);

        // The non-participating co-member must not see the private trip.
        $this->actingAs($coMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonCount(3, 'data');
        $response->assertJsonMissing(['id' => $privateTrip->short_id]);

        // The participant themselves still sees it.
        $this->actingAs($this->approvedMember, 'sanctum');
        $response = $this->getJson($this->getEndpointUrl());
        $response->assertOk();
        $response->assertJsonCount(4, 'data');
    }
}
