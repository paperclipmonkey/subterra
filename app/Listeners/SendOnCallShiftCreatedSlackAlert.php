<?php

namespace App\Listeners;

use App\Events\OnCallShiftCreated;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendOnCallShiftCreatedSlackAlert
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OnCallShiftCreated $event): void
    {
        try {
            $shift = $event->shift;
            $user = $shift->user;
            $start = $shift->start_at->format('d/m H:i');
            $end = $shift->end_at->format('d/m H:i');

            $msg = "🛡️ *DUTY OFFICER UPDATE*\n{$user->name} is now ON CALL.\nFrom: {$start}\nUntil: {$end}.";

            SlackAlert::to('callouts')->message($msg);
        } catch (\Exception $e) {
            Log::error('Failed to send On Call Shift Slack alert: '.$e->getMessage());
        }
    }
}
