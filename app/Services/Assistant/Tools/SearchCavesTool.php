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
            'type' => 'function',
            'function' => [
                'name' => 'search_caves',
                'description' => 'Search for cave systems matching criteria. Use this to find options to recommend, or to look up a specific system by name. Returns up to 10 results with slugs, primary entrance link, length, grades, and tags. IMPORTANT: if a search returns no results or 0 matches, do NOT call this tool again with variations — instead, tell the user the data is not in Subterra and suggest they try a different region or name. Repeated searches waste your limited tool-call budget.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Partial name match for a specific cave or system, e.g. "Ogof Draenen", "Swildon\'s". The search is fuzzy and handles apostrophes, spacing, and case variations. If your first search for a specific name returns no results, try removing or adding apostrophes (e.g., "Swildons" if "Swildon\'s" failed, or vice versa). This bypasses the curated filter.',
                        ],
                        'region' => [
                            'type' => 'string',
                            'description' => 'UK/Ireland region. Accepts any of: Northern / Yorkshire / Yorkshire Dales / Dales, Mendip / Mendips, South Wales / Brecon Beacons, North Wales / Snowdonia, Peak District / Derbyshire, Forest of Dean, Devon, Portland, Assynt / Scottish Highlands. Matches both cave location names AND tags, so use the natural region name.',
                        ],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Tags the cave system must have, e.g. ["streamway"], ["through trip"], ["sporting"]. Each tag must match.',
                        ],
                        'min_length' => [
                            'type' => 'number',
                            'description' => 'Minimum cave system length in metres.',
                        ],
                        'max_length' => [
                            'type' => 'number',
                            'description' => 'Maximum cave system length in metres.',
                        ],
                        'not_visited' => [
                            'type' => 'boolean',
                            'description' => 'If true, only return systems the user has not yet visited.',
                        ],
                        'include_obscure' => [
                            'type' => 'boolean',
                            'description' => 'Default false. By default this tool returns only "curated" cave systems — well-documented caves worth visiting. Subterra also catalogues thousands of minor sinkholes and uncurated entries that are noise for most queries; set this to true ONLY if the user explicitly asks for obscure / minor / dig-site caves.',
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

        // Name filter — match either system name or any of its caves
        // Normalize the search term by removing apostrophes and normalizing spaces
        if (!empty($arguments['name'])) {
            $name = (string) $arguments['name'];
            // Create a normalized version for fuzzy matching: remove apostrophes, normalize spaces
            $normalized = str_replace("'", '', $name);
            $normalized = preg_replace('/\s+/', ' ', trim($normalized));

            $query->where(function ($q) use ($name, $normalized) {
                // Exact LIKE match on original name
                $q->where('cave_systems.name', 'like', "%{$name}%")
                    // Also match normalized version (handles apostrophe and space variations)
                    // Chain REPLACE calls to remove apostrophes and normalize multiple spaces to single space
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(REPLACE(LOWER(cave_systems.name), '''', ''), '  ', ' '), '  ', ' '), '  ', ' ') LIKE LOWER(?)", ["%{$normalized}%"])
                    ->orWhereExists(function ($sub) use ($name) {
                        $sub->select(DB::raw(1))
                            ->from('caves')
                            ->whereColumn('caves.cave_system_id', 'cave_systems.id')
                            ->where('caves.name', 'like', "%{$name}%");
                    })
                    ->orWhereExists(function ($sub) use ($normalized) {
                        $sub->select(DB::raw(1))
                            ->from('caves')
                            ->whereColumn('caves.cave_system_id', 'cave_systems.id')
                            ->whereRaw("REPLACE(REPLACE(REPLACE(REPLACE(LOWER(caves.name), '''', ''), '  ', ' '), '  ', ' '), '  ', ' ') LIKE LOWER(?)", ["%{$normalized}%"]);
                    });
            });
        }

        // Region filter — match caves' location_name OR system/cave tags.
        // Most regional context (Northern, Mendip, South Wales) lives in tags
        // rather than location_name (which holds specific village/quarry names).
        if (!empty($arguments['region'])) {
            $region = (string) $arguments['region'];
            $aliases = $this->expandRegionSearchTerms($region);

            // Case-insensitive match — PostgreSQL's `LIKE` is case-sensitive by
            // default, which meant region="Mendips" missed tags spelled "Mendip"
            // and slugs spelled "mendips_*". LOWER() works on both PG and SQLite.
            $query->where(function ($outer) use ($aliases) {
                foreach ($aliases as $term) {
                    $needle = '%'.strtolower($term).'%';
                    $outer->orWhereExists(function ($sub) use ($needle) {
                        $sub->select(DB::raw(1))
                            ->from('caves as region_caves')
                            ->whereColumn('region_caves.cave_system_id', 'cave_systems.id')
                            ->whereRaw('LOWER(region_caves.location_name) LIKE ?', [$needle]);
                    });
                    $outer->orWhereExists(function ($sub) use ($needle) {
                        $sub->select(DB::raw(1))
                            ->from('cave_system_tag')
                            ->join('tags', 'tags.id', '=', 'cave_system_tag.tag_id')
                            ->whereColumn('cave_system_tag.cave_system_id', 'cave_systems.id')
                            ->whereRaw('LOWER(tags.tag) LIKE ?', [$needle]);
                    });
                    // Region tags are applied at the cave (entrance) level, not the
                    // system level — match any cave in the system carrying the tag.
                    $outer->orWhereExists(function ($sub) use ($needle) {
                        $sub->select(DB::raw(1))
                            ->from('cave_tag')
                            ->join('tags', 'tags.id', '=', 'cave_tag.tag_id')
                            ->join('caves as tag_caves', 'tag_caves.id', '=', 'cave_tag.cave_id')
                            ->whereColumn('tag_caves.cave_system_id', 'cave_systems.id')
                            ->whereRaw('LOWER(tags.tag) LIKE ?', [$needle]);
                    });
                    // Also match the cave slug prefix (e.g. `mendips_*`) so we catch
                    // systems whose data uses a regional slug convention even when
                    // the tag isn't applied.
                    $outer->orWhereExists(function ($sub) use ($needle) {
                        $sub->select(DB::raw(1))
                            ->from('caves as slug_caves')
                            ->whereColumn('slug_caves.cave_system_id', 'cave_systems.id')
                            ->whereRaw('LOWER(slug_caves.slug) LIKE ?', [$needle]);
                    });
                }
            });
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

        // Default to "curated" caves only — Subterra's database includes thousands
        // of minor sinkholes and uncurated entries that are noise for most queries.
        // Caller can pass include_obscure=true to opt into the long tail.
        // The exception: a name= search bypasses the curated filter, since the
        // user has named a specific cave and expects to find it whether or not
        // it's been blessed as curated.
        $includeObscure = !empty($arguments['include_obscure']);
        $hasName = !empty($arguments['name']);
        if (!$includeObscure && !$hasName) {
            // Wrap the OR in a where-group so it doesn't bleed into earlier
            // top-level WHERE conditions (e.g. min_length, region).
            $query->where(function ($outer) {
                $outer->whereExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('cave_system_tag')
                        ->join('tags', 'tags.id', '=', 'cave_system_tag.tag_id')
                        ->whereColumn('cave_system_tag.cave_system_id', 'cave_systems.id')
                        ->where('tags.tag', 'Curated');
                })
                    // Some legacy data lives on caves rather than systems — accept
                    // either side being tagged Curated.
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('cave_tag')
                            ->join('caves', 'caves.id', '=', 'cave_tag.cave_id')
                            ->join('tags', 'tags.id', '=', 'cave_tag.tag_id')
                            ->whereColumn('caves.cave_system_id', 'cave_systems.id')
                            ->where('tags.tag', 'Curated');
                    });
            });
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

        // Representative entrance — first cave with valid lat/lng per system
        // (used both for coordinates and for cave-level deep-linking when there's a clear primary)
        $primaryCaveBySystem = DB::table('caves')
            ->select(['cave_system_id', 'id', 'name', 'slug', 'location_lat', 'location_lng', 'location_name'])
            ->whereIn('cave_system_id', $systemIds)
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->orderBy('id')
            ->get()
            ->unique('cave_system_id')
            ->keyBy('cave_system_id');

        // Total entrance count — needed so the model can decide whether to deep-link
        // to the cave page (single-entrance system) or the system page (multi-entrance).
        $entranceCountBySystem = DB::table('caves')
            ->select(['cave_system_id', DB::raw('COUNT(*) as cnt')])
            ->whereIn('cave_system_id', $systemIds)
            ->groupBy('cave_system_id')
            ->get()
            ->keyBy('cave_system_id');

        // Hero image URL per system — looked up via Eloquent so the CaveMedia
        // model's URL accessor (which reads the configured storage disk) runs.
        // First hero, then entrance, then nothing.
        $imageBySystem = [];
        if ($systemIds->isNotEmpty()) {
            $primaryCaveIds = collect($primaryCaveBySystem)->pluck('id')->all();
            $caves = \App\Models\Cave::with(['heroImage', 'entranceImage'])
                ->whereIn('id', $primaryCaveIds)
                ->get();
            foreach ($caves as $cave) {
                $imageBySystem[$cave->cave_system_id] = $cave->heroImage?->url
                    ?? $cave->entranceImage?->url
                    ?? null;
            }
        }

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

        $mapped = $systems->map(function ($system) use ($tagsBySystem, $gradesBySystem, $primaryCaveBySystem, $entranceCountBySystem, $imageBySystem) {
            $tags = ($tagsBySystem[$system->id] ?? collect())->map(fn ($t) => $t->tag)->values();
            $grades = $gradesBySystem[$system->id] ?? null;
            $primary = $primaryCaveBySystem[$system->id] ?? null;
            $count = (int) ($entranceCountBySystem[$system->id]->cnt ?? 0);
            $imageUrl = $imageBySystem[$system->id] ?? null;

            $excerpt = null;
            if ($system->description) {
                $excerpt = mb_substr(strip_tags($system->description), 0, 200);
            }

            // When a system has a single entrance, the cave page is the better landing page
            // (the system page would mostly duplicate the cave content).
            $preferredLink = ($count === 1 && $primary?->slug)
                ? "/caves/{$primary->slug}"
                : "/cave-systems/{$system->slug}";

            return [
                'id' => $system->id,
                'name' => $system->name,
                'slug' => $system->slug,
                'system_url' => "/cave-systems/{$system->slug}",
                'preferred_link' => $preferredLink,
                'length_m' => $system->length,
                'vertical_range_m' => $system->vertical_range,
                'grades' => $grades,
                'tags' => $tags,
                'description' => $excerpt,
                'entrance_count' => $count,
                'primary_cave_name' => $primary?->name,
                'primary_cave_slug' => $primary?->slug,
                'primary_cave_url' => $primary?->slug ? "/caves/{$primary->slug}" : null,
                'location_name' => $primary?->location_name,
                'latitude' => $primary ? (float) $primary->location_lat : null,
                'longitude' => $primary ? (float) $primary->location_lng : null,
                'image_url' => $imageUrl,
            ];
        });

        $result = [
            'count' => $mapped->count(),
            'cave_systems' => $mapped->values()->toArray(),
        ];

        // When no caves match, surface what filters were tried plus the documented
        // tag taxonomy so the model has a clear next move (or can tell the user
        // there's no data). The strongest signal here is "stop retrying with
        // variations" — small models tend to spam search_caves otherwise.
        if ($mapped->isEmpty()) {
            $result['hint'] = 'No caves matched these filters. Do NOT retry this tool with variations — '
                .'the data simply isn\'t in Subterra. Either tell the user no matches were found and '
                .'suggest they widen their search, or use list_collections / get_user_experience instead.';
            $result['filters_tried'] = array_filter([
                'name' => $arguments['name'] ?? null,
                'region' => $arguments['region'] ?? null,
                'tags' => $arguments['tags'] ?? null,
                'min_length' => $arguments['min_length'] ?? null,
                'max_length' => $arguments['max_length'] ?? null,
                'not_visited' => $arguments['not_visited'] ?? null,
            ], fn ($v) => $v !== null);
            $result['valid_tags_reference'] = [
                'region' => ['Northern', 'Mendip', 'South Wales', 'North Wales', 'Peak District', 'Forest of Dean', 'Devon', 'Portland', 'Scotland'],
                'tackle' => ['SRT', 'Ladder', 'Handline', 'No Tackle'],
                'access' => ['Open', 'Permit', 'Padlocked', 'Warden', 'Keycode', 'Closed'],
            ];
            $result['note_on_difficulty'] = 'Subterra has no "Sporting" / "Beginner" / "Hard" / '
                .'"Streamway" / "Through Trip" / "Showcave" tags. Do NOT retry with those — use '
                .'min_length / max_length, list_routes for route grades, or tackle tags as proxies.';
        }

        return $result;
    }

    /**
     * Expand a region term to an array of search terms covering common synonyms,
     * so that "Yorkshire Dales" also matches the "Northern" tag and "Brecon
     * Beacons" matches "South Wales", etc.
     *
     * @return string[]
     */
    private function expandRegionSearchTerms(string $region): array
    {
        $lower = strtolower(trim($region));
        $terms = [$region];

        $synonyms = [
            'yorkshire' => ['Northern'],
            'yorkshire dales' => ['Northern'],
            'dales' => ['Northern'],
            'northern' => ['Northern'],
            'brecon beacons' => ['South Wales', 'Brecon'],
            'snowdonia' => ['North Wales'],
            'south wales' => ['South Wales'],
            'north wales' => ['North Wales'],
            'mendip hills' => ['Mendip'],
            'mendips' => ['Mendip'], // kept as alias in case the model passes "Mendips"
            'peak district' => ['Peak District', 'Derbyshire'],
            'derbyshire' => ['Peak District', 'Derbyshire'],
            'forest of dean' => ['Forest of Dean'],
            'highlands' => ['Assynt', 'Scotland'],
            'scottish highlands' => ['Assynt', 'Scotland'],
        ];

        foreach ($synonyms as $key => $extras) {
            if (str_contains($lower, $key)) {
                $terms = array_merge($terms, $extras);
            }
        }

        return array_values(array_unique($terms));
    }
}
