<?php

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
        $this->assertEquals('approved', $response->json('data.clubs.0.status'));
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
}
