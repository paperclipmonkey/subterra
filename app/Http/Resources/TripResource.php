<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin \App\Models\Trip */
class TripResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Public trips are readable without logging in, so apply CaveResource's
        // gate: only approved-club members get cave coordinates, access info and
        // survey references.
        $user = $request->user();
        $canSeeLocations = (bool) $user?->hasApprovedClub();
        // admin_only sites (e.g. coal mines) can't be picked for a new trip by
        // anyone who can't see them, but an older trip (or one logged by a data
        // admin) may still point at one: never give their whereabouts away.
        // Role check only when needed, so ordinary trips cost no extra query.
        $canSeeCave = fn (?Model $cave): bool => $canSeeLocations
            && ($cave?->getAttribute('visibility') !== 'admin_only'
                || (bool) $user?->hasRole(['platform_admin', 'data_admin']));

        return [
            'id' => $this->short_id,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'system' => $this->gated($this->system, ['references'], $canSeeLocations),
            'entrance' => $this->gated($this->entrance, self::CAVE_LOCATION_FIELDS, $canSeeCave($this->entrance)),
            // exit and creator_id only serialize when eager-loaded — falling
            // back to null instead of running a query per trip on collections.
            'exit' => $this->relationLoaded('exit')
                ? $this->gated($this->exit, self::CAVE_LOCATION_FIELDS, $canSeeCave($this->exit))
                : null,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'visibility' => $this->visibility,
            'participants' => UserResource::collection($this->participants),
            'creator_id' => $this->relationLoaded('audits')
                ? $this->audits->firstWhere('event', 'created')?->user_id
                : null,
            'media' => MediaResource::collection($this->media),
            'duration' => $this->duration,
            'entrance_hero_image' => $this->entrance?->heroImage?->filename
                ? Storage::disk('media')->url($this->entrance->heroImage->filename)
                : null,
            'entrance_entrance_image' => $this->entrance?->entranceImage?->filename
                ? Storage::disk('media')->url($this->entrance->entranceImage->filename)
                : null,
        ];
    }

    private const CAVE_LOCATION_FIELDS = ['location_lat', 'location_lng', 'location_alt', 'access_info'];

    /**
     * Serialise a related model, nulling the given fields unless the viewer may see them.
     *
     * @param  list<string>  $fields
     * @return array<string, mixed>|null
     */
    private function gated(?Model $model, array $fields, bool $canSee): ?array
    {
        if ($model === null) {
            return null;
        }

        $data = $model->toArray();
        if (!$canSee) {
            foreach ($fields as $field) {
                if (array_key_exists($field, $data)) {
                    $data[$field] = null;
                }
            }
        }

        return $data;
    }
}
