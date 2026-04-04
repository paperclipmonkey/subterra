<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Permit;
use App\Models\User;

class PermitPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Permit $permit): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['access_officer', 'platform_admin']);
    }

    public function update(User $user, Permit $permit): bool
    {
        if ($user->hasRole('platform_admin')) {
            return true;
        }

        return $permit->officers()->where('user_id', $user->id)->exists();
    }

    public function delete(User $user, Permit $permit): bool
    {
        if ($user->hasRole('platform_admin')) {
            return true;
        }

        return $permit->officers()->where('user_id', $user->id)->exists();
    }
}
