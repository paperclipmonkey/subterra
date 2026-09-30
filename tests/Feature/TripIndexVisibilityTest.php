<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GET /api/trips with no user_id is the global "All Trips" list (the frontend's
 * /trips?user_id=all and the discover page). It must only ever contain trips the
 * viewer is allowed to see, per Trip::scopeVisibleTo.
 */
class TripIndexVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $clubAuthor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->clubAuthor = User::factory()->create();
        $this->club->users()->attach($this->clubAuthor, ['status' => 'approved']);
    }

    /** @return list<string> */
    private function visibleTripIds(User $viewer): array
    {
        return collect($this->actingAs($viewer)->getJson('/api/trips')->assertOk()->json('data'))
            ->pluck('id')
            ->all();
    }

    private function tripBy(User $participant, string $visibility): Trip
    {
        $trip = Trip::factory()->create(['visibility' => $visibility]);
        $trip->participants()->attach($participant->id);

        return $trip;
    }

    #[Test]
    public function public_trips_are_listed_for_any_user(): void
    {
        $trip = $this->tripBy(User::factory()->create(), 'public');

        $this->assertContains($trip->short_id, $this->visibleTripIds(User::factory()->create()));
    }

    #[Test]
    public function private_trips_are_listed_only_for_their_participants(): void
    {
        $author = User::factory()->create();
        $trip = $this->tripBy($author, 'private');

        $this->assertContains($trip->short_id, $this->visibleTripIds($author));
        $this->assertNotContains($trip->short_id, $this->visibleTripIds(User::factory()->create()));
    }

    #[Test]
    public function club_trips_are_listed_for_approved_members_of_a_participants_club(): void
    {
        $trip = $this->tripBy($this->clubAuthor, 'club');
        $member = User::factory()->create();
        $this->club->users()->attach($member, ['status' => 'approved']);

        $this->assertContains($trip->short_id, $this->visibleTripIds($member));
    }

    #[Test]
    public function club_trips_are_hidden_from_users_whose_membership_is_still_pending(): void
    {
        $trip = $this->tripBy($this->clubAuthor, 'club');
        $pending = User::factory()->create();
        $this->club->users()->attach($pending, ['status' => 'pending']);

        $this->assertNotContains($trip->short_id, $this->visibleTripIds($pending));
    }

    #[Test]
    public function club_trips_are_hidden_from_users_outside_the_club(): void
    {
        $trip = $this->tripBy($this->clubAuthor, 'club');

        $this->assertNotContains($trip->short_id, $this->visibleTripIds(User::factory()->create()));
    }

    #[Test]
    public function club_trips_are_hidden_when_the_participant_is_only_pending_in_the_viewers_club(): void
    {
        $pendingAuthor = User::factory()->create();
        $this->club->users()->attach($pendingAuthor, ['status' => 'pending']);
        $trip = $this->tripBy($pendingAuthor, 'club');

        $this->assertNotContains($trip->short_id, $this->visibleTripIds($this->clubAuthor));
    }
}
