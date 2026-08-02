<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A deliberately minimal view of a user for contexts where only an identity is
 * needed (incident controllers, note authors, rota entries, callout leaders).
 *
 * Crucially it exposes NO contact details (email/phone) or account metadata,
 * so nesting a user inside another response can never leak PII by default.
 *
 * @mixin \App\Models\User
 */
class UserSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'photo' => $this->photo
                ? (str_starts_with($this->photo, 'http') ? $this->photo : Storage::disk('media')->url($this->photo))
                : null,
        ];
    }
}
