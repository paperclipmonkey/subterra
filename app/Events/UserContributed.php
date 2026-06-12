<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a user makes a non-trip contribution that can count toward a
 * medal (e.g. submitting a suggested edit). Triggers the medal check for
 * actions that don't go through TripParticipantTagged.
 */
class UserContributed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }
}
