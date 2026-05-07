<?php

namespace App\Listeners;

use App\Events\TripCreated;
use App\Mail\TripStartedDONotification;
use App\Models\OnCallShift;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendTripStartedDONotification
{
    public function handle(TripCreated $event): void
    {
        $trip = $event->trip;
        $creator = $event->user;

        // Find the on-call shift covering the trip start time
        $shift = OnCallShift::with('user')
            ->where('notify_do', true)
            ->covering($trip->start_time)
            ->first();

        if (!$shift || !$shift->user?->email) {
            return;
        }

        try {
            Mail::to($shift->user->email)->send(new TripStartedDONotification($trip, $creator));
        } catch (\Exception $e) {
            Log::error('Failed to send trip DO notification: '.$e->getMessage());
        }
    }
}
