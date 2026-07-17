<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_empty_list_when_no_search_provided()
    {
        $user = User::factory()->create();
        User::factory()->count(5)->create(['visibility_addable' => 'public']);

        $response = $this->actingAs($user)
            ->getJson('/api/users');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_finds_public_users_by_name()
    {
        $me = User::factory()->create(['name' => 'Tester User']);
        $target = User::factory()->create(['name' => 'Target User', 'visibility_addable' => 'public']);
        $other = User::factory()->create(['name' => 'Other Person', 'visibility_addable' => 'public']);

        $response = $this->actingAs($me)
            ->getJson('/api/users?search=Target');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($target->id, $response->json('data.0.id'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_hides_private_unconnected_users()
    {
        $me = User::factory()->create(['name' => 'Tester User']);
        // Private user, not in club, not on trip
        $target = User::factory()->create(['name' => 'Secret User', 'visibility_addable' => 'private']);

        $response = $this->actingAs($me)
            ->getJson('/api/users?search=Secret');

        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_private_user_if_exact_email_match()
    {
        $me = User::factory()->create(['name' => 'Tester User']);
        $target = User::factory()->create(['name' => 'Secret User', 'email' => 'secret@example.com', 'visibility_addable' => 'private']);

        // Search by email
        $response = $this->actingAs($me)
            ->getJson('/api/users?search=secret@example.com');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($target->id, $response->json('data.0.id'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_prioritizes_trip_partners_over_club_over_public()
    {
        $me = User::factory()->create(['name' => 'Tester User']);

        // 1. Public user (Low priority)
        $publicUser = User::factory()->create(['name' => 'Dave Public', 'visibility_addable' => 'public']);

        // 2. Club user (Medium priority)
        $club = Club::factory()->create();
        $clubUser = User::factory()->create(['name' => 'Dave Club', 'visibility_addable' => 'club']);
        $club->users()->attach($me, ['status' => 'approved', 'is_admin' => false]);
        $club->users()->attach($clubUser, ['status' => 'approved', 'is_admin' => false]);

        // 3. Trip partner (High priority)
        $tripUser = User::factory()->create(['name' => 'Dave Trip', 'visibility_addable' => 'private']);
        // Create cave to satisfy FK
        $cave = \App\Models\Cave::factory()->create();
        $trip = Trip::factory()->create([
            'entrance_cave_id' => $cave->id,
            'exit_cave_id' => $cave->id,
            'cave_system_id' => $cave->cave_system_id,
        ]);
        // Note: Trip logic depends on shared trips. Need to attach participants.
        $trip->participants()->attach($me);
        $trip->participants()->attach($tripUser);

        // All contain "Dave"
        $response = $this->actingAs($me)
            ->getJson('/api/users?search=Dave');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);

        // Expected Order: Trip User, Club User, Public User
        $this->assertEquals($tripUser->id, $data[0]['id'], 'Trip User should be first');
        $this->assertEquals($clubUser->id, $data[1]['id'], 'Club User should be second');
        $this->assertEquals($publicUser->id, $data[2]['id'], 'Public User should be third');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_shows_club_members_regardless_of_visibility_setting()
    {
        $me = User::factory()->create(['name' => 'Tester User']);
        $club = Club::factory()->create();

        // Create club member with PRIVATE visibility
        $privateClubMember = User::factory()->create([
            'name' => 'Private Club Member',
            'visibility_addable' => 'private',
        ]);

        // Add both to the same club
        $club->users()->attach($me, ['status' => 'approved', 'is_admin' => false]);
        $club->users()->attach($privateClubMember, ['status' => 'approved', 'is_admin' => false]);

        // Search for the private club member
        $response = $this->actingAs($me)
            ->getJson('/api/users?search=Private');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($privateClubMember->id, $response->json('data.0.id'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pending_club_membership_does_not_expose_the_roster()
    {
        $me = User::factory()->create(['name' => 'Tester User']);
        $club = Club::factory()->create();
        $member = User::factory()->create(['name' => 'Roster Member', 'visibility_addable' => 'club']);

        // I have merely *requested* to join; the member is approved.
        $club->users()->attach($me, ['status' => 'pending']);
        $club->users()->attach($member, ['status' => 'approved']);

        // Suggestions (no search) must not include the club's roster.
        $response = $this->actingAs($me)->getJson('/api/users');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        // Nor should searching surface a club-only member to a pending applicant.
        $response = $this->actingAs($me)->getJson('/api/users?search=Roster');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approved_members_do_not_see_pending_applicants_as_club_contacts()
    {
        $me = User::factory()->create(['name' => 'Tester User']);
        $club = Club::factory()->create();
        $applicant = User::factory()->create(['name' => 'Pending Applicant', 'visibility_addable' => 'club']);

        $club->users()->attach($me, ['status' => 'approved']);
        $club->users()->attach($applicant, ['status' => 'pending']);

        $response = $this->actingAs($me)->getJson('/api/users?search=Pending');
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_searches_case_insensitively()
    {
        $me = User::factory()->create(['name' => 'Test User']);
        $target = User::factory()->create([
            'name' => 'John Smith',
            'email' => 'John.Smith@Example.COM',
            'visibility_addable' => 'public',
        ]);

        // Search by lowercase name
        $response = $this->actingAs($me)
            ->getJson('/api/users?search=john');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($target->id, $response->json('data.0.id'));

        // Search by uppercase name
        $response = $this->actingAs($me)
            ->getJson('/api/users?search=SMITH');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($target->id, $response->json('data.0.id'));

        // Search by mixed case email
        $response = $this->actingAs($me)
            ->getJson('/api/users?search=john.smith@example.com');
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $this->assertEquals($target->id, $response->json('data.0.id'));
    }
}
