<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\CaveSystem */
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
            // Cave tags are eager loaded only to compute the caving_region append
            // without per-cave queries; keep them out of the payload.
            'caves' => $this->caves->makeHidden('tags'),
            'tags' => TagResource::collection($this->tags),
            'references' => $this->references,
            'catchment_id' => $this->catchment_id,
            'files' => $this->relationLoaded('files') ? CaveSystemFileResource::collection($this->files) : [],
            'annotation' => $this->whenLoaded('annotation'),
            'map_overlays' => CaveSystemMapOverlayResource::collection($this->whenLoaded('mapOverlays')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
