<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'conditions' => $this->conditions,
            'has_max_groups_per_day' => $this->has_max_groups_per_day,
            'max_groups_per_day' => $this->max_groups_per_day,
            'has_max_participants' => $this->has_max_participants,
            'max_participants' => $this->max_participants,
            'auto_approve' => $this->auto_approve,
            'booking_info' => $this->when(
                $request->user()?->hasRole(['access_officer', 'platform_admin']) ||
                $this->officers()->where('user_id', $request->user()?->id)->exists(),
                $this->booking_info
            ),
            'is_active' => $this->is_active,
            'caves' => CaveSummaryResource::collection($this->whenLoaded('caves')),
            'officers' => UserResource::collection($this->whenLoaded('officers')),
            'bookings_count' => $this->whenCounted('bookings'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
