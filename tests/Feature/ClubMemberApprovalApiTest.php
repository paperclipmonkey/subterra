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
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_approved' => false]);
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
                    '*' => ['id', 'name', 'slug', 'is_admin', 'status']
                ],
                'is_approved'
            ]
        ]);

        $response->assertJsonPath('data.is_approved', true);
        
        // Verify club ID is present in the response
        $this->assertEquals($club->id, $response->json('data.clubs.0.id'));
        $this->assertEquals('approved', $response->json('data.clubs.0.status'));
    }
}
