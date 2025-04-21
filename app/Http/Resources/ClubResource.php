<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'description' => $this->description, // Consider truncating for list view if needed
            'website' => $this->website,
            'location' => $this->location,
            'is_enabled' => $this->is_enabled,
            // Use the accessor for member count
            // Ensure 'users_count' is loaded via withCount('users') in the controller for efficiency
            'member_count' => $this->whenCounted('users', $this->member_count), // Use whenCounted if using withCount
            // 'member_count' => $this->member_count, // Or directly use accessor (less efficient in loops)
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
