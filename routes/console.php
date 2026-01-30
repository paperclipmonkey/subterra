<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('callouts:check-overdue')->everyMinute();
Schedule::command('callouts:purge-sensitive-data')->daily();

// Monthly test alert at 12:00 on the 1st to verify watchdog system is working
Schedule::command('watchdog:test-alert')->monthlyOn(1, '12:00');
