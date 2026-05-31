<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class UserDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'photo' => $this->photo ? (str_starts_with($this->photo, 'http') ? $this->photo : Storage::disk('public')->url($this->photo)) : null,
            'bio' => $this->bio,
            'visibility_addable' => $this->visibility_addable,
            // Eager load approvedClubs if not already done in controller
            'clubs' => $this->clubs->map(function ($club) {
                return [
                    'name' => $club->name,
                    'slug' => $club->slug,
                    'is_admin' => $club->pivot->is_admin,
                    'status' => $club->pivot->status,
                ];
            }),
            'medals' => $this->medals->map(function ($medal) {
                return [
                    'id' => $medal->id,
                    'name' => $medal->name,
                    'description' => $medal->description,
                    'image_url' => $this->getMedalUrl($medal->image_path),
                    'awarded_at' => $medal->pivot->awarded_at ?? null,
                ];
            }),
            'is_admin' => $this->is_admin,
            'stats' => [
                'trips' => $this->trips->count(),
                'caves' => $this->trips->pluck('system.id')->unique()->count(),
                'duration' => $this->trips->sum('duration'),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * Get the full URL for a medal image.
     */
    private function getMedalUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return Storage::disk('medals')->url($path);
    }
}
