<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Cave;
use App\Services\GcpWatchdogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckWatchdogSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cave::factory()->create();
    }

    private function mockWatchdogCount(int $count): void
    {
        $this->mock(GcpWatchdogService::class, function ($mock) use ($count) {
            $mock->shouldReceive('getActiveWatchdogCount')->andReturn($count);
        });
    }

    public function test_reports_in_sync_when_counts_match_and_all_covered()
    {
        $this->mockWatchdogCount(1);

        Callout::factory()->create([
            'status' => 'active',
            'watchdog_registered_at' => now(),
        ]);

        $this->artisan('callouts:check-watchdog-sync')
            ->expectsOutputToContain('in sync')
            ->assertExitCode(0);
    }

    public function test_alerts_when_sms_credit_is_low()
    {
        // Watchdog healthy + no active callouts, so the only alert is the low credit.
        $this->mockWatchdogCount(0);

        Config::set('services.twilio.sid', 'AC');
        Config::set('services.twilio.token', 'tok');
        Config::set('services.textmagic.username', null);
        Config::set('services.textmagic.api_key', null);
        Config::set('callouts.balance.primary_min', 5);
        Config::set('callouts.balance.cache_seconds', 0);
        Cache::flush();
        Http::fake(['api.twilio.com/*' => Http::response(['balance' => '0.50', 'currency' => 'USD'])]);

        $this->artisan('callouts:check-watchdog-sync')
            ->expectsOutputToContain('SMS credit is LOW')
            ->assertExitCode(0);
    }

    public function test_alerts_when_watchdog_count_diverges()
    {
        // Subterra has one active callout, but the watchdog is tracking none.
        $this->mockWatchdogCount(0);

        Callout::factory()->create([
            'status' => 'active',
            'watchdog_registered_at' => now(),
        ]);

        $this->artisan('callouts:check-watchdog-sync')
            ->expectsOutputToContain('OUT OF SYNC')
            ->assertExitCode(0);
    }

    public function test_alerts_when_watchdog_unreachable()
    {
        $this->mockWatchdogCount(-1);

        Callout::factory()->create([
            'status' => 'active',
            'watchdog_registered_at' => now(),
        ]);

        $this->artisan('callouts:check-watchdog-sync')
            ->expectsOutputToContain('UNREACHABLE')
            ->assertExitCode(0);
    }

    public function test_alerts_when_active_callout_lacks_backup_coverage()
    {
        // Counts match, but the active callout never registered with the watchdog.
        $this->mockWatchdogCount(1);

        Callout::factory()->create([
            'status' => 'active',
            'watchdog_registered_at' => null,
        ]);

        $this->artisan('callouts:check-watchdog-sync')
            ->expectsOutputToContain('NO backup watchdog coverage')
            ->assertExitCode(0);
    }

    public function test_triggered_callouts_are_excluded_from_the_active_count()
    {
        // Triggered callouts have had their watchdog cancelled, so only ACTIVE callouts
        // should be compared against the watchdog count.
        $this->mockWatchdogCount(1);

        Callout::factory()->create([
            'status' => 'active',
            'watchdog_registered_at' => now(),
        ]);
        Callout::factory()->create([
            'status' => 'triggered',
            'watchdog_registered_at' => now(),
        ]);

        $this->artisan('callouts:check-watchdog-sync')
            ->expectsOutputToContain('in sync')
            ->assertExitCode(0);
    }

    public function test_no_alert_when_watchdog_unconfigured_and_no_active_callouts()
    {
        // -2 = not configured. With nothing relying on the backup, this is not an alert.
        $this->mockWatchdogCount(-2);

        $this->artisan('callouts:check-watchdog-sync')
            ->expectsOutputToContain('in sync')
            ->assertExitCode(0);
    }
}
