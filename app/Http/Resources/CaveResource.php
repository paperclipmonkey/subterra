<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'length' => $this->length,
            'depth' => $this->depth,
            'location' => [
                'name' => $this->location_name,
                'country' => $this->location_country,
                'lat' => $this->location_lat,
                'lng' => $this->location_lng,
            ],
            'system' => [
                'id' => $this->system->id,
                'name' => $this->system->name,
                'description' => $this->system->description,
                'caves' => $this->system->caves,
            ],
            'trips' => $this->trips,
            'trips_total' => $this->trips()->count(),
        ];
    }
}
