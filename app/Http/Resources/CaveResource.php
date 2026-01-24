<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\TripResource;
use App\Http\Resources\CollectionResource;
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
        $user = $request->user();
        $hasDone = false;

        if ($this->relationLoaded('trips')) {
             $hasDone = $this->trips->contains(function ($trip) use ($user) {
                 return $trip->participants->contains('id', $user->id);
             });
        } elseif ($user) {
             $hasDone = $this->trips()->whereHas('participants', function($q) use($user) {
                 $q->where('users.id', $user->id);
             })->exists();
        }

        $previoslyDoneTag = $hasDone ? Tag::where('tag', 'Previously Done')->first() : Tag::where('tag', 'Not Done Yet')->first();
        
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
            'hero_image' => $this->hero_image ? Storage::disk('media')->url($this->hero_image) : null,
            'entrance_image' => $this->entrance_image ? Storage::disk('media')->url($this->entrance_image) : null,
            'tags' => TagResource::collection($tags->filter(function($tag) { return $tag instanceof Tag; })),
            'location_name' => $this->location_name,
            'location_country' => $this->location_country,
            'location_lat' => $request->user()?->is_approved ? $this->location_lat : null,
            'location_lng' => $request->user()?->is_approved ? $this->location_lng : null,
            'location_alt' => $request->user()?->is_approved ? $this->location_alt : null,
            'access_info' => $request->user()?->is_approved ? ($this->access_info ?? '') : null,
            'system' => [
                'id' => $this->system->id,
                'name' => $this->system->name,
                'description' => $this->system->description ?? '',
                'length' => $this->system->length,
                'vertical_range' => $this->system->vertical_range,
                'caves' => $this->system->caves,
                'tags' => TagResource::collection($this->system->tags->merge($systemLengthTags)),
                'references' => $request->user()?->is_approved ? $this->system->references : [],
                'files' => $request->user()?->is_approved && $this->system->files ? $this->system->files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'url' => Storage::disk('media')->url('cave_system_files/' . $file->cave_system_id . '/' . $file->filename),
                        'original_filename' => $file->original_filename,
                        'mime_type' => $file->mime_type,
                        'size' => $file->size,
                        'details' => $file->details,
                    ];
                }) : [],            ],
            'trips' => TripResource::collection($this->whenLoaded('trips')),
            'previously_done' => $previoslyDoneTag->tag === 'Previously Done',
            'collections' => CollectionResource::collection($this->whenLoaded('collections')),
            'pivot' => $this->whenPivotLoaded('cave_collection', function () {
                return [
                    'description' => $this->pivot->description,
                    'sort_order' => $this->pivot->sort_order,
                ];
            }),
            'is_ticked' => $this->when(isset($this->is_ticked), $this->is_ticked),
        ];
    }
}
