<?php

namespace App\Mail;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TripTaggedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public $trip;
    public $user;
    public $creator;

    public function __construct(Trip $trip, User $user, User $creator)
    {
        $this->trip = $trip;
        $this->user = $user;
        $this->creator = $creator;
    }

    public function build()
    {
        return $this->subject('You have been tagged in a trip')
            ->view('emails.trip_tagged')
            ->with([
                'trip' => $this->trip,
                'user' => $this->user,
                'creator' => $this->creator,
            ]);
    }
}
