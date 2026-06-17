<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An on-call rota shift. The assigned officer is rendered via
 * {@see UserSummaryResource} (id/name/photo) so the rota never exposes duty
 * officers' email/phone/notification preferences to one another.
 *
 * @mixin \App\Models\OnCallShift
 */
class OnCallShiftResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'notified_at' => $this->notified_at,
            'notify_do' => $this->notify_do,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'user' => UserSummaryResource::make($this->whenLoaded('user')),
        ];
    }
}
