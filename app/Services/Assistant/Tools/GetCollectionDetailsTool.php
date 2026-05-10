<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\Collection;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use Illuminate\Support\Facades\DB;

class GetCollectionDetailsTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_collection_details',
                'description' => "Get the caves in a specific collection plus per-cave info on whether the user has visited each one. Use this when the user asks 'what's in this collection?' or wants to see their progress through a list.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'collection_id' => [
                            'type' => 'integer',
                            'description' => 'The numeric ID of the collection (returned by list_collections).',
                        ],
                        'slug' => [
                            'type' => 'string',
                            'description' => 'Alternatively, the collection slug (e.g. "yorkshire-big-three").',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $collection = null;
        if (!empty($arguments['collection_id'])) {
            $collection = Collection::find((int) $arguments['collection_id']);
        } elseif (!empty($arguments['slug'])) {
            $collection = Collection::where('slug', (string) $arguments['slug'])->first();
        }

        if (!$collection) {
            return ['error' => 'Collection not found. Pass either collection_id or slug.'];
        }

        $caves = $collection->caves()
            ->with(['system', 'heroImage', 'entranceImage'])
            ->get();

        $heroImage = $caves->pluck('heroImage.url')->first(fn ($u) => !empty($u))
            ?? $caves->pluck('entranceImage.url')->first(fn ($u) => !empty($u))
            ?? null;

        // Mark which caves the user has visited (entrance or exit cave on any trip)
        $caveIds = $caves->pluck('id')->all();
        $visitedIds = [];
        if (!empty($caveIds)) {
            $visitedIds = DB::table('trips')
                ->join('trip_user', 'trip_user.trip_id', '=', 'trips.id')
                ->where('trip_user.user_id', $user->id)
                ->where(function ($q) use ($caveIds) {
                    $q->whereIn('trips.entrance_cave_id', $caveIds)
                        ->orWhereIn('trips.exit_cave_id', $caveIds);
                })
                ->pluck('trips.entrance_cave_id')
                ->merge(
                    DB::table('trips')
                        ->join('trip_user', 'trip_user.trip_id', '=', 'trips.id')
                        ->where('trip_user.user_id', $user->id)
                        ->whereIn('trips.exit_cave_id', $caveIds)
                        ->pluck('trips.exit_cave_id')
                )
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $mapped = $caves->map(function ($cave) use ($visitedIds) {
            $isMultiEntrance = $cave->system && $cave->system->caves()->count() > 1;
            $preferredLink = $isMultiEntrance
                ? "/cave-systems/{$cave->system->slug}"
                : "/caves/{$cave->slug}";

            return [
                'cave_id' => $cave->id,
                'name' => $cave->name,
                'slug' => $cave->slug,
                'system_name' => $cave->system?->name,
                'system_slug' => $cave->system?->slug,
                'cave_url' => "/caves/{$cave->slug}",
                'preferred_link' => $preferredLink,
                'note' => $cave->pivot->description ?? null,
                'user_visited' => in_array($cave->id, $visitedIds, true),
            ];
        });

        $visitedCount = $mapped->where('user_visited', true)->count();
        $totalCount = $mapped->count();

        return [
            'id' => $collection->id,
            'name' => $collection->name,
            'slug' => $collection->slug,
            'url' => "/collections/{$collection->slug}",
            'description' => $collection->description
                ? mb_substr(strip_tags($collection->description), 0, 600)
                : null,
            'image_url' => $heroImage,
            'cave_count' => $totalCount,
            'user_visited_count' => $visitedCount,
            'user_progress' => $totalCount > 0 ? "{$visitedCount}/{$totalCount}" : '0/0',
            'caves' => $mapped->values()->toArray(),
        ];
    }
}
