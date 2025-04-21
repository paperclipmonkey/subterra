<?php

namespace App\Events;

use App\Models\Club;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClubAccessRequested
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $club;
    public $user;

    public function __construct(Club $club, User $user)
    {
        $this->club = $club;
        $this->user = $user;
    }
}