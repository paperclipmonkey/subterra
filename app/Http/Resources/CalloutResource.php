<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises a callout for API responses.
 *
 * Two PII safeguards are baked in here so no caller can leak by accident:
 *
 *  1. Forensic/operational metadata captured for safeguarding — the reporter's
 *     IP and user-agent (`request_data`), and the cancellation IP/user-agent/
 *     location — is NEVER serialised. (It is also hidden at the model level as
 *     defence in depth.)
 *  2. Sensitive trip detail (vehicle registration, parking, team logistics,
 *     plan, precise location) and participant contact details are only included
 *     for entitled viewers — the creator, a participant, or a duty officer/admin
 *     — controlled by {@see withContact()}. Everyone else sees just enough to
 *     identify the callout (status, time, location name, who is on the trip).
 *
 * @mixin \App\Models\Callout
 */
class CalloutResource extends JsonResource
{
    public bool $showContact = false;

    public function withContact(bool $show): static
    {
        $this->showContact = $show;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'trip_id' => $this->trip_id,
            'cave_id' => $this->cave_id,
            'exit_cave_id' => $this->exit_cave_id,
            'status' => $this->status,
            'callout_time' => $this->callout_time,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Sensitive trip detail — entitled viewers only.
            'description' => $this->when($this->showContact, fn () => $this->description),
            'trip_plan' => $this->when($this->showContact, fn () => $this->trip_plan),
            'car_details' => $this->when($this->showContact, fn () => $this->car_details),
            'car_registration' => $this->when($this->showContact, fn () => $this->car_registration),
            'car_parking' => $this->when($this->showContact, fn () => $this->car_parking),
            'team_details' => $this->when($this->showContact, fn () => $this->team_details),
            'location_data' => $this->when($this->showContact, fn () => $this->location_data),

            'cave' => $this->whenLoaded('cave'),
            'exit_cave' => $this->whenLoaded('exitCave'),
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
            'incident' => $this->whenLoaded('incident'),
            'participants' => $this->whenLoaded('participants', fn () => $this->participants
                ->map(fn ($participant) => (new CalloutParticipantResource($participant))->withContact($this->showContact))
                ->values()),
        ];
    }
}
