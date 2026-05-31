<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Trip */
class TripSummaryResource extends JsonResource
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
            'description' => $this->description ?? '',
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'participants' => UserResource::collection($this->participants),
            'duration' => $this->duration,
            'entrance' => $this->entrance ? [
                'id' => $this->entrance->id,
                'name' => $this->entrance->name,
                'slug' => $this->entrance->slug,
                'location_lat' => $this->entrance->location_lat,
                'location_lng' => $this->entrance->location_lng,
            ] : null,
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'entrance_hero_image' => $this->entrance?->heroImage?->filename
                ? Storage::disk('media')->url($this->entrance->heroImage->filename)
                : null,
            'entrance_entrance_image' => $this->entrance?->entranceImage?->filename
                ? Storage::disk('media')->url($this->entrance->entranceImage->filename)
                : null,
        ];
    }
}
