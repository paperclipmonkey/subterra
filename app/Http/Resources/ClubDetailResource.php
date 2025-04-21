<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClubDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For now, it's the same as ClubResource, but can be expanded later
        // e.g., include a list of members (users) if needed for detail view.
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'website' => $this->website,
            'location' => $this->location,
            'is_active' => $this->is_active,
            // Use the accessor for member count
            // Ensure 'users_count' is loaded via withCount('users') in the controller for efficiency
            'member_count' => $this->whenCounted('users', $this->member_count), // Use whenCounted if using withCount
            // 'member_count' => $this->member_count, // Or directly use accessor
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Example: Load members only for detail view if needed
            // 'members' => UserResource::collection($this->whenLoaded('users')),
        ];
    }
}
