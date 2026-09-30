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
        // Mirror CaveResource / CaveSystemFileController: data admins see
        // everything; approved-club members see locations, access info,
        // references and public files; everyone else sees none of those, and
        // only admins learn that admin_only sites (e.g. coal mines) exist.
        $user = $request->user();
        $canManage = $user !== null && $this->resource->managedBy($user);
        $canSeeLocations = $canManage || (bool) $user?->hasApprovedClub();

        $caves = $this->caves
            ->when(!$canManage, fn ($caves) => $caves->reject(fn ($cave) => $cave->visibility === 'admin_only'))
            ->map(function ($cave) use ($canSeeLocations) {
                // Cave tags are eager loaded only to compute the caving_region append
                // without per-cave queries; keep them out of the payload.
                $data = $cave->makeHidden('tags')->toArray();
                if (!$canSeeLocations) {
                    foreach (['location_lat', 'location_lng', 'location_alt', 'access_info'] as $field) {
                        $data[$field] = null;
                    }
                }

                return $data;
            })
            ->values();

        $files = [];
        if ($this->relationLoaded('files')) {
            $visibleFiles = match (true) {
                $canManage => $this->files,
                $canSeeLocations => $this->files->where('visibility', 'public')->values(),
                default => collect(),
            };
            $files = CaveSystemFileResource::collection($visibleFiles);
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'length' => $this->length,
            'vertical_range' => $this->vertical_range,
            'caves' => $caves,
            'tags' => TagResource::collection($this->tags),
            'references' => $canSeeLocations ? $this->references : null,
            'catchment_id' => $this->catchment_id,
            'files' => $files,
            'annotation' => $this->whenLoaded('annotation'),
            'map_overlays' => CaveSystemMapOverlayResource::collection($this->whenLoaded('mapOverlays')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
