<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Trip;
use App\Models\User;

class TripTaggedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

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
