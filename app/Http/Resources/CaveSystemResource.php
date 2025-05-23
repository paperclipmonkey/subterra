<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TripResource;

class CaveSystemResource extends JsonResource
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
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'length' => $this->length,
            'vertical_range' => $this->vertical_range,
            'caves' => $this->caves,
            'tags' => TagResource::collection($this->tags),
            'references' => $this->references,
            'files' => $this->files ? CaveSystemFileResource::collection($this->whenLoaded('files')) : [],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
