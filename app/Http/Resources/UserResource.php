<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
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
            'photo' => $this->photo ? (str_starts_with($this->photo, 'http') ? $this->photo : Storage::disk('media')->url($this->photo)) : null,
            'has_phone' => !empty($this->phone),
            // Whether a BCA number is on file (the number itself is PII and is
            // never exposed here — it's resolved server-side at booking time).
            'has_bca' => !empty($this->bca_number),
            'clubs' => $this->clubs->filter(function ($club) {
                return $club->pivot->status === 'approved';
            })->map(function ($club) {
                return [
                    'name' => $club->name,
                    'slug' => $club->slug,
                ];
            })->values(),
            'is_club_admin' => $this->when(isset($this->is_club_admin), function () {
                return $this->is_club_admin;
            }),
        ];
    }
}
