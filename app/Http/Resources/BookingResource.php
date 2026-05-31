<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Booking */
class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOfficer = $user?->hasRole(['access_officer', 'platform_admin']) ||
            ($this->relationLoaded('permit') && $this->permit->officers()->where('user_id', $user?->id)->exists());

        return [
            'id' => $this->short_id,
            'permit' => new PermitResource($this->whenLoaded('permit')),
            'applicant' => new UserResource($this->whenLoaded('applicant')),
            'date' => $this->date->toDateString(),
            'participants' => $this->participants,
            'status' => $this->status,
            'notes' => $this->notes,
            'rejection_reason' => $this->rejection_reason,
            'booking_info' => $this->when(
                $this->status === 'approved' && ($user?->id === $this->user_id || $isOfficer),
                fn () => $this->permit?->booking_info
            ),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'conditions_accepted_at' => $this->conditions_accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
