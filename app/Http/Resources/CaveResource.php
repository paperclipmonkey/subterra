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

            if ($length >= 5000) {
                $systemLengthTags->push(Tag::where('tag', '> 5km')->first());
            }
            if ($length >= 1000) {
                $systemLengthTags->push(Tag::where('tag', '> 1km')->first());
            }
            if ($length >= 500) {
                $systemLengthTags->push(Tag::where('tag', '> 500m')->first());
            }
            if ($length >= 250) {
                $systemLengthTags->push(Tag::where('tag', '> 250m')->first());
            }
        }

        // Remove nulls from systemLengthTags to avoid merge errors
        $systemLengthTags = $systemLengthTags->filter(function($tag) { return $tag instanceof Tag; });

        // Ensure $this->tags is always a collection of Tag models
        $tags = $this->tags instanceof \Illuminate\Support\Collection ? $this->tags : collect($this->tags);
        $tags = $tags->filter(function($tag) { return $tag instanceof Tag; });
        if ($previoslyDoneTag instanceof Tag) {
            $tags = $tags->merge([$previoslyDoneTag]);
        }
        if ($systemLengthTags->isNotEmpty()) {
            $tags = $tags->merge($systemLengthTags);
        }

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'access_info' => $this->access_info ?? '',
            'hero_image' => $this->hero_image ? \Illuminate\Support\Facades\URL::to('/storage/' . ltrim($this->hero_image, '/')) : null,
            'entrance_image' => $this->entrance_image ? \Illuminate\Support\Facades\URL::to('/storage/' . ltrim($this->entrance_image, '/')) : null,
            'tags' => TagResource::collection($tags->filter(function($tag) { return $tag instanceof Tag; })),
            'location_name' => $this->location_name,
            'location_country' => $this->location_country,
            'location_lat' => $this->location_lat,
            'location_lng' => $this->location_lng,
            'system' => [
                'id' => $this->system->id,
                'name' => $this->system->name,
                'description' => $this->system->description ?? '',
                'length' => $this->system->length,
                'vertical_range' => $this->system->vertical_range,
                'caves' => $this->system->caves,
                'tags' => TagResource::collection($this->system->tags->merge($systemLengthTags)),
                'references' => $this->system->references,
                'files' => $this->system->files ? $this->system->files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'url' => \Illuminate\Support\Facades\URL::to('/storage/cave_system_files/' . $file->cave_system_id . '/' . $file->filename),
                        'original_filename' => $file->original_filename,
                        'mime_type' => $file->mime_type,
                        'size' => $file->size,
                        'details' => $file->details,
                    ];
                }) : [],
            ],
            'trips' => TripResource::collection($this->trips),
            'previously_done' => $previoslyDoneTag->tag === 'Previously Done',
        ];
    }
}
