<?php

namespace App\Listeners;

use App\Events\CalloutCreated;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendCalloutCreatedSlackAlert
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
    public function handle(CalloutCreated $event): void
    {
        try {
            $callout = $event->callout;
            $user = $callout->user; // Helper relation
            $caveName = $callout->cave ? $callout->cave->name : 'Unknown Location';
            $time = $callout->callout_time->format('d/m H:i');

            $msg = "📢 *NEW CALLOUT* posted by {$user->name} for *{$caveName}*.\nDue: {$time}.\n<".url('/admin/callout').'|View Callouts>';

            SlackAlert::to('callouts')->message($msg);
        } catch (\Exception $e) {
            Log::error('Failed to send Callout Created Slack alert: '.$e->getMessage());
        }
    }
}
