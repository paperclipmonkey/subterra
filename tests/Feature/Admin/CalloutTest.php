<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Callout;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class CalloutTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_live_operations_dashboard()
    {
        $admin = User::factory()->dutyOfficer()->create();

        // Create callouts with different times
        $c1 = Callout::factory()->create([
            'status' => 'active',
            'callout_time' => Carbon::now()->addHours(1),
        ]);
        $c2 = Callout::factory()->create([
            'status' => 'triggered', // Should also appear
            'callout_time' => Carbon::now()->subHour(),
        ]);
        $c3 = Callout::factory()->create([
            'status' => 'resolved', // Should NOT appear
            'callout_time' => Carbon::now()->subHours(5),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/callouts');

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data'); // c1 and c2

        // Verify Structure and PII stripping
        $data = $response->json('data');
        $this->assertArrayHasKey('team_size', $data[0]);
        $this->assertArrayNotHasKey('participants', $data[0]); // Ensure raw participants not returned
        $this->assertArrayHasKey('has_incident', $data[0]);
    }

    public function test_dashboard_exposes_configured_whatsapp_group_url()
    {
        Config::set('callouts.whatsapp_group_url', 'https://chat.whatsapp.com/TESTGROUP');
        $admin = User::factory()->dutyOfficer()->create();

        $this->actingAs($admin)
            ->getJson('/api/admin/callouts')
            ->assertStatus(200)
            ->assertJsonPath('whatsapp_group_url', 'https://chat.whatsapp.com/TESTGROUP');
    }

    public function test_non_admin_cannot_view_live_operations()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->getJson('/api/admin/callouts');

        $response->assertStatus(403); // Or 404/Redirect depending on middleware
    }

    public function test_guest_cannot_view_live_operations()
    {
        $response = $this->getJson('/api/admin/callouts');

        $response->assertStatus(401);
    }
}
