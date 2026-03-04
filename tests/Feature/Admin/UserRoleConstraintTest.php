<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoleConstraintTest extends TestCase
{
    use RefreshDatabase;

    public function test_duty_officer_role_cannot_be_assigned_to_user_without_phone()
    {
        // Create an admin user to perform the action
        $admin = User::factory()->create();
        $admin->assignRole('platform_admin');

        // Create a user without a phone number
        $user = User::factory()->create(['phone' => null]);

        // Ensure the duty_officer role exists
        Role::firstOrCreate(['slug' => 'duty_officer'], ['name' => 'Duty Officer']);

        $response = $this->actingAs($admin)
            ->putJson("/api/admin/users/{$user->id}/toggle-role/duty_officer");

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['phone']);
        $response->assertJsonFragment(['User must have a phone number to be a Duty Officer.']);
    }

    public function test_duty_officer_role_can_be_assigned_to_user_with_phone()
    {
        // Create an admin user to perform the action
        $admin = User::factory()->create();
        $admin->assignRole('platform_admin');

        // Create a user with a phone number
        $user = User::factory()->create(['phone' => '+447999888777']);

        // Ensure the duty_officer role exists
        Role::firstOrCreate(['slug' => 'duty_officer'], ['name' => 'Duty Officer']);

        $response = $this->actingAs($admin)
            ->putJson("/api/admin/users/{$user->id}/toggle-role/duty_officer");

        $response->assertStatus(200);
        $this->assertTrue($user->fresh()->hasRole('duty_officer'));
    }
}
