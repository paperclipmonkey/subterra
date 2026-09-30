<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->approved;
    }

    public function view(User $user, Trip $trip): bool
    {
        return $user->approved;
    }

    public function create(User $user): bool
    {
        return $user->approved;
    }

    public function update(User $user, Trip $trip): bool
    {
        // Participants, or platform admins (is_admin covers every staff role)
        return $user->hasRole('platform_admin') || $trip->participants()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Trip $trip): bool
    {
        // Participants, or platform admins (is_admin covers every staff role)
        return $user->hasRole('platform_admin') || $trip->participants()->where('user_id', $user->id)->exists();
    }
}
