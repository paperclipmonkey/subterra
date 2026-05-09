<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use Illuminate\Support\Facades\DB;

class SearchCavesTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => 'search_caves',
                'description' => 'Search for cave systems matching criteria. Use this to find options to recommend. Returns up to 10 results with name, slug, length, grades, and tags.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'region' => [
                            'type'        => 'string',
                            'description' => 'Location name or region to search within, e.g. "Yorkshire Dales", "South Wales", "Derbyshire". Matched against cave location names.',
                        ],
                        'tags' => [
                            'type'        => 'array',
                            'items'       => ['type' => 'string'],
                            'description' => 'Tags the cave system must have, e.g. ["streamway"], ["through trip"], ["sporting"]. Each tag must match.',
                        ],
                        'min_length' => [
                            'type'        => 'number',
                            'description' => 'Minimum cave system length in metres.',
                        ],
                        'max_length' => [
                            'type'        => 'number',
                            'description' => 'Maximum cave system length in metres.',
                        ],
                        'not_visited' => [
                            'type'        => 'boolean',
                            'description' => 'If true, only return systems the user has not yet visited.',
                        ],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $query = DB::table('cave_systems')
            ->select([
                'cave_systems.id',
                'cave_systems.name',
                'cave_systems.slug',
                'cave_systems.length',
                'cave_systems.vertical_range',
                'cave_systems.description',
            ]);

        // Region filter — join caves for location_name
        if (!empty($arguments['region'])) {
            $region = $arguments['region'];
            $query->join('caves as region_caves', 'region_caves.cave_system_id', '=', 'cave_systems.id')
                ->where('region_caves.location_name', 'like', "%{$region}%")
                ->groupBy('cave_systems.id', 'cave_systems.name', 'cave_systems.slug', 'cave_systems.length', 'cave_systems.vertical_range', 'cave_systems.description');
        }

        // Length filters
        if (!empty($arguments['min_length'])) {
            $query->where('cave_systems.length', '>=', (float) $arguments['min_length']);
        }
        if (!empty($arguments['max_length'])) {
            $query->where('cave_systems.length', '<=', (float) $arguments['max_length']);
        }

        // Tag filters — each tag must match at least one record in cave_system_tag
        if (!empty($arguments['tags']) && is_array($arguments['tags'])) {
            foreach ($arguments['tags'] as $tag) {
                $tag = (string) $tag;
                $query->whereExists(function ($sub) use ($tag) {
                    $sub->select(DB::raw(1))
                        ->from('cave_system_tag')
                        ->join('tags', 'tags.id', '=', 'cave_system_tag.tag_id')
                        ->whereColumn('cave_system_tag.cave_system_id', 'cave_systems.id')
                        ->where('tags.tag', 'like', "%{$tag}%");
                });
            }
        }

        // Exclude already-visited systems
        if (!empty($arguments['not_visited'])) {
            $visitedIds = DB::table('trip_user')
                ->join('trips', 'trips.id', '=', 'trip_user.trip_id')
                ->where('trip_user.user_id', $user->id)
                ->whereNotNull('trips.cave_system_id')
                ->distinct()
                ->pluck('trips.cave_system_id');

            $query->whereNotIn('cave_systems.id', $visitedIds);
        }

        $systems = $query->orderBy('cave_systems.name')->limit(10)->get();
        $systemIds = $systems->pluck('id');

        // Tags for the returned systems
        $tagsBySystem = DB::table('cave_system_tag')
            ->join('tags', 'tags.id', '=', 'cave_system_tag.tag_id')
            ->select(['cave_system_tag.cave_system_id', 'tags.tag'])
            ->whereIn('cave_system_tag.cave_system_id', $systemIds)
            ->get()
            ->groupBy('cave_system_id');

        // Representative coordinates — first entrance with valid lat/lng per system
        $coordsBySystem = DB::table('caves')
            ->select(['cave_system_id', 'location_lat', 'location_lng'])
            ->whereIn('cave_system_id', $systemIds)
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->orderBy('id')
            ->get()
            ->unique('cave_system_id')
            ->keyBy('cave_system_id');

        // Route grades summary per system — done in PHP to keep the query cross-compatible
        // with both MySQL (production) and SQLite (tests).
        $gradesBySystem = collect();
        if ($systemIds->isNotEmpty()) {
            $gradesBySystem = DB::table('routes')
                ->select(['cave_system_id', 'grade'])
                ->whereIn('cave_system_id', $systemIds)
                ->whereNotNull('grade')
                ->distinct()
                ->orderBy('grade')
                ->get()
                ->groupBy('cave_system_id')
                ->map(fn ($rows) => $rows->pluck('grade')->join(', '));
        }

        $mapped = $systems->map(function ($system) use ($tagsBySystem, $gradesBySystem, $coordsBySystem) {
            $tags   = ($tagsBySystem[$system->id] ?? collect())->map(fn ($t) => $t->tag)->values();
            $grades = $gradesBySystem[$system->id] ?? null;
            $coords = $coordsBySystem[$system->id] ?? null;

            $excerpt = null;
            if ($system->description) {
                $excerpt = mb_substr(strip_tags($system->description), 0, 200);
            }

            return [
                'id'               => $system->id,
                'name'             => $system->name,
                'slug'             => $system->slug,
                'length_m'         => $system->length,
                'vertical_range_m' => $system->vertical_range,
                'grades'           => $grades,
                'tags'             => $tags,
                'description'      => $excerpt,
                'latitude'         => $coords ? (float) $coords->location_lat : null,
                'longitude'        => $coords ? (float) $coords->location_lng : null,
            ];
        });

        return [
            'count'        => $mapped->count(),
            'cave_systems' => $mapped->values()->toArray(),
        ];
    }
}
