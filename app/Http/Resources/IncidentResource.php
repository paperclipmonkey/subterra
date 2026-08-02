<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serialises an incident for the duty-officer dashboard / war room.
 *
 * Incidents are only ever returned to duty officers and platform admins, so the
 * nested callout is rendered with full operational detail (`withContact(true)`).
 * Even so, the people referenced — the incident controller and note authors —
 * are reduced to {@see UserSummaryResource} (id/name/photo) rather than full
 * user records, so officers' own email/phone/preferences are never exposed to
 * one another.
 *
 * @mixin \App\Models\Incident
 */
class IncidentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'callout_id' => $this->callout_id,
            'status' => $this->status,
            'resolved_at' => $this->resolved_at,
            'incident_controller_id' => $this->incident_controller_id,
            'acknowledged_at' => $this->acknowledged_at,
            'escalated_at' => $this->escalated_at,
            'police_log_number' => $this->police_log_number,
            'last_voice_call_at' => $this->last_voice_call_at,
            'voice_call_count' => $this->voice_call_count,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'callout' => CalloutResource::make($this->whenLoaded('callout'))->withContact(true),
            'controller' => UserSummaryResource::make($this->whenLoaded('controller')),
            'notes' => $this->whenLoaded('notes', fn () => $this->notes->map(fn ($note) => [
                'id' => $note->id,
                'content' => $note->content,
                'created_at' => $note->created_at,
                'user' => $note->relationLoaded('user') && $note->user
                    ? UserSummaryResource::make($note->user)
                    : null,
            ])->values()),
        ];
    }
}
