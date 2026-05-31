<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Club;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClubAccessResponded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $club;
    public $user;
    public $status; // 'approved' or 'rejected'
    public $reason;

    public function __construct(Club $club, User $user, string $status, ?string $reason = null)
    {
        $this->club = $club;
        $this->user = $user;
        $this->status = $status;
        $this->reason = $reason;
    }
}
