<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DutyOfficerApiTest extends TestCase
{
    use RefreshDatabase;

    public function setUp(): void
    {
        parent::setUp();
        // Manually create roles since seeder is missing
        \App\Models\Role::firstOrCreate(['slug' => 'platform_admin'], ['name' => 'Platform Admin']);
        \App\Models\Role::firstOrCreate(['slug' => 'duty_officer'], ['name' => 'Duty Officer']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function duty_officer_can_list_potential_officers()
    {
        $officer = User::factory()->create();
        $officer->assignRole('duty_officer');

        $otherOfficer = User::factory()->create(['name' => 'Jane DO']);
        $otherOfficer->assignRole('duty_officer');

        $admin = User::factory()->create(['name' => 'Super Admin']);
        $admin->assignRole('platform_admin');

        $regularUser = User::factory()->create(['name' => 'Regular Member']);

        $response = $this->actingAs($officer)->getJson('/api/admin/duty-officers');

        $response->assertStatus(200);

        $data = $response->json('data');
        $names = collect($data)->pluck('name');

        $this->assertTrue($names->contains($officer->name));
        $this->assertTrue($names->contains('Jane DO'));
        $this->assertTrue($names->contains('Super Admin'));
        $this->assertFalse($names->contains('Regular Member'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function regular_user_cannot_list_officers()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/admin/duty-officers');

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_admin_can_list_officers()
    {
        $admin = User::factory()->create();
        $admin->assignRole('platform_admin');

        $response = $this->actingAs($admin)->getJson('/api/admin/duty-officers');

        $response->assertStatus(200);
    }
}
