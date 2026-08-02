<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Club */
class ClubResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description,
            'website' => $this->website,
            'location' => $this->location,
            'is_active' => $this->is_active,
            // Controllers load this via withCount(['approvedUsers as users_count']).
            // The closure keeps evaluation lazy so the member_count accessor's
            // per-club COUNT query never runs when the count is preloaded.
            'member_count' => $this->whenCounted('users', fn () => $this->users_count),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
