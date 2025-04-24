<?php

namespace App\Events;

use App\Models\Trip;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripParticipantTagged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $trip;
    public $user;
    public $creator;

    public function __construct(Trip $trip, User $user, User $creator)
    {
        $this->trip = $trip;
        $this->user = $user;
        $this->creator = $creator;
    }
}
