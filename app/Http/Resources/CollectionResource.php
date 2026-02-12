<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CollectionResource extends JsonResource
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
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'caves_count' => $this->caves_count,
            'photo_path' => $this->photo_path ? Storage::disk('media')->url($this->photo_path) : null,
            'caves' => CaveResource::collection($this->whenLoaded('caves')),
        ];
    }
}
