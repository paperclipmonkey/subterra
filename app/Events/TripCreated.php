<?php

namespace App\Events;

use App\Models\Trip;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TripCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $trip;
    public $user;

    public function __construct(Trip $trip, $user)
    {
        $this->trip = $trip;
        $this->user = $user;
    }
}
