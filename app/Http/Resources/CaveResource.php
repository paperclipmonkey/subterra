<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Cave */
class CaveResource extends JsonResource
{
    protected static $cachedTags = null;

    public static function getCachedTag($label)
    {
        if (self::$cachedTags === null) {
            self::$cachedTags = Tag::whereIn('tag', ['Previously Done', 'Not Done Yet', '> 5km', '> 1km', '> 500m', '> 250m', '< 250m'])->get()->keyBy('tag');
        }

        return self::$cachedTags->get($label);
    }

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
            $hasDone = $this->trips()->whereHas('participants', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })->exists();
        }

        $previoslyDoneTag = $hasDone ? $this->getCachedTag('Previously Done') : $this->getCachedTag('Not Done Yet');

        $systemLengthTags = collect([]);
        if ($this->system) {
            $length = $this->system->length;

            if ($length >= 5000) {
                $systemLengthTags->push($this->getCachedTag('> 5km'));
            }
            if ($length >= 1000) {
                $systemLengthTags->push($this->getCachedTag('> 1km'));
            }
            if ($length >= 500) {
                $systemLengthTags->push($this->getCachedTag('> 500m'));
            }
            if ($length >= 250) {
                $systemLengthTags->push($this->getCachedTag('> 250m'));
            } elseif ($length > 0) {
                $systemLengthTags->push($this->getCachedTag('< 250m'));
            }
        }

        // Remove nulls from systemLengthTags to avoid merge errors
        $systemLengthTags = $systemLengthTags->filter(function ($tag) {
            return $tag instanceof Tag;
        });

        // $this->tags is a Collection of Tag models
        $tags = $this->tags;
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
            'hero_image' => new CaveMediaResource($this->whenLoaded('heroImage', $this->heroImage)),
            'hero_video' => new CaveMediaResource($this->whenLoaded('heroVideo', $this->heroVideo)),
            'entrance_image' => new CaveMediaResource($this->whenLoaded('entranceImage', $this->entranceImage)),
            'media' => CaveMediaResource::collection($this->whenLoaded('media')),
            'tags' => TagResource::collection($tags),
            'caving_region' => $this->caving_region,
            'location_name' => $this->location_name,
            'location_country' => $this->location_country,
            'location_lat' => $request->user()?->hasApprovedClub() ? $this->location_lat : null,
            'location_lng' => $request->user()?->hasApprovedClub() ? $this->location_lng : null,
            'location_alt' => $request->user()?->hasApprovedClub() ? $this->location_alt : null,
            'access_info' => $request->user()?->hasApprovedClub() ? ($this->access_info ?? '') : null,
            'system' => [
                'id' => $this->system->id,
                'name' => $this->system->name,
                'description' => $this->system->description ?? '',
                'catchment_id' => $this->system->catchment_id,
                'catchment_name' => $this->system->relationLoaded('catchment') ? $this->system->catchment?->name : null,
                'length' => $this->system->length,
                'vertical_range' => $this->system->vertical_range,
                'caves' => $this->system->relationLoaded('caves') ? $this->system->caves->map(function ($cave) use ($request) {
                    return [
                        'id' => $cave->id,
                        'name' => $cave->name,
                        'slug' => $cave->slug,
                        'location_lat' => $request->user()?->hasApprovedClub() ? $cave->location_lat : null,
                        'location_lng' => $request->user()?->hasApprovedClub() ? $cave->location_lng : null,
                    ];
                }) : [],
                'tags' => $this->system->relationLoaded('tags') ? TagResource::collection($this->system->tags->merge($systemLengthTags)) : TagResource::collection($systemLengthTags),
                'references' => $request->user()?->hasApprovedClub() ? $this->system->references : [],
                'files' => $request->user()?->hasApprovedClub() && $this->system->relationLoaded('files') && $this->system->files->isNotEmpty() ? CaveSystemFileResource::collection($this->system->files) : [],
                'routes' => $this->system->relationLoaded('routes') ? $this->system->routes : [],
                'annotation' => $this->system->relationLoaded('annotation') ? $this->system->annotation : null,
            ],
            'trips' => TripSummaryResource::collection($this->whenLoaded('trips')),
            'previously_done' => optional($previoslyDoneTag)->tag === 'Previously Done',
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
