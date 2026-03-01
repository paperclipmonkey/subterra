<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SendTestWatchdogAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gcp_watchdog.url' => 'https://mock-watchdog.test']);
        config(['services.gcp_watchdog.api_key' => 'test-key']);
        config(['services.gcp_watchdog.test_phone' => '+447911123456']);
        config(['services.gcp_watchdog.test_email' => 'test@example.com']);
    }

    public function test_it_sends_test_alert_successfully()
    {
        Http::fake([
            'https://mock-watchdog.test/watchdog' => Http::response(['message' => 'success'], 201),
        ]);

        $this->artisan('watchdog:test-alert')
            ->expectsOutputToContain('Creating test watchdog callout...')
            ->expectsOutputToContain('✅ Test watchdog registered successfully!')
            ->assertExitCode(0);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://mock-watchdog.test/watchdog' &&
                   $request->method() === 'POST' &&
                   $request->hasHeader('X-Watchdog-Key', 'test-key') &&
                   isset($request['callout_id']) &&
                   str_starts_with($request['callout_id'], 'TEST-');
        });
    }

    public function test_it_logs_error_and_returns_failure_on_http_error()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Watchdog API responded with error') &&
                       $context['status'] === 500;
            });

        Http::fake([
            'https://mock-watchdog.test/watchdog' => Http::response('Server Error', 500),
        ]);

        $this->artisan('watchdog:test-alert')
            ->expectsOutputToContain('❌ Failed to register test watchdog: 500')
            ->assertExitCode(1);
    }

    public function test_it_logs_error_and_returns_failure_on_exception()
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'Exception caught') &&
                       str_contains($context['exception'], 'Connection timeout');
            });

        Http::fake(function ($request) {
            throw new \Exception('Connection timeout');
        });

        $this->artisan('watchdog:test-alert')
            ->expectsOutputToContain('❌ Exception: Connection timeout')
            ->assertExitCode(1);
    }
}
