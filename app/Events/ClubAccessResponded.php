<?php

namespace App\Events;

use App\Models\Club;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClubAccessResponded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $club;
    public $user;
    public $status; // 'approved' or 'rejected'

    public function __construct(Club $club, User $user, string $status)
    {
        $this->club = $club;
        $this->user = $user;
        $this->status = $status;
    }
}