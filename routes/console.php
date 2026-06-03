<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

// withoutOverlapping() prevents a slow run (notifications call external SMS/email/Slack
// APIs synchronously) from being started a second time by the next minute's tick, which
// could otherwise race on incident creation and send duplicate alerts.
//
// The explicit expiry is critical: withoutOverlapping defaults to a 24-HOUR lock. If a
// run were ever killed mid-execution (OOM, deploy, crash) the stale lock would silently
// block ALL subsequent overdue checks for a full day — catastrophic for a safety system.
// A short expiry means a crashed lock self-heals within minutes. Overlap is already safe
// because trigger/imminent/escalation each commit their guard (status / warned_at /
// escalated_at) BEFORE notifying, so a concurrent run skips work that's already done.
Schedule::command('callouts:check-overdue')->everyMinute()->withoutOverlapping(5);
Schedule::command('shifts:notify-started')->everyMinute()->withoutOverlapping(5);
Schedule::command('callouts:purge-sensitive-data')->daily();

// Monitor the monitor: alert if the independent backup watchdog drifts out of sync,
// becomes unreachable, or if any active callout is missing backup coverage.
Schedule::command('callouts:check-watchdog-sync')->everyFifteenMinutes()->withoutOverlapping(10);

// Monthly test alert at 12:00 on the 1st to verify watchdog system is working
Schedule::command('watchdog:test-alert')->monthlyOn(1, '12:00');

// Ping Better Stack heartbeat every minute. If this stops, Better Stack will alert us
// that the scheduler is down — catching cron/environment failures early.
Schedule::call(function () {
    $url = config('services.betterstack.heartbeat_url');
    if ($url) {
        Http::timeout(5)->get($url);
    }
})->everyMinute()->name('betterstack-heartbeat')->withoutOverlapping(5);
