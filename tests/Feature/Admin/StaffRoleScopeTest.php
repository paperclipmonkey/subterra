<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Callout;
use App\Models\Collection;
use App\Models\Hut;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * is_admin is true for every staff role. Only platform admins may edit or delete
 * other people's accounts, trips, huts and collections, and only duty officers
 * (and platform admins) may stand down someone else's callout; the other staff
 * roles are staff for their own area only.
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
    #[DataProvider('narrowStaffRoles')]
    public function narrow_staff_cannot_create_edit_or_delete_huts(string $role): void
    {
        $hut = Hut::factory()->create(['name' => 'Original Hut']);
        $staff = $this->staff($role);

        $this->actingAs($staff)->postJson('/api/huts', ['name' => 'New Hut'])->assertForbidden();
        $this->actingAs($staff)->putJson("/api/huts/{$hut->id}", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/huts/{$hut->id}")->assertForbidden();

        $this->assertSame('Original Hut', $hut->fresh()->name);
    }

    #[Test]
    #[DataProvider('narrowStaffRoles')]
    public function narrow_staff_cannot_edit_or_delete_someone_elses_collection(string $role): void
    {
        $collection = Collection::factory()->create(['name' => 'Original Collection']);
        $staff = $this->staff($role);

        $this->actingAs($staff)->putJson("/api/collections/{$collection->slug}", ['name' => 'Hijacked'])->assertForbidden();
        $this->actingAs($staff)->deleteJson("/api/collections/{$collection->slug}")->assertForbidden();

        $this->assertSame('Original Collection', $collection->fresh()->name);
    }

    public static function nonDutyStaffRoles(): array
    {
        return [
            'access officer' => ['access_officer'],
            'data admin' => ['data_admin'],
        ];
    }

    #[Test]
    #[DataProvider('nonDutyStaffRoles')]
    public function non_duty_staff_cannot_cancel_someone_elses_callout(string $role): void
    {
        $callout = Callout::factory()->create(['status' => 'active']);

        $this->actingAs($this->staff($role))
            ->postJson("/api/callouts/{$callout->id}/cancel")
            ->assertForbidden();

        $this->assertSame('active', $callout->fresh()->status);
    }

    #[Test]
    public function duty_officers_can_cancel_someone_elses_callout(): void
    {
        $callout = Callout::factory()->create(['status' => 'active']);

        $this->actingAs(User::factory()->dutyOfficer()->create())
            ->postJson("/api/callouts/{$callout->id}/cancel")
            ->assertOk();

        $this->assertSame('cancelled', $callout->fresh()->status);
    }

    #[Test]
    public function platform_admins_can_still_manage_huts_and_collections(): void
    {
        $admin = User::factory()->admin()->create();
        $hut = Hut::factory()->create();
        $collection = Collection::factory()->create();

        $this->actingAs($admin)->putJson("/api/huts/{$hut->id}", ['name' => 'Moderated Hut'])->assertOk();
        $this->actingAs($admin)->putJson("/api/collections/{$collection->slug}", ['name' => 'Moderated'])->assertOk();
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
