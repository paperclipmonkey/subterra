<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TripResource;

class CaveResource extends JsonResource
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
            'description' => $this->description,

            'tags' => TagResource::collection($this->tags),
            'location' => [
                'name' => $this->location_name,
                'country' => $this->location_country,
                'lat' => $this->location_lat,
                'lng' => $this->location_lng,
            ],
            'system' => $this->system ? [
                'id' => $this->system->id,
                'name' => $this->system->name,
                'description' => $this->system->description,
                'length' => $this->length,
                'depth' => $this->depth,
                'caves' => $this->system->caves,
                'tags' => TagResource::collection($this->system->tags),
            ] : [],
            'trips' => TripResource::collection($this->trips),
        ];
    }
}
