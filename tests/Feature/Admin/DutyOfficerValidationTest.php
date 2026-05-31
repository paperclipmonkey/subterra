<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutyOfficerValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_assign_duty_officer_role_if_user_has_no_phone()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['phone' => null]);

        $response = $this->actingAs($admin)
            ->putJson(route('admin.users.toggle-role', ['user_without_scopes' => $user->id, 'role' => 'duty_officer']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        $this->assertFalse($user->fresh()->hasRole('duty_officer'));
    }

    public function test_can_assign_duty_officer_role_if_user_has_phone()
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['phone' => '+447999999999']);

        $response = $this->actingAs($admin)
            ->putJson(route('admin.users.toggle-role', ['user_without_scopes' => $user->id, 'role' => 'duty_officer']));

        $response->assertStatus(200);

        $this->assertTrue($user->fresh()->hasRole('duty_officer'));
    }

    public function test_duty_officer_cannot_remove_their_phone_number()
    {
        $do = User::factory()->dutyOfficer()->create(['phone' => '+447999999999']);

        // Try to update profile with null phone
        $response = $this->actingAs($do)
            ->putJson(route('users.me.update'), [
                'name' => 'New Name',
                'phone' => null,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone']);

        $this->assertNotNull($do->fresh()->phone);
    }

    public function test_duty_officer_can_change_their_phone_number_to_valid_number()
    {
        $do = User::factory()->dutyOfficer()->create(['phone' => '+447999999999']);

        $response = $this->actingAs($do)
            ->putJson(route('users.me.update'), [
                'name' => 'New Name',
                'phone' => '+447888888888',
            ]);

        $response->assertStatus(200);
        $this->assertEquals('+447888888888', $do->fresh()->phone);
    }
}
