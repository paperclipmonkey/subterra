<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Schedule::command('callouts:check-overdue')->everyMinute();
Schedule::command('shifts:notify-started')->everyMinute();
Schedule::command('callouts:purge-sensitive-data')->daily();

// Monthly test alert at 12:00 on the 1st to verify watchdog system is working
Schedule::command('watchdog:test-alert')->monthlyOn(1, '12:00');

// Ping Better Stack heartbeat every minute. If this stops, Better Stack will alert us
// that the scheduler is down — catching cron/environment failures early.
Schedule::call(function () {
    $url = config('services.betterstack.heartbeat_url');
    if ($url) {
        Http::timeout(5)->get($url);
    }
})->everyMinute()->name('betterstack-heartbeat')->withoutOverlapping();
