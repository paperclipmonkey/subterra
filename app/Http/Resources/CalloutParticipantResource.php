<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A callout participant. Contact details (phone/email) are PII about people who
 * may be in an emergency, so they are only included when the viewer is entitled
 * to see them — the callout creator, a fellow participant, or a duty
 * officer/admin. Set via {@see withContact()}.
 *
 * @mixin \App\Models\CalloutParticipant
 */
class CalloutParticipantResource extends JsonResource
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
            'name' => $this->name,
            'phone' => $this->when($this->showContact, fn () => $this->phone),
            'email' => $this->when($this->showContact, fn () => $this->email),
        ];
    }
}
