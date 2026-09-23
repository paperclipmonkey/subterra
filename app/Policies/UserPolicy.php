<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, User $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    // platform_admin, not is_admin: is_admin covers every staff role.
    public function update(User $user, User $model): bool
    {
        return $user->hasRole('platform_admin') || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasRole('platform_admin') || $user->id === $model->id;
    }

    public function toggleAdmin(User $user): bool
    {
        return $user->is_admin;
    }
}
