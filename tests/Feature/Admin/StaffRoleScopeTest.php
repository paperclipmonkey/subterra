<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * is_admin is true for every staff role. Only platform admins may edit or delete
 * other people's accounts and trips; duty officers, access officers and data
 * admins are staff for their own area only.
 */
class StaffRoleScopeTest extends TestCase
{
    use RefreshDatabase;

    public static function narrowStaffRoles(): array
    {
        return [
            'duty officer' => ['duty_officer'],
            'access officer' => ['access_officer'],
            'data admin' => ['data_admin'],
        ];
    }

    private function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    #[Test]
    #[DataProvider('narrowStaffRoles')]
    public function narrow_staff_cannot_delete_or_edit_another_account(string $role): void
    {
        $victim = User::factory()->create(['name' => 'Original Name']);
        $staff = $this->staff($role);

        $this->actingAs($staff)->deleteJson("/api/users/{$victim->id}")->assertForbidden();
        $this->actingAs($staff)->putJson("/api/users/{$victim->id}", ['name' => 'Hijacked'])->assertForbidden();

        $this->assertSame('Original Name', $victim->fresh()->name);
    }

    #[Test]
    #[DataProvider('narrowStaffRoles')]
    public function narrow_staff_cannot_edit_or_delete_someone_elses_trip(string $role): void
    {
        $trip = Trip::factory()->create(['name' => 'Original Trip']);
        $trip->participants()->attach(User::factory()->create());
        $staff = $this->staff($role);

        $this->actingAs($staff)->putJson("/api/trips/{$trip->short_id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/trips/{$trip->short_id}")->assertForbidden();

        $this->assertSame('Original Trip', $trip->fresh()->name);
    }

    #[Test]
    public function platform_admins_can_still_manage_accounts_and_trips(): void
    {
        $admin = User::factory()->admin()->create();
        $trip = Trip::factory()->create();
        $trip->participants()->attach(User::factory()->create());

        $this->actingAs($admin)->putJson("/api/trips/{$trip->short_id}", ['name' => 'Moderated'])->assertOk();
        $this->actingAs($admin)->deleteJson('/api/users/'.User::factory()->create()->id)->assertOk();
    }
}
