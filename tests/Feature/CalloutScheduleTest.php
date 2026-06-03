<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Guards the scheduling guarantees that the callout safety system depends on. These are
 * easy to accidentally weaken (e.g. dropping withoutOverlapping, or reverting to the
 * 24-hour default lock expiry), so they are pinned here.
 */
class CalloutScheduleTest extends TestCase
{
    private function event(string $needle): ?Event
    {
        // Ensure the schedule defined in routes/console.php is loaded into the singleton.
        Artisan::call('schedule:list');

        $schedule = app(Schedule::class);

        foreach ($schedule->events() as $event) {
            if (str_contains((string) $event->command, $needle)) {
                return $event;
            }
        }

        return null;
    }

    public function test_check_overdue_uses_overlap_protection_with_short_expiry()
    {
        $event = $this->event('callouts:check-overdue');

        $this->assertNotNull($event, 'callouts:check-overdue must be scheduled');
        $this->assertTrue($event->withoutOverlapping, 'callouts:check-overdue must use withoutOverlapping()');
        $this->assertLessThanOrEqual(
            5,
            $event->expiresAt,
            'Lock expiry must be short — the 24h default would stall the safety check for a day if a run is killed.'
        );
    }

    public function test_notify_started_shifts_uses_overlap_protection_with_short_expiry()
    {
        $event = $this->event('shifts:notify-started');

        $this->assertNotNull($event, 'shifts:notify-started must be scheduled');
        $this->assertTrue($event->withoutOverlapping);
        $this->assertLessThanOrEqual(5, $event->expiresAt);
    }

    public function test_watchdog_sync_monitor_is_scheduled_with_overlap_protection()
    {
        $event = $this->event('callouts:check-watchdog-sync');

        $this->assertNotNull($event, 'callouts:check-watchdog-sync must be scheduled');
        $this->assertTrue($event->withoutOverlapping);
        $this->assertLessThanOrEqual(15, $event->expiresAt);
    }
}
