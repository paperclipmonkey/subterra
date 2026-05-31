<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Callout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WatchdogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Set a dummy URL for testing
        config(['services.gcp_watchdog.url' => 'https://mock-watchdog.test']);
        config(['services.gcp_watchdog.api_key' => 'test-key']);
    }

    public function test_admin_can_view_dashboard_with_watchdog_count()
    {
        $admin = User::factory()->dutyOfficer()->create();

        Callout::factory()->create(['status' => 'active']);
        Callout::factory()->create(['status' => 'active']);

        Http::fake([
            'https://mock-watchdog.test/watchdog' => Http::response(['count' => 2], 200),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/callouts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'watchdog_count',
            'system_count',
            'is_watchdog_out_of_sync',
        ]);

        $this->assertEquals(2, $response->json('watchdog_count'));
        $this->assertEquals(2, $response->json('system_count'));
        $this->assertFalse($response->json('is_watchdog_out_of_sync'));
    }

    public function test_it_handles_watchdog_communication_error()
    {
        $admin = User::factory()->dutyOfficer()->create();

        Http::fake([
            'https://mock-watchdog.test/watchdog' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/callouts');

        $response->assertStatus(200);
        $this->assertEquals(-1, $response->json('watchdog_count'));
    }

    public function test_it_handles_missing_watchdog_configuration()
    {
        $admin = User::factory()->dutyOfficer()->create();
        config(['services.gcp_watchdog.url' => null]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/callouts');

        $response->assertStatus(200);
        $this->assertEquals(-2, $response->json('watchdog_count'));
    }

    public function test_it_flags_out_of_sync_status()
    {
        $admin = User::factory()->dutyOfficer()->create();
        Callout::factory()->create(['status' => 'active']);

        Http::fake([
            'https://mock-watchdog.test/watchdog' => Http::response(['count' => 5], 200),
        ]);

        $response = $this->actingAs($admin)
            ->getJson('/api/admin/callouts');

        $response->assertStatus(200);
        $this->assertEquals(5, $response->json('watchdog_count'));
        $this->assertEquals(1, $response->json('system_count'));
        $this->assertTrue($response->json('is_watchdog_out_of_sync'));
    }

    public function test_admin_can_send_test_callout_to_watchdog()
    {
        $admin = User::factory()->dutyOfficer()->create();

        Http::fake([
            'https://mock-watchdog.test/watchdog/test' => Http::response(['message' => 'success'], 200),
        ]);

        $response = $this->actingAs($admin)
            ->postJson('/api/admin/callouts/test-watchdog');

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Test callout sent to watchdog successfully.']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://mock-watchdog.test/watchdog/test' &&
                   $request->hasHeader('X-Watchdog-Key', 'test-key');
        });
    }

    public function test_non_admin_cannot_send_test_callout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson('/api/admin/callouts/test-watchdog');

        $response->assertStatus(403);
    }
}
