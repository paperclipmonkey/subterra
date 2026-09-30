<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\TripCreated;
use App\Events\TripParticipantTagged;
use App\Models\Cave;
use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * visibility_addable = 'club' (the default for under-18s) means only people
 * sharing an approved club may tag the user onto a trip — whatever the trip's
 * own visibility.
 */
class TripTaggingPermissionTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;

    private User $clubOnlyMember;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake([TripCreated::class, TripParticipantTagged::class]);

        $this->club = Club::factory()->create();
        $this->clubOnlyMember = User::factory()->create(['visibility_addable' => 'club']);
        $this->clubOnlyMember->clubs()->attach($this->club->id, ['status' => 'approved']);
    }

    private function tripPayload(array $participants): array
    {
        $cave = Cave::factory()->create();

        return [
            'name' => 'Tagging Test',
            'cave_system_id' => $cave->cave_system_id,
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'visibility' => 'public',
            'participants' => $participants,
        ];
    }

    #[Test]
    public function a_non_member_cannot_tag_a_club_only_user_onto_a_public_trip(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->postJson('/api/trips', $this->tripPayload([$outsider->id, $this->clubOnlyMember->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participants');

        $this->assertDatabaseMissing('trips', ['name' => 'Tagging Test']);
        $this->assertDatabaseMissing('trip_user', ['user_id' => $this->clubOnlyMember->id]);
    }

    #[Test]
    public function a_pending_member_of_the_same_club_cannot_tag_them_either(): void
    {
        $pending = User::factory()->create();
        $pending->clubs()->attach($this->club->id, ['status' => 'pending']);

        $this->actingAs($pending)
            ->postJson('/api/trips', $this->tripPayload([$pending->id, $this->clubOnlyMember->id]))
            ->assertUnprocessable();
    }

    #[Test]
    public function a_fellow_approved_member_can_tag_them(): void
    {
        $member = User::factory()->create();
        $member->clubs()->attach($this->club->id, ['status' => 'approved']);

        $this->actingAs($member)
            ->postJson('/api/trips', $this->tripPayload([$member->id, $this->clubOnlyMember->id]))
            ->assertCreated();

        $this->assertDatabaseHas('trip_user', ['user_id' => $this->clubOnlyMember->id]);
    }

    #[Test]
    public function a_club_only_user_can_tag_themselves(): void
    {
        $this->actingAs($this->clubOnlyMember)
            ->postJson('/api/trips', $this->tripPayload([$this->clubOnlyMember->id]))
            ->assertCreated();
    }

    #[Test]
    public function anyone_can_tag_a_public_user(): void
    {
        $outsider = User::factory()->create();
        $public = User::factory()->create(['visibility_addable' => 'public']);

        $this->actingAs($outsider)
            ->postJson('/api/trips', $this->tripPayload([$outsider->id, $public->id]))
            ->assertCreated();
    }

    #[Test]
    public function updating_a_trip_cannot_add_a_club_only_user_but_keeps_one_already_on_it(): void
    {
        $outsider = User::factory()->create();
        $trip = Trip::factory()->create(['visibility' => 'public']);
        $trip->participants()->attach($outsider->id);

        $this->actingAs($outsider)
            ->putJson("/api/trips/{$trip->short_id}", ['participants' => [$outsider->id, $this->clubOnlyMember->id]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('participants');
        $this->assertDatabaseMissing('trip_user', ['trip_id' => $trip->id, 'user_id' => $this->clubOnlyMember->id]);

        // Already on the trip (tagged by a club-mate): an outsider's unrelated
        // edit must not fail or drop them.
        $trip->participants()->attach($this->clubOnlyMember->id);
        $this->actingAs($outsider)
            ->putJson("/api/trips/{$trip->short_id}", ['name' => 'Renamed', 'participants' => [$outsider->id, $this->clubOnlyMember->id]])
            ->assertOk();
        $this->assertDatabaseHas('trip_user', ['trip_id' => $trip->id, 'user_id' => $this->clubOnlyMember->id]);
    }

    #[Test]
    public function the_participant_picker_does_not_offer_club_only_users_to_non_members(): void
    {
        $outsider = User::factory()->create();
        // Shared trip history and an exact email match used to bypass the setting.
        $trip = Trip::factory()->create();
        $trip->participants()->attach([$outsider->id, $this->clubOnlyMember->id]);

        $this->actingAs($outsider)
            ->getJson('/api/users')
            ->assertOk()
            ->assertJsonMissing(['id' => $this->clubOnlyMember->id]);

        $this->actingAs($outsider)
            ->getJson('/api/users?search='.urlencode($this->clubOnlyMember->email))
            ->assertOk()
            ->assertJsonMissing(['id' => $this->clubOnlyMember->id]);
    }
}
