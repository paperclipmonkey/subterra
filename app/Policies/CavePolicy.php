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
        // admin_only sites (e.g. coal mines) exist for safety but must not be
        // viewable by guests or ordinary users — only by data admins.
        if ($cave->visibility === 'admin_only') {
            return $user !== null && $this->canManage($user, $cave);
        }

        return true; // Public caves are publicly viewable
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['platform_admin', 'data_admin']);
    }

    public function update(User $user, Cave $cave): bool
    {
        return $this->canManage($user, $cave);
    }

    public function delete(User $user, Cave $cave): bool
    {
        return $this->canManage($user, $cave);
    }

    /**
     * Data management (edit, delete, see private fields, view admin-only caves)
     * is restricted to global data admins.
     */
    private function canManage(User $user, Cave $cave): bool
    {
        return $user->hasRole(['platform_admin', 'data_admin']);
    }
}
