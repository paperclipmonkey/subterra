<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Roles are already seeded by the migration
    }

    public function test_platform_admin_can_access_platform_routes()
    {
        $user = User::factory()->create();
        $user->assignRole('platform_admin');

        $this->actingAs($user)
            ->getJson('/api/admin/users')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/admin/pages')
            ->assertOk();

        // Platform admin CAN access duty officer routes now
        $this->actingAs($user)
            ->getJson('/api/admin/shifts')
            ->assertOk();
    }

    public function test_data_admin_can_access_catchments_only()
    {
        $user = User::factory()->create();
        $user->assignRole('data_admin');

        $this->actingAs($user)
            ->getJson('/api/admin/catchments')
            ->assertOk();

        // Data admin CAN access pages
        $this->actingAs($user)
            ->getJson('/api/admin/pages')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/admin/shifts')
            ->assertStatus(403);
    }

    public function test_duty_officer_can_access_shifts_but_not_pages()
    {
        $user = User::factory()->create();
        $user->assignRole('duty_officer');

        $this->actingAs($user)
            ->getJson('/api/admin/shifts')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/admin/incidents')
            ->assertOk();

        $this->actingAs($user)
            ->getJson('/api/admin/pages')
            ->assertStatus(403);

        $this->actingAs($user)
            ->getJson('/api/admin/users')
            ->assertStatus(403);
    }

    public function test_normal_user_cannot_access_any_admin_routes()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/admin/users')
            ->assertStatus(403);

        $this->actingAs($user)
            ->getJson('/api/admin/pages')
            ->assertStatus(403);

        $this->actingAs($user)
            ->getJson('/api/admin/shifts')
            ->assertStatus(403);
    }

    public function test_on_call_shift_requires_duty_officer_role()
    {
        $dutyOfficerAdmin = User::factory()->create();
        $dutyOfficerAdmin->assignRole('duty_officer');

        $dutyOfficer = User::factory()->create();
        $dutyOfficer->assignRole('duty_officer');

        $normalUser = User::factory()->create();

        // Duty officer assigning another duty officer (Should work)
        $this->actingAs($dutyOfficerAdmin)
            ->postJson('/api/admin/shifts', [
                'user_id' => $dutyOfficer->id,
                'start_at' => now()->addDay()->format('Y-m-d H:i:s'),
                'end_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(200);

        // Duty officer assigning a normal user (Should fail)
        $this->actingAs($dutyOfficerAdmin)
            ->postJson('/api/admin/shifts', [
                'user_id' => $normalUser->id,
                'start_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
                'end_at' => now()->addDays(4)->format('Y-m-d H:i:s'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_is_admin_attribute_returns_true_for_any_role()
    {
        $user = User::factory()->create();
        
        $this->assertFalse($user->is_admin);

        $user->assignRole('platform_admin');
        $this->assertTrue($user->is_admin);

        $user->removeRole('platform_admin');
        $user->assignRole('data_admin');
        $this->assertTrue($user->is_admin);

        $user->removeRole('data_admin');
        $user->assignRole('duty_officer');
        $this->assertTrue($user->is_admin);

        $user->removeRole('duty_officer');
        $this->assertFalse($user->is_admin);
    }
}
