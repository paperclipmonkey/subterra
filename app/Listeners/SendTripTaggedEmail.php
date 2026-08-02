<?php

declare(strict_types=1);

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
        if ($event->user->id === $event->creator->id) {
            return;
        }

        // Respect the recipient's "email me when I'm tagged" preference
        if (!$event->user->email_tagged) {
            return;
        }

        Mail::to($event->user->email)->send(new TripTaggedMail($event->trip, $event->user, $event->creator));
    }
}
