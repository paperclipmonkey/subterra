<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Booking $booking): bool
    {
        if ($user->id === $booking->user_id) {
            return true;
        }

        if ($user->hasRole('platform_admin')) {
            return true;
        }

        return $booking->permit->officers()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, Booking $booking): bool
    {
        if ($user->hasRole('platform_admin')) {
            return true;
        }

        return $booking->permit->officers()->where('user_id', $user->id)->exists();
    }

    public function reject(User $user, Booking $booking): bool
    {
        return $this->approve($user, $booking);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->id === $booking->user_id) {
            return in_array($booking->status, ['pending', 'approved']);
        }

        if ($user->hasRole('platform_admin')) {
            return true;
        }

        return $booking->permit->officers()->where('user_id', $user->id)->exists();
    }
}
