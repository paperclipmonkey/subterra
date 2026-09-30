<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\Incident;
use App\Models\OnCallShift;
use App\Models\User;
use App\Services\GcpWatchdogService;
use App\Services\IncidentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The GCP watchdog is the independent backup: it alerts if a callout is still
 * unacknowledged 15 minutes after callout_time. It must stay armed when our own
 * alerts are merely sent, and only stand down once a duty officer acknowledges.
 */
class WatchdogLifecycleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function triggering_an_overdue_callout_leaves_the_watchdog_armed(): void
    {
        Carbon::setTestNow('2025-01-01 12:00:00');
        Notification::fake();
        $watchdog = Mockery::mock(GcpWatchdogService::class);
        $watchdog->shouldNotReceive('cancel');
        $this->app->instance(GcpWatchdogService::class, $watchdog);

        $do = User::factory()->dutyOfficer()->create();
        OnCallShift::create(['user_id' => $do->id, 'start_at' => now()->startOfDay(), 'end_at' => now()->endOfDay()]);
        $callout = Callout::factory()->create([
            'cave_id' => Cave::factory()->create()->id,
            'status' => 'active',
            'callout_time' => now()->subMinute(),
        ]);

        $this->artisan('callouts:check-overdue')->assertExitCode(0);

        $this->assertSame('triggered', $callout->fresh()->status);
    }

    #[Test]
    public function acknowledging_the_incident_stands_the_watchdog_down(): void
    {
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['id' => 'INC001', 'callout_id' => $callout->id, 'status' => 'open']);

        $watchdog = Mockery::mock(GcpWatchdogService::class);
        $watchdog->shouldReceive('cancel')->once()->withArgs(fn ($c) => $c->is($callout))->andReturn(true);
        $this->app->instance(GcpWatchdogService::class, $watchdog);

        app(IncidentService::class)->acknowledge($incident, User::factory()->dutyOfficer()->create(), 'test');

        $this->assertSame('managed', $incident->fresh()->status);
    }

    #[Test]
    public function later_status_changes_do_not_cancel_again(): void
    {
        $callout = Callout::factory()->create(['status' => 'triggered']);
        $incident = Incident::create(['id' => 'INC002', 'callout_id' => $callout->id, 'status' => 'managed']);

        $watchdog = Mockery::mock(GcpWatchdogService::class);
        $watchdog->shouldNotReceive('cancel');
        $this->app->instance(GcpWatchdogService::class, $watchdog);

        $incident->update(['status' => 'resolved']);
    }
}
