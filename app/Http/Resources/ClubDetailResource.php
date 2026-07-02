<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Club */
class ClubDetailResource extends JsonResource
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
            // The "Direct Individual Member" catch-all club has its social
            // features (member roster, club trips, stats) hidden in the UI.
            'is_individual_membership' => $this->isIndividualMembership(),
            // Controllers load this via loadCount(['approvedUsers as users_count']).
            // The closure keeps evaluation lazy so the member_count accessor's
            // per-club COUNT query never runs when the count is preloaded.
            'member_count' => $this->whenCounted('users', fn () => $this->users_count),
            'pending_users_count' => $this->whenCounted('pendingUsers'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'huts' => $this->whenLoaded('huts'),
            // Example: Load members only for detail view if needed
            // 'members' => UserResource::collection($this->whenLoaded('users')),
        ];
    }
}
