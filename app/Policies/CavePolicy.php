<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Cave;
use App\Models\User;

class CavePolicy
{
    public function viewAny(?User $user): bool
    {
        return true; // Caves are publicly viewable
    }

    public function view(?User $user, Cave $cave): bool
    {
        return true; // Individual caves are publicly viewable
    }

    public function create(User $user): bool
    {
        return $user->admin;
    }

    public function update(User $user, Cave $cave): bool
    {
        return $user->admin;
    }

    public function delete(User $user, Cave $cave): bool
    {
        return $user->admin;
    }
}