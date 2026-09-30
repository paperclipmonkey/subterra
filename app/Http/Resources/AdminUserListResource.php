<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Slim user row for the admin user table and the club member picker. Deliberately omits trips, medals and
 * callouts — loading those for every user is what made the list slow. Row
 * actions (toggle role, approve membership) return the full
 * UserDetailEmailResource, which is a superset of these fields.
 *
 * @mixin \App\Models\User
 */
class AdminUserListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'photo' => $this->photo ? (str_starts_with($this->photo, 'http') ? $this->photo : Storage::disk('media')->url($this->photo)) : null,
            'clubs' => $this->clubs->map(fn ($club) => [
                'id' => $club->id,
                'name' => $club->name,
                'slug' => $club->slug,
                'is_admin' => $club->pivot->is_admin,
                'status' => $club->pivot->status,
            ]),
            'roles' => $this->roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
