<?php

namespace App\Listeners;

use App\Events\TripParticipantTagged;
use App\Mail\TripTaggedMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Mail;

class SendTripTaggedEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(TripParticipantTagged $event)
    {
        if($event->user->id === $event->creator->id) {
            return;
        }
        Mail::to($event->user->email)->send(new TripTaggedMail($event->trip, $event->user, $event->creator));
    }
}
