<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\TripCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Spatie\SlackAlerts\Facades\SlackAlert;

class SendTripCreatedSlackAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TripCreated $event)
    {
        $trip = $event->trip;
        $user = $event->user;
        try {
            // Trips are addressed by short_id on the frontend (/trips/{short_id})
            SlackAlert::to('trips')->message("A new trip has been created: <https://subterra.world/trips/{$trip->short_id}|{$trip->name}> to {$trip->entrance->name} by {$user->name}");
        } catch (\Exception $e) {
            Log::error('Failed to send Slack alert: '.$e->getMessage());
        }
    }
}
