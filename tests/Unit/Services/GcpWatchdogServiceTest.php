<?php

namespace Tests\Unit\Services;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\User;
use App\Services\GcpWatchdogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GcpWatchdogServiceTest extends TestCase
{
    use RefreshDatabase;

    private GcpWatchdogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.gcp_watchdog.url' => 'https://test-watchdog.run.app']);
        config(['services.gcp_watchdog.api_key' => 'test-key']);

        $this->service = new GcpWatchdogService();
    }

    public function test_register_sends_correct_payload()
    {
        // Create test data
        $user = User::factory()->create([
            'name' => 'John Doe',
            'phone' => '+1234567890',
            'email' => 'john@example.com',
        ]);

        $cave = Cave::factory()->create(['name' => 'Test Cave']);

        $callout = Callout::factory()->create([
            'user_id' => $user->id,
            'cave_id' => $cave->id,
            'callout_time' => now()->addHours(2),
            'trip_plan' => 'Test trip plan',
        ]);

        // Mock HTTP response
        Http::fake([
            '*/watchdog' => Http::response(['message' => 'Success', 'callout_id' => $callout->id], 200),
        ]);

        // Call register
        $result = $this->service->register($callout);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($callout->id, $result);

        // Verify HTTP request was made
        Http::assertSent(function ($request) use ($callout) {
            return $request->url() === 'https://test-watchdog.run.app/watchdog'
                && $request->hasHeader('X-Watchdog-Key', 'test-key')
                && $request['callout_id'] === $callout->id
                && isset($request['callout_time'])
                && isset($request['user']);
        });
    }

    public function test_register_handles_http_errors()
    {
        $callout = Callout::factory()->create();

        // Mock failed HTTP response
        Http::fake([
            '*/watchdog' => Http::response(['error' => 'Server error'], 500),
        ]);

        $result = $this->service->register($callout);

        // Should return null on failure
        $this->assertNull($result);
    }

    public function test_cancel_sends_delete_request()
    {
        $callout = Callout::factory()->create();

        Http::fake([
            '*/watchdog*' => Http::response(['message' => 'Cancelled'], 200),
        ]);

        $result = $this->service->cancel($callout);

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/watchdog')
                && $request->hasHeader('X-Watchdog-Key', 'test-key')
                && isset($request['callout_id']);
        });
    }

    public function test_cancel_handles_http_errors()
    {
        $callout = Callout::factory()->create();

        Http::fake([
            '*/watchdog*' => Http::response(['error' => 'Not found'], 404),
        ]);

        $result = $this->service->cancel($callout);

        $this->assertFalse($result);
    }
}
