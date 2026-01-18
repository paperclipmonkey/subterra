<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('callouts:check-overdue')->everyMinute();
