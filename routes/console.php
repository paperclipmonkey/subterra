<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('callouts:check-overdue')->everyMinute();
Schedule::command('callouts:purge-sensitive-data')->daily();
