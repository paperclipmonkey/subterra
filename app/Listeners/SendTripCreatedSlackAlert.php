<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TripCreated;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendTripCreatedSlackAlert
{
    public function handle(TripCreated $event)
    {
        $trip = $event->trip;
        $user = $event->user;
        try {
            SlackAlert::to('trips')->message("A new trip has been created: <https://subterra.world/trip/{$trip->id}|{$trip->name}> to {$trip->entrance->name} by {$user->name}");
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert: '.$e->getMessage());
        }
    }
}
