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
            'description' => $this->description ?? '',
            'hero_image' => $this->hero_image,
            'entrance_image' => $this->entrance_image,

            'tags' => TagResource::collection($this->tags),
            'location_name' => $this->location_name,
            'location_country' => $this->location_country,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'system' => $this->system ? [
                'id' => $this->system->id,
                'name' => $this->system->name,
                'description' => $this->system->description,
                'length' => $this->system->length,
                'vertical_range' => $this->system->vertical_range,
                'caves' => $this->system->caves,
                'tags' => TagResource::collection($this->system->tags),
                'references' => $this->system->references,
            ] : [],
            'trips' => TripResource::collection($this->trips),
        ];
    }
}
