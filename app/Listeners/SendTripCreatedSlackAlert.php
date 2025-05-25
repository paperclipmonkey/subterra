<?php

namespace App\Listeners;

use App\Events\TripCreated;
use Spatie\SlackAlerts\Facades\SlackAlert;
use Illuminate\Support\Facades\Log;

class SendTripCreatedSlackAlert
{
    public function handle(TripCreated $event)
    {
        $trip = $event->trip;
        $user = $event->user;
        try {
            SlackAlert::to('trips')->message("A new trip has been created: <https://subterra.world/trip/{$trip->id}|{$trip->name}> to {$trip->entrance->name} by {$user->name}");
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert: ' . $e->getMessage());
        }
    }
}
