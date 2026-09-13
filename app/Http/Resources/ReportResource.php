<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Cave;
use App\Models\CaveMedia;
use App\Models\Trip;
use App\Models\TripMedia;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Report */
class ReportResource extends JsonResource
{
    /**
     * Admin-facing shape. Reports are only ever listed to platform admins, so
     * this deliberately includes the reporter's identity — a moderator needs to
     * know who raised a complaint to weigh it and to follow up.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->category,
            'details' => $this->details,
            'status' => $this->status,
            'is_urgent' => $this->isUrgent(),
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
                'email' => $this->reporter?->email,
            ]),
            'target' => [
                'type' => $this->shortType(),
                'id' => $this->reportable_id,
                'label' => $this->targetLabel(),
                'url' => $this->targetUrl(),
            ],
            'handled_by' => $this->whenLoaded('handler', fn () => $this->handler?->name),
            'handled_at' => $this->handled_at,
            'resolution_note' => $this->resolution_note,
            'created_at' => $this->created_at,
        ];
    }

    /** "Trip" rather than "App\Models\Trip" — the admin table shows this raw. */
    private function shortType(): ?string
    {
        if (!$this->reportable_type) {
            return null;
        }

        return class_basename($this->reportable_type);
    }

    /**
     * A human label for the reported thing, resolved from the loaded relation.
     * Falls back to the type and id when the target has since been deleted —
     * a report must stay readable after its subject is gone.
     */
    private function targetLabel(): ?string
    {
        $target = $this->whenLoaded('reportable') instanceof \Illuminate\Http\Resources\MissingValue
            ? null
            : $this->reportable;

        if ($target === null) {
            return $this->reportable_type
                ? $this->shortType().' #'.$this->reportable_id.' (deleted)'
                : null;
        }

        return match (true) {
            $target instanceof User => $target->name ?? $target->email,
            $target instanceof Trip => $target->name,
            $target instanceof Cave => $target->name,
            $target instanceof TripMedia, $target instanceof CaveMedia => $target->title ?? 'Photo',
            default => $this->shortType().' #'.$this->reportable_id,
        };
    }

    /** Deep link into the SPA so a moderator can see the thing in context. */
    private function targetUrl(): ?string
    {
        $target = $this->whenLoaded('reportable') instanceof \Illuminate\Http\Resources\MissingValue
            ? null
            : $this->reportable;

        if ($target === null) {
            return null;
        }

        return match (true) {
            $target instanceof User => '/profile/'.$target->id,
            $target instanceof Trip => '/trips/'.($target->short_id ?? $target->id),
            $target instanceof Cave => '/caves/'.($target->slug ?? $target->id),
            default => null,
        };
    }
}
