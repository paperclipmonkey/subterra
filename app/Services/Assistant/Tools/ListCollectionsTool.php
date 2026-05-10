<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\Collection;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use Illuminate\Support\Facades\DB;

class ListCollectionsTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_collections',
                'description' => "List curated cave collections — themed groups of caves users can work through (e.g. 'Yorkshire Big Three', 'Mendip Classics'). Each result includes the user's progress (how many of the caves they've already done). Use this when the user asks about goal lists, classic trips, or what to aim for.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Optional partial name match, e.g. "Yorkshire", "beginner", "Mendip". Matches collection name and description.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $query = Collection::query()->withCount('caves');

        if (!empty($arguments['name'])) {
            $name = (string) $arguments['name'];
            $query->where(function ($q) use ($name) {
                $q->where('name', 'like', "%{$name}%")
                    ->orWhere('description', 'like', "%{$name}%");
            });
        }

        $collections = $query->orderBy('name')->limit(10)->get();

        // Progress per collection — how many of its caves the user has visited
        // (entrance or exit on a trip they participated in).
        $collectionIds = $collections->pluck('id');
        $progressByCollection = $this->progressFor($collectionIds, $user);

        $mapped = $collections->map(function ($c) use ($progressByCollection) {
            $visited = (int) ($progressByCollection[$c->id] ?? 0);
            $total = (int) $c->caves_count;

            return [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'url' => "/collections/{$c->slug}",
                'description' => $c->description
                    ? mb_substr(strip_tags($c->description), 0, 240)
                    : null,
                'cave_count' => $total,
                'user_visited_count' => $visited,
                'user_progress' => $total > 0 ? "{$visited}/{$total}" : '0/0',
            ];
        });

        return [
            'count' => $mapped->count(),
            'collections' => $mapped->values()->toArray(),
        ];
    }

    /**
     * Visited-count per collection for the given user.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $collectionIds
     * @return array<int, int>  collection_id => visited_count
     */
    private function progressFor($collectionIds, User $user): array
    {
        if ($collectionIds->isEmpty()) {
            return [];
        }

        // A cave counts as "visited" if the user participated in any trip whose
        // entrance OR exit was that cave. We dedupe per cave per collection so a
        // user with three trips through one cave still scores 1 in that collection.
        $rows = DB::table('cave_collection')
            ->select(['cave_collection.collection_id', 'cave_collection.cave_id'])
            ->whereIn('cave_collection.collection_id', $collectionIds)
            ->whereExists(function ($q) use ($user) {
                $q->select(DB::raw(1))
                    ->from('trips')
                    ->join('trip_user', 'trip_user.trip_id', '=', 'trips.id')
                    ->where('trip_user.user_id', $user->id)
                    ->where(function ($w) {
                        $w->whereColumn('trips.entrance_cave_id', 'cave_collection.cave_id')
                            ->orWhereColumn('trips.exit_cave_id', 'cave_collection.cave_id');
                    });
            })
            ->get();

        $counts = [];
        foreach ($rows as $r) {
            $counts[$r->collection_id] = ($counts[$r->collection_id] ?? 0) + 1;
        }

        return $counts;
    }
}
