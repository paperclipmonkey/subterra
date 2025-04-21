<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Club;

class ClubTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $regularUser;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an admin user
        $this->adminUser = User::factory()->create(['is_admin' => true]);
        // Create a regular user
        $this->regularUser = User::factory()->create(['is_admin' => false]);
    }

    // --- Index Tests (Public) ---

    /** @test */
    public function guest_can_view_enabled_clubs()
    {
        Club::factory()->count(3)->enabled()->create();
        Club::factory()->count(2)->disabled()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')->getJson('/api/clubs');

        $response->assertStatus(200)
             ->assertJsonCount(3, 'data'); // Only enabled clubs in 'data'
    }
    // --- Admin Index Tests ---

    /** @test */
    public function admin_can_view_all_clubs()
    {
        Club::factory()->count(3)->enabled()->create();
        Club::factory()->count(2)->disabled()->create();

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson('/api/admin/clubs');

        $response->assertStatus(200)
                 ->assertJsonCount(5, 'data'); // All clubs
    }

    /** @test */
    public function non_admin_cannot_view_admin_club_index()
    {
        $response = $this->actingAs($this->regularUser, 'sanctum')->getJson('/api/admin/clubs');
        $response->assertStatus(401); // Forbidden
    }

    // --- Show Tests ---

    /** @test */
    public function anyone_can_view_an_enabled_club()
    {
        $club = Club::factory()->enabled()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')->getJson("/api/clubs/{$club->slug}");

        $response->assertStatus(200)
                 ->assertJson(['id' => $club->id, 'name' => $club->name]);
    }

    /** @test */
    public function admin_can_view_a_disabled_club()
    {
        $club = Club::factory()->disabled()->create();

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/clubs/{$club->slug}");

        $response->assertStatus(200)
                 ->assertJson(['id' => $club->id, 'name' => $club->name]);
    }

    /** @test */
    public function non_admin_cannot_view_a_disabled_club()
    {
        $club = Club::factory()->disabled()->create();

        // Regular user
        $response = $this->actingAs($this->regularUser, 'sanctum')->getJson("/api/clubs/{$club->slug}");
        $response->assertStatus(404); // Or 403 depending on policy

        // Guest
        $response = $this->getJson("/api/clubs/{$club->slug}");
        $response->assertStatus(404);
    }

    /** @test */
    public function show_returns_404_for_non_existent_club()
    {
        $response = $this->getJson('/api/clubs/non-existent-slug');
        $response->assertStatus(404);
    }

    // --- Store Tests ---

    /** @test */
    public function admin_can_create_a_club()
    {
        $clubData = [
            'name' => 'Test Club',
            'slug' => 'test-club',
            'description' => 'A test club description.',
            'website' => 'http://example.com',
            'location' => 'Test Location',
            'is_active' => true,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/admin/clubs', $clubData);

        $response->assertStatus(201)
                 ->assertJsonFragment(['name' => 'Test Club']);
        $this->assertDatabaseHas('clubs', ['slug' => 'test-club', 'is_active' => true]);
    }

    /** @test */
    public function admin_can_create_a_club_defaulting_to_enabled()
    {
        $clubData = [
            'name' => 'Test Club Default Enabled',
            'slug' => 'test-club-default-enabled',
            // is_active is omitted
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/admin/clubs', $clubData);

        $response->assertStatus(201);
        $this->assertDatabaseHas('clubs', ['slug' => 'test-club-default-enabled', 'is_active' => true]);
    }

    /** @test */
    public function non_admin_cannot_create_a_club()
    {
        $clubData = Club::factory()->make()->toArray();

        $response = $this->actingAs($this->regularUser, 'sanctum')->postJson('/api/admin/clubs', $clubData);
        $response->assertStatus(401);

        $response = $this->postJson('/api/admin/clubs', $clubData);
        $response->assertStatus(401);
    }

    /** @test */
    public function store_validates_required_fields()
    {
        $response = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/admin/clubs', []);
        $response->assertStatus(422)
                 ->assertJson(['name' => ["The name field is required."], 'slug' => ["The slug field is required."]]);
    }

    /** @test */
    public function store_validates_unique_name_and_slug()
    {
        $existingClub = Club::factory()->create();
        $clubData = [
            'name' => $existingClub->name, // Duplicate name
            'slug' => $existingClub->slug, // Duplicate slug
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')->postJson('/api/admin/clubs', $clubData);
        $response->assertStatus(422)
        ->assertJson(['name' => ["The name has already been taken."], 'slug' => ["The slug has already been taken."]]);
    }

    // --- Update Tests ---

    /** @test */
    public function admin_can_update_a_club()
    {
        $club = Club::factory()->create();
        $updateData = [
            'name' => 'Updated Club Name',
            'description' => 'Updated description.',
            'is_active' => false,
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}", $updateData);

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Updated Club Name', 'is_active' => false]);
        $this->assertDatabaseHas('clubs', ['id' => $club->id, 'name' => 'Updated Club Name', 'is_active' => false]);
    }

    /** @test */
    public function non_admin_cannot_update_a_club()
    {
        $club = Club::factory()->create();
        $updateData = ['name' => 'Attempted Update'];

        $response = $this->actingAs($this->regularUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}", $updateData);
        $response->assertStatus(401);

        $response = $this->putJson("/api/admin/clubs/{$club->slug}", $updateData);
        $response->assertStatus(401);
    }

    /** @test */
    public function update_validates_unique_name_ignoring_self()
    {
        $club1 = Club::factory()->create();
        $club2 = Club::factory()->create();

        $updateData = ['name' => $club1->name]; // Try to update club2 with club1's name

        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club2->slug}", $updateData);
        $response->assertStatus(422)
                 ->assertJson(['name' => ["The name has already been taken."]]);

        // Should be able to update club1 with its own name
        $updateDataSelf = ['name' => $club1->name];
        $responseSelf = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club1->slug}", $updateDataSelf);
        $responseSelf->assertStatus(200);
    }

    // --- Destroy Tests ---

    /** @test */
    public function admin_can_delete_a_club()
    {
        $club = Club::factory()->create();

        $response = $this->actingAs($this->adminUser, 'sanctum')->deleteJson("/api/admin/clubs/{$club->slug}");

        $response->assertStatus(204); // No Content
        $this->assertDatabaseMissing('clubs', ['id' => $club->id]);
    }

    /** @test */
    public function non_admin_cannot_delete_a_club()
    {
        $club = Club::factory()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')->deleteJson("/api/admin/clubs/{$club->slug}");
        $response->assertStatus(401);

        $response = $this->deleteJson("/api/admin/clubs/{$club->slug}");
        $response->assertStatus(401);

        $this->assertDatabaseHas('clubs', ['id' => $club->id]);
    }

    // --- Toggle Enabled Tests ---

    /** @test */
    public function admin_can_toggle_club_enabled_status()
    {
        $club = Club::factory()->enabled()->create();
        $this->assertTrue($club->is_active);

        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/toggle-active");

        $response->assertStatus(200)
                 ->assertJsonFragment(['is_active' => false]);
        $this->assertFalse($club->fresh()->is_active);

        // Toggle back
        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/toggle-active");
        $response->assertStatus(200)
                 ->assertJsonFragment(['is_active' => true]);
        $this->assertTrue($club->fresh()->is_active);
    }

    /** @test */
    public function non_admin_cannot_toggle_club_enabled_status()
    {
        $club = Club::factory()->enabled()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/toggle-active");
        $response->assertStatus(401);

        $response = $this->putJson("/api/admin/clubs/{$club->slug}/toggle-active");
        $response->assertStatus(401);

        $this->assertTrue($club->fresh()->is_active);
    }

    // --- Request Join Tests ---

    /** @test */
    public function authenticated_user_can_request_to_join_a_club()
    {
        $club = Club::factory()->enabled()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')->postJson("/api/clubs/{$club->slug}/join");

        $response->assertStatus(201)
                 ->assertJson(['message' => 'Join request sent successfully.']);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $this->regularUser->id,
            'status' => 'pending'
        ]);
    }

    /** @test */
    public function guest_cannot_request_to_join_a_club()
    {
        $club = Club::factory()->enabled()->create();

        $response = $this->postJson("/api/clubs/{$club->slug}/join");
        $response->assertStatus(401); // Unauthorized
    }

    /** @test */
    public function user_cannot_request_to_join_if_already_member_or_pending()
    {
        $club = Club::factory()->enabled()->create();
        // Add user as pending
        $club->users()->attach($this->regularUser->id, ['status' => 'pending']);

        $response = $this->actingAs($this->regularUser, 'sanctum')->postJson("/api/clubs/{$club->slug}/join");

        $response->assertStatus(409) // Conflict
                 ->assertJson(['message' => 'You are already a member or your request is pending.']);

        // Change status to approved
        $club->users()->updateExistingPivot($this->regularUser->id, ['status' => 'approved']);

        $response = $this->actingAs($this->regularUser, 'sanctum')->postJson("/api/clubs/{$club->slug}/join");

        $response->assertStatus(409); // Still conflict
    }

    // --- Get Approved Members Tests ---

    /** @test */
    public function admin_can_get_approved_members()
    {
        $club = Club::factory()->create();
        $approvedUser = User::factory()->create();
        $pendingUser = User::factory()->create();
        $club->users()->attach($approvedUser->id, ['status' => 'approved', 'is_admin' => false]);
        $club->users()->attach($pendingUser->id, ['status' => 'pending', 'is_admin' => false]);

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/admin/clubs/{$club->slug}/members");

        $response->assertStatus(200)
                 ->assertJsonCount(1) // Only the approved user
                 ->assertJsonFragment(['id' => $approvedUser->id, 'is_club_admin' => false])
                 ->assertJsonMissing(['id' => $pendingUser->id]);
    }

    /** @test */
    public function non_admin_cannot_get_approved_members()
    {
        $club = Club::factory()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')->getJson("/api/admin/clubs/{$club->slug}/members");
        $response->assertStatus(401);

        $response = $this->getJson("/api/admin/clubs/{$club->slug}/members");
        $response->assertStatus(401);
    }

    // --- Sync Approved Members Tests ---

    /** @test */
    public function admin_can_sync_approved_members()
    {
        $club = Club::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create(); // Will be removed
        $user4 = User::factory()->create(); // Will be added

        // Initial state
        $club->users()->attach($user1->id, ['status' => 'approved', 'is_admin' => false]);
        $club->users()->attach($user3->id, ['status' => 'approved', 'is_admin' => false]);
        $club->users()->attach(User::factory()->create()->id, ['status' => 'pending']); // Pending user should be ignored

        $syncData = [
            'members' => [
                ['id' => $user1->id, 'is_admin' => true],  // Update user1 to admin
                ['id' => $user2->id, 'is_admin' => false], // Add user2 as non-admin
                ['id' => $user4->id, 'is_admin' => false], // Add user4 as non-admin
            ]
        ];

        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/members", $syncData);

        $response->assertStatus(200)
                 ->assertJsonCount(3) // user1, user2, user4
                 ->assertJsonFragment(['id' => $user1->id, 'is_club_admin' => true])
                 ->assertJsonFragment(['id' => $user2->id, 'is_club_admin' => false])
                 ->assertJsonFragment(['id' => $user4->id, 'is_club_admin' => false])
                 ->assertJsonMissing(['id' => $user3->id]); // user3 removed

        // Verify database state
        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $user1->id, 'status' => 'approved', 'is_admin' => true]);
        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $user2->id, 'status' => 'approved', 'is_admin' => false]);
        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $user4->id, 'status' => 'approved', 'is_admin' => false]);
        $this->assertDatabaseMissing('club_user', ['club_id' => $club->id, 'user_id' => $user3->id, 'status' => 'approved']);
        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'status' => 'pending']); // Pending user should remain untouched by sync
    }

    /** @test */
    public function non_admin_cannot_sync_members()
    {
        $club = Club::factory()->create();
        $user = User::factory()->create();
        $syncData = ['members' => [['id' => $user->id, 'is_admin' => false]]];

        $response = $this->actingAs($this->regularUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/members", $syncData);
        $response->assertStatus(401);

        $response = $this->putJson("/api/admin/clubs/{$club->slug}/members", $syncData);
        $response->assertStatus(401);
    }

    /** @test */
    public function sync_members_validates_input()
    {
        $club = Club::factory()->create();

        // Missing members array
        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/members", []);
        // dd($response->getContent());
        $response->assertStatus(422)->assertJson(['members' => ['The members field must be present.']]);

        // Invalid member structure
        $invalidData = ['members' => [['id' => 999]]]; // Missing is_admin, non-existent user
        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/members", $invalidData);
        $response->assertStatus(422)->assertJson(['members.0.id' => ['The selected members.0.id is invalid.']]);
    }

    // --- Get Pending Members Tests ---

    /** @test */
    public function admin_can_get_pending_members()
    {
        $club = Club::factory()->create();
        $approvedUser = User::factory()->create();
        $pendingUser = User::factory()->create();
        $club->users()->attach($approvedUser->id, ['status' => 'approved']);
        $club->users()->attach($pendingUser->id, ['status' => 'pending']);

        $response = $this->actingAs($this->adminUser, 'sanctum')->getJson("/api/admin/clubs/{$club->slug}/pending-members");

        $response->assertStatus(200)
                 ->assertJsonCount(1) // Only the pending user
                 ->assertJsonFragment(['id' => $pendingUser->id])
                 ->assertJsonMissing(['id' => $approvedUser->id]);
    }

    /** @test */
    public function non_admin_cannot_get_pending_members()
    {
        $club = Club::factory()->create();

        $response = $this->actingAs($this->regularUser, 'sanctum')->getJson("/api/admin/clubs/{$club->slug}/pending-members");
        $response->assertStatus(401);

        $response = $this->getJson("/api/admin/clubs/{$club->slug}/pending-members");
        $response->assertStatus(401);
    }

    // --- Approve Member Tests ---

    /** @test */
    public function admin_can_approve_a_pending_member()
    {
        $club = Club::factory()->create();
        $pendingUser = User::factory()->create();
        $club->users()->attach($pendingUser->id, ['status' => 'pending']);

        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/members/{$pendingUser->id}/approve");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Member approved.']);
        $this->assertDatabaseHas('club_user', [
            'club_id' => $club->id,
            'user_id' => $pendingUser->id,
            'status' => 'approved'
        ]);
    }

    /** @test */
    public function non_admin_cannot_approve_a_member()
    {
        $club = Club::factory()->create();
        $pendingUser = User::factory()->create();
        $club->users()->attach($pendingUser->id, ['status' => 'pending']);

        $response = $this->actingAs($this->regularUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/members/{$pendingUser->id}/approve");
        $response->assertStatus(401);

        $response = $this->putJson("/api/admin/clubs/{$club->slug}/members/{$pendingUser->id}/approve");
        $response->assertStatus(401);

        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $pendingUser->id, 'status' => 'pending']);
    }

    // --- Reject Member Tests ---

    /** @test */
    public function admin_can_reject_a_pending_member()
    {
        $club = Club::factory()->create();
        $pendingUser = User::factory()->create();
        $club->users()->attach($pendingUser->id, ['status' => 'pending']);

        $response = $this->actingAs($this->adminUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/members/{$pendingUser->id}/reject");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Member rejected.']);
        $this->assertDatabaseMissing('club_user', [
            'club_id' => $club->id,
            'user_id' => $pendingUser->id,
        ]);
    }

    /** @test */
    public function non_admin_cannot_reject_a_member()
    {
        $club = Club::factory()->create();
        $pendingUser = User::factory()->create();
        $club->users()->attach($pendingUser->id, ['status' => 'pending']);

        $response = $this->actingAs($this->regularUser, 'sanctum')->putJson("/api/admin/clubs/{$club->slug}/members/{$pendingUser->id}/reject");
        $response->assertStatus(401);

        $response = $this->putJson("/api/admin/clubs/{$club->slug}/members/{$pendingUser->id}/reject");
        $response->assertStatus(401);

        $this->assertDatabaseHas('club_user', ['club_id' => $club->id, 'user_id' => $pendingUser->id, 'status' => 'pending']);
    }
}
