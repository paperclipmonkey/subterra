<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Events\CalloutCreated;
use App\Models\OnCallShift;
use App\Models\User;
use App\Services\CalloutService;
use App\Services\GcpWatchdogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Mockery\MockInterface;
use Spatie\SlackAlerts\Jobs\SendToSlackChannelJob;
use Tests\TestCase;

class CalloutServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalloutService $service;
    private User $user;
    private MockInterface $watchdogServiceMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watchdogServiceMock = Mockery::mock(GcpWatchdogService::class);
        $this->service = new CalloutService($this->watchdogServiceMock);
        $this->user = User::factory()->create(['phone' => '1234567890']);
    }

    private function shiftCovering(\Carbon\Carbon $time): void
    {
        $admin = User::factory()->admin()->create();
        OnCallShift::create([
            'user_id' => $admin->id,
            'start_at' => $time->copy()->subHour(),
            'end_at' => $time->copy()->addHour(),
        ]);
    }

    public function test_creates_callout_and_records_backup_coverage_when_watchdog_registers()
    {
        Mail::fake();
        Event::fake([CalloutCreated::class]);

        $tomorrowNoon = now()->addDay()->setHour(12)->setMinute(0);
        $this->shiftCovering($tomorrowNoon);

        // Backup is configured and registers successfully.
        $this->watchdogServiceMock->shouldReceive('isConfigured')->andReturn(true);
        $this->watchdogServiceMock->shouldReceive('register')->once()->andReturn('watchdog-123');

        $callout = $this->service->create($this->user, [
            'callout_time' => $tomorrowNoon->toDateTimeString(),
            'description' => 'Cave X, Deep Pitch, Me',
            'participants' => [
                ['name' => 'Bob', 'phone' => '999'],
            ],
        ]);

        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'description' => 'Cave X, Deep Pitch, Me',
            'status' => 'active',
        ]);
        $this->assertNotNull($callout->fresh()->watchdog_registered_at, 'Backup coverage should be recorded.');

        $this->assertDatabaseHas('callout_participants', [
            'callout_id' => $callout->id,
            'name' => 'Bob',
            'phone' => '999',
        ]);

        Event::assertDispatched(CalloutCreated::class, fn ($event) => $event->callout->id === $callout->id);
    }

    public function test_creates_callout_without_backup_when_watchdog_not_configured()
    {
        Mail::fake();

        $tomorrowNoon = now()->addDay()->setHour(12)->setMinute(0);
        $this->shiftCovering($tomorrowNoon);

        // Not configured (local/CI): registration is skipped, callout still created.
        $this->watchdogServiceMock->shouldReceive('isConfigured')->andReturn(false);
        $this->watchdogServiceMock->shouldNotReceive('register');

        $callout = $this->service->create($this->user, [
            'callout_time' => $tomorrowNoon->toDateTimeString(),
            'description' => 'No backup configured',
        ]);

        $this->assertDatabaseHas('callouts', ['id' => $callout->id, 'status' => 'active']);
        $this->assertNull($callout->fresh()->watchdog_registered_at);
    }

    public function test_rolls_back_callout_when_watchdog_registration_fails()
    {
        Mail::fake();

        $tomorrowNoon = now()->addDay()->setHour(12)->setMinute(0);
        $this->shiftCovering($tomorrowNoon);

        // Backup is configured but unreachable — registration returns null. Creation
        // must hard-fail and leave no callout behind (never watched by one system only).
        $this->watchdogServiceMock->shouldReceive('isConfigured')->andReturn(true);
        $this->watchdogServiceMock->shouldReceive('register')->once()->andReturn(null);

        try {
            $this->service->create($this->user, [
                'callout_time' => $tomorrowNoon->toDateTimeString(),
                'description' => 'Should roll back',
                'participants' => [
                    ['name' => 'Bob', 'phone' => '999'],
                ],
            ]);
            $this->fail('Expected callout creation to fail when the backup cannot be registered.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('backup safety system', $e->getMessage());
        }

        $this->assertDatabaseMissing('callouts', ['description' => 'Should roll back']);
        $this->assertDatabaseMissing('callout_participants', ['name' => 'Bob', 'phone' => '999']);
    }

    public function test_detects_missing_essential_config()
    {
        config(['callouts.required_config' => ['services.twilio.from', 'callouts.numbers.backup_sms']]);

        config(['services.twilio.from' => '+447000000000', 'callouts.numbers.backup_sms' => '+447111111111']);
        $this->assertSame([], $this->service->missingEssentialConfig());

        config(['callouts.numbers.backup_sms' => null]);
        $this->assertSame(['callouts.numbers.backup_sms'], $this->service->missingEssentialConfig());
    }

    public function test_create_is_blocked_when_essential_config_is_missing()
    {
        // A callout must not be created when the alerting system isn't configured.
        config([
            'callouts.enforce_config' => true,
            'callouts.required_config' => ['services.twilio.from'],
            'services.twilio.from' => null,
        ]);

        try {
            $this->service->create($this->user, [
                'callout_time' => now()->addDay()->setHour(12)->toDateTimeString(),
                'description' => 'Blocked by missing config',
            ]);
            $this->fail('Expected creation to be blocked when essential config is missing.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('alerting system is not fully configured', $e->getMessage());
        }

        $this->assertDatabaseMissing('callouts', ['description' => 'Blocked by missing config']);
    }

    public function test_create_is_blocked_when_sms_credit_is_below_minimum()
    {
        config([
            'callouts.enforce_config' => true,
            'callouts.required_config' => [],
            'services.twilio.sid' => 'AC',
            'services.twilio.token' => 'tok',
            'services.textmagic.username' => null,
            'services.textmagic.api_key' => null,
            'callouts.balance.primary_min' => 5,
            'callouts.balance.cache_seconds' => 0,
            'slack-alerts.webhook_urls.callouts-overdue' => 'https://hooks.slack.test/x',
        ]);
        Cache::flush();
        Queue::fake();
        Http::fake([
            'api.twilio.com/*' => Http::response(['balance' => '0.50', 'currency' => 'USD']),
        ]);

        try {
            $this->service->create($this->user, [
                'callout_time' => now()->addDay()->setHour(12)->toDateTimeString(),
                'description' => 'Blocked by low credit',
            ]);
            $this->fail('Expected creation to be blocked when SMS credit is too low.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('alerting credit is too low', $e->getMessage());
        }

        $this->assertDatabaseMissing('callouts', ['description' => 'Blocked by low credit']);
        // The block is reported to Slack as a loud, belt-and-braces error.
        Queue::assertPushed(SendToSlackChannelJob::class);
    }

    public function test_fails_when_no_admin_is_on_call()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No administrator is on-call');

        // Validation fails before any watchdog interaction.
        $this->service->create($this->user, [
            'callout_time' => now()->addDay()->setHour(12)->toDateTimeString(),
            'description' => 'Should Fail',
        ]);
    }

    public function test_triggers_callout_and_creates_incident()
    {
        $callout = \App\Models\Callout::factory()->create([
            'status' => 'active',
            'user_id' => $this->user->id,
        ]);

        $this->service->trigger($callout);

        $this->assertDatabaseHas('callouts', [
            'id' => $callout->id,
            'status' => 'triggered',
        ]);

        $this->assertDatabaseHas('incidents', [
            'callout_id' => $callout->id,
            'status' => 'open',
        ]);
    }
}
