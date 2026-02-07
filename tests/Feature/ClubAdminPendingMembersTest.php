<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClubAdminPendingMembersTest extends TestCase
{
    use RefreshDatabase;

    private Club $club;
    private User $systemAdmin;
    private User $clubAdmin;
    private User $pendingUser1;
    private User $pendingUser2;
    private User $nonMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->club = Club::factory()->create();
        $this->systemAdmin = User::factory()->create(['is_admin' => true]);
        $this->clubAdmin = User::factory()->create(['is_admin' => false]);
        $this->pendingUser1 = User::factory()->create(['name' => 'Pending User 1']);
        $this->pendingUser2 = User::factory()->create(['name' => 'Pending User 2']);
        $this->nonMember = User::factory()->create();

        // Set up club admin as approved member with admin privileges
        $this->club->users()->attach($this->clubAdmin->id, ['status' => 'approved', 'is_admin' => true]);
        
        // Set up pending members
        $this->club->users()->attach($this->pendingUser1->id, ['status' => 'pending', 'is_admin' => false]);
        $this->club->users()->attach($this->pendingUser2->id, ['status' => 'pending', 'is_admin' => false]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function system_admin_can_get_pending_members(): void
    {
        $this->actingAs($this->systemAdmin, 'sanctum');
        $response = $this->getJson("/api/admin/clubs/{$this->club->slug}/pending-members");
        
        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['id' => $this->pendingUser1->id, 'name' => 'Pending User 1']);
        $response->assertJsonFragment(['id' => $this->pendingUser2->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function club_admin_can_get_pending_members(): void
    {
        $this->actingAs($this->clubAdmin, 'sanctum');
        $response = $this->getJson("/api/admin/clubs/{$this->club->slug}/pending-members");
        
        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['id' => $this->pendingUser1->id, 'name' => 'Pending User 1']);
        $response->assertJsonFragment(['id' => $this->pendingUser2->id]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_member_cannot_get_pending_members(): void
    {
        $this->actingAs($this->nonMember, 'sanctum');
        $response = $this->getJson("/api/admin/clubs/{$this->club->slug}/pending-members");
        
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_user_cannot_get_pending_members(): void
    {
        $response = $this->getJson("/api/admin/clubs/{$this->club->slug}/pending-members");
        
        $response->assertUnauthorized();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function system_admin_can_approve_member(): void
    {
        $this->actingAs($this->systemAdmin, 'sanctum');
        $response = $this->putJson("/api/admin/clubs/{$this->club->slug}/members/{$this->pendingUser1->id}/approve");
        
        $response->assertOk();
        $response->assertJson(['message' => 'Member approved.']);
        
        // Verify the user status changed to approved
        $this->assertDatabaseHas('club_user', [
            'club_id' => $this->club->id,
            'user_id' => $this->pendingUser1->id,
            'status' => 'approved'
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function club_admin_can_approve_member(): void
    {
        $this->actingAs($this->clubAdmin, 'sanctum');
        $response = $this->putJson("/api/admin/clubs/{$this->club->slug}/members/{$this->pendingUser1->id}/approve");
        
        $response->assertOk();
        $response->assertJson(['message' => 'Member approved.']);
        
        // Verify the user status changed to approved
        $this->assertDatabaseHas('club_user', [
            'club_id' => $this->club->id,
            'user_id' => $this->pendingUser1->id,
            'status' => 'approved'
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_member_cannot_approve_member(): void
    {
        $this->actingAs($this->nonMember, 'sanctum');
        $response = $this->putJson("/api/admin/clubs/{$this->club->slug}/members/{$this->pendingUser1->id}/approve");
        
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function system_admin_can_reject_member(): void
    {
        $this->actingAs($this->systemAdmin, 'sanctum');
        $response = $this->putJson("/api/admin/clubs/{$this->club->slug}/members/{$this->pendingUser1->id}/reject");
        
        $response->assertOk();
        $response->assertJson(['message' => 'Member rejected.']);
        
        // Verify the user was removed from the club
        $this->assertDatabaseMissing('club_user', [
            'club_id' => $this->club->id,
            'user_id' => $this->pendingUser1->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function club_admin_can_reject_member(): void
    {
        $this->actingAs($this->clubAdmin, 'sanctum');
        $response = $this->putJson("/api/admin/clubs/{$this->club->slug}/members/{$this->pendingUser1->id}/reject");
        
        $response->assertOk();
        $response->assertJson(['message' => 'Member rejected.']);
        
        // Verify the user was removed from the club
        $this->assertDatabaseMissing('club_user', [
            'club_id' => $this->club->id,
            'user_id' => $this->pendingUser1->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_member_cannot_reject_member(): void
    {
        $this->actingAs($this->nonMember, 'sanctum');
        $response = $this->putJson("/api/admin/clubs/{$this->club->slug}/members/{$this->pendingUser1->id}/reject");
        
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pending_club_admin_cannot_access_pending_members(): void
    {
        // Create a user who is marked as admin but still pending approval
        $pendingAdmin = User::factory()->create();
        $this->club->users()->attach($pendingAdmin->id, ['status' => 'pending', 'is_admin' => true]);
        
        $this->actingAs($pendingAdmin, 'sanctum');
        $response = $this->getJson("/api/admin/clubs/{$this->club->slug}/pending-members");
        
        // Should be forbidden because the user is not yet approved
        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function approved_non_admin_member_cannot_access_pending_members(): void
    {
        // Create an approved member without admin privileges
        $regularMember = User::factory()->create();
        $this->club->users()->attach($regularMember->id, ['status' => 'approved', 'is_admin' => false]);
        
        $this->actingAs($regularMember, 'sanctum');
        $response = $this->getJson("/api/admin/clubs/{$this->club->slug}/pending-members");
        
        $response->assertForbidden();
    }
}
