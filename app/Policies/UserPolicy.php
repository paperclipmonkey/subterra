<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->approved;
    }

    public function view(User $user, User $model): bool
    {
        return $user->approved;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, User $model): bool
    {
        return $user->is_admin || $user->id === $model->id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->is_admin || $user->id === $model->id;
    }

    public function toggleAdmin(User $user): bool
    {
        return $user->is_admin;
    }

    public function toggleApproval(User $user): bool
    {
        return $user->is_admin;
    }
}