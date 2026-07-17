<?php

declare(strict_types=1);

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
        // 1. Create a Duty Officer to verify they are included in the payload
        $dutyOfficer = User::factory()->create([
            'name' => 'Jane Admin',
            'phone' => '+447999888777',
            'email' => 'jane@example.com',
        ]);
        $dutyOfficer->assignRole('duty_officer');

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
            'car_parking' => 'Layby',
            'car_registration' => 'AB12 CDE',
            'team_details' => 'Team Alpha',
            'location_data' => ['lat' => 51.5, 'lng' => -0.1],
        ]);

        // Mock HTTP response
        Http::fake([
            'https://test-watchdog.run.app/watchdog' => Http::response(['message' => 'Success', 'callout_id' => $callout->id], 200),
        ]);

        // Call register
        $result = $this->service->register($callout);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($callout->id, $result);

        // Verify HTTP request was made
        Http::assertSent(function ($request) use ($callout, $dutyOfficer) {
            $data = $request->data();

            // Validate duty officers are accurately fetched and appended
            $hasJane = collect($data['duty_officers'])->contains(function ($do) use ($dutyOfficer) {
                return $do['email'] === $dutyOfficer->email && $do['phone'] === $dutyOfficer->phone;
            });

            return $request->url() === 'https://test-watchdog.run.app/watchdog'
                && $request->hasHeader('X-Watchdog-Key', 'test-key')
                && $data['callout_id'] === $callout->id
                && isset($data['callout_time'])
                && isset($data['user'])
                && $hasJane
                && $data['car_parking'] === 'Layby'
                && $data['car_registration'] === 'AB12 CDE'
                && $data['team_details'] === 'Team Alpha'
                && $data['location_data']['lat'] === 51.5;
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

        Http::assertSent(function ($request) use ($callout) {
            return $request->method() === 'DELETE'
                && str_contains($request->url(), '/watchdog')
                && $request->hasHeader('X-Watchdog-Key', 'test-key')
                && $request->url() === 'https://test-watchdog.run.app/watchdog?callout_id='.$callout->id;
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

    public function test_active_watchdog_count_excludes_monthly_test_entries()
    {
        // TEST- watchdogs exist only on the watchdog side by design; counting them
        // would make the sync monitor report a false OUT OF SYNC.
        Http::fake([
            '*/watchdog' => Http::response([
                'count' => 3,
                'data' => [
                    ['callout_id' => 'abc-123'],
                    ['callout_id' => 'TEST-2026-07-01-120032'],
                    ['callout_id' => 'def-456'],
                ],
            ], 200),
        ]);

        $this->assertEquals(2, $this->service->getActiveWatchdogCount());
    }

    public function test_active_watchdog_count_falls_back_to_count_field_when_data_missing()
    {
        Http::fake([
            '*/watchdog' => Http::response(['count' => 4], 200),
        ]);

        $this->assertEquals(4, $this->service->getActiveWatchdogCount());
    }
}
