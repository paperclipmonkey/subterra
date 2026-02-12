<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class TripResource extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->short_id,
            'name' => $this->name,
            'description' => $this->description ?? "",
            'system' => $this->system,
            'entrance' => $this->entrance,
            'exit' => $this->exit,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'visibility' => $this->visibility,
            'participants' => UserResource::collection($this->participants),
            'media' => MediaResource::collection($this->media),
            'duration' => $this->duration,
            'entrance_hero_image' => $this->entrance?->hero_image
                ? Storage::disk('media')->url($this->entrance->hero_image)
                : null,
            'entrance_entrance_image' => $this->entrance?->entrance_image
                ? Storage::disk('media')->url($this->entrance->entrance_image)
                : null,
        ];
    }
}
