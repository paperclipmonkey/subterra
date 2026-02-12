<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

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
            'photo' => $this->photo ? (str_starts_with($this->photo, 'http') ? $this->photo : Storage::disk('public')->url($this->photo)) : null,
            'clubs' => $this->clubs->filter(function ($club) {
                return $club->pivot->status === 'approved';
            })->map(function ($club) {
                return [
                    'name' => $club->name,
                    'slug' => $club->slug,
                ];
            })->values(),
        ];
    }
}
