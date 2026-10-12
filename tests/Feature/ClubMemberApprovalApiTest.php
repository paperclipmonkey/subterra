<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubMemberApprovalApiTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_updated_user_resource_after_approval()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $club = Club::factory()->create();

        $club->users()->attach($user->id, ['status' => 'pending']);

        $this->actingAs($admin, 'sanctum');

        $response = $this->putJson("/api/admin/clubs/{$club->slug}/members/{$user->id}/approve");

        $response->assertStatus(200);

        // Use the structure from UserDetailEmailResource
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'clubs' => [
                    '*' => ['id', 'name', 'slug', 'is_admin', 'status'],
                ],
            ],
        ]);

        // Check club status
        $this->assertEquals('approved', $user->fresh()->clubs->first()->pivot->status);

        // Verify club ID is present in the response
        $this->assertEquals($club->id, $response->json('data.clubs.0.id'));
        $this->assertEquals('approved', $response->json('data.clubs.0.status'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_allows_club_admin_to_approve_member()
    {
        $club = Club::factory()->create();
        $clubAdmin = User::factory()->create();
        $club->users()->attach($clubAdmin->id, ['is_admin' => true, 'status' => 'approved']);

        $user = User::factory()->create();
        $club->users()->attach($user->id, ['status' => 'pending']);

        $this->actingAs($clubAdmin, 'sanctum');

        $response = $this->putJson("/api/admin/clubs/{$club->slug}/members/{$user->id}/approve");

        $response->assertStatus(200);
        $this->assertEquals('approved', $user->fresh()->clubs->first()->pivot->status);

        // A club admin is an ordinary member and must not receive the applicant's
        // private profile.
        foreach (['email', 'phone', 'date_of_birth', 'is_minor', 'roles'] as $field) {
            $response->assertJsonMissingPath("data.{$field}");
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_forbids_non_club_admin_from_approving_member()
    {
        $club = Club::factory()->create();
        $otherUser = User::factory()->create();
        // Other user is just a member, not admin
        $club->users()->attach($otherUser->id, ['is_admin' => false, 'status' => 'approved']);

        $user = User::factory()->create();
        $club->users()->attach($user->id, ['status' => 'pending']);

        $this->actingAs($otherUser, 'sanctum');

        $response = $this->putJson("/api/admin/clubs/{$club->slug}/members/{$user->id}/approve");

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function other_club_admins_can_see_who_approved_a_request_and_when()
    {
        $club = Club::factory()->create();
        $approver = User::factory()->create(['name' => 'Alice Approver']);
        $otherAdmin = User::factory()->create();
        $club->users()->attach($approver->id, ['is_admin' => true, 'status' => 'approved']);
        $club->users()->attach($otherAdmin->id, ['is_admin' => true, 'status' => 'approved']);

        $user = User::factory()->create(['name' => 'New Member']);
        $club->users()->attach($user->id, ['status' => 'pending']);

        $this->travelTo('2026-10-10 14:30:00');
        $this->actingAs($approver, 'sanctum')
            ->putJson("/api/admin/clubs/{$club->slug}/members/{$user->id}/approve")
            ->assertOk();

        $this->travelTo('2026-10-12 09:00:00');
        $this->actingAs($otherAdmin, 'sanctum')
            ->getJson("/api/admin/clubs/{$club->slug}/recently-approved-members")
            ->assertOk()
            ->assertExactJson([[
                'id' => $user->id,
                'name' => 'New Member',
                'photo' => null,
                'approved_by' => 'Alice Approver',
                'approved_at' => '2026-10-10T14:30:00+00:00',
            ]]);

        $this->travelTo('2026-11-15 09:00:00');
        $this->actingAs($otherAdmin, 'sanctum')
            ->getJson("/api/admin/clubs/{$club->slug}/recently-approved-members")
            ->assertOk()
            ->assertExactJson([]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ordinary_members_cannot_see_recently_approved_members()
    {
        $club = Club::factory()->create();
        $member = User::factory()->create();
        $club->users()->attach($member->id, ['status' => 'approved']);

        $this->actingAs($member, 'sanctum')
            ->getJson("/api/admin/clubs/{$club->slug}/recently-approved-members")
            ->assertForbidden();
    }
}
