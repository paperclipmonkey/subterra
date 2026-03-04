<?php

namespace App\Listeners;

use App\Events\CalloutCancelled;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendCalloutCancelledSlackAlert
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
    public function handle(CalloutCancelled $event): void
    {
        try {
            $callout = $event->callout;
            $user = $callout->user;
            $caveName = $callout->cave ? $callout->cave->name : 'Unknown Location';

            $msg = "✅ *CALLOUT CLOSED* by {$user->name} for *{$caveName}*.\nEveryone is out safe.";

            SlackAlert::to('callouts-closed')->message($msg);
        } catch (\Exception $e) {
            Log::error('Failed to send Callout Cancelled Slack alert: '.$e->getMessage());
        }
    }
}
