<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'email'=> $this->email,
            'photo' => $this->photo,
            'bio' => $this->bio,
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
                    'image_url' => $medal->image_url,
                    'awarded_at' => $medal->pivot->awarded_at ?? null,
                ];
            }),
            'is_admin' => $this->is_admin,
            'is_approved' => $this->is_approved,
            'stats'=> [
                'trips' => $this->trips->count(),
                'caves' => $this->trips->pluck('system.id')->unique()->count(),
                'duration' => $this->trips->sum('duration'),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
