<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TripResource;
use Illuminate\Support\Facades\Storage;

class CaveResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $previoslyDoneTag = $this->trips->filter(function ($trip) use ($request) {
            return $trip->participants->contains('id', $request->user()->id);
        })->count() > 0 ? Tag::where('tag', 'Previously Done')->first() : Tag::where('tag', 'Not Done Yet')->first();
        
        $systemLengthTags = collect([]);
        if ($this->system) {
            $length = $this->system->length;

            if ($length > 5000) {
                $systemLengthTags->push(Tag::where('tag', '> 5km')->first());
            }
            if ($length > 1000) {
                $systemLengthTags->push(Tag::where('tag', '> 1km')->first());
            }
            if ($length > 500) {
                $systemLengthTags->push(Tag::where('tag', '> 500m')->first());
            }
            if ($length > 250) {
                $systemLengthTags->push(Tag::where('tag', '> 250m')->first());
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'hero_image' => $this->hero_image ? Storage::disk('media')->url($this->hero_image) : null,
            'entrance_image' => $this->entrance_image,
            'tags' => TagResource::collection($this->tags->merge(collect([$previoslyDoneTag]))),
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
                'tags' => TagResource::collection($this->system->tags->merge($systemLengthTags)),
                'references' => $this->system->references,
            ] : [],
            'trips' => TripResource::collection($this->trips),
        ];
    }
}
