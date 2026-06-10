<?php

declare(strict_types=1);

namespace App\Services\DataHealth;

use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Support\Facades\DB;

/**
 * Read-only queries that surface data-quality problems in the cave database.
 *
 * Used by Pip's data-steward tools to find records worth fixing. Every method
 * is a pure query — fixes always flow through SuggestedEdit proposals, never
 * direct writes.
 */
class DataHealthService
{
    public const ISSUE_TYPES = [
        'missing_length_depth',
        'missing_coordinates',
        'missing_region_tag',
        'missing_description',
        'unlinked_entrances',
    ];

    /**
     * Counts for every known issue type — the entry point for "how bad is it?".
     *
     * @return array<string, int>
     */
    public function summary(): array
    {
        return [
            'missing_length_depth' => $this->systemsMissingLengthDepthQuery()->count(),
            'missing_coordinates' => $this->cavesMissingCoordinatesQuery()->count(),
            'missing_region_tag' => $this->cavesMissingRegionTagQuery()->count(),
            'missing_description' => $this->systemsMissingDescriptionQuery()->count(),
            'unlinked_entrances' => count($this->unlinkedEntranceCandidates(1000)),
            'total_cave_systems' => CaveSystem::count(),
            'total_caves' => Cave::count(),
        ];
    }

    /**
     * Cave systems with no length and/or no vertical range recorded.
     *
     * @return array<int, array<string, mixed>>
     */
    public function systemsMissingLengthDepth(int $limit = 25, int $offset = 0, ?string $region = null, ?string $registry = null): array
    {
        $query = $this->systemsMissingLengthDepthQuery();

        if ($region !== null) {
            $this->filterSystemsByRegion($query, $region);
        }

        if ($registry !== null) {
            $query->whereHas('caves', fn ($q) => $q->whereRaw('LOWER(registry) = ?', [mb_strtolower($registry)]));
        }

        return $query
            ->with(['caves:id,cave_system_id,name,slug,registry,registry_id,length,depth', 'tags'])
            ->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (CaveSystem $system) => [
                'cave_system_id' => $system->id,
                'name' => $system->name,
                'slug' => $system->slug,
                'length_m' => $system->length,
                'vertical_range_m' => $system->vertical_range,
                'missing' => array_values(array_filter([
                    empty($system->length) ? 'length' : null,
                    empty($system->vertical_range) ? 'vertical_range' : null,
                ])),
                'description_excerpt' => $system->description
                    ? mb_substr($system->description, 0, 600)
                    : null,
                'regions' => $system->tags->where('category', 'region')->pluck('tag')->values(),
                'entrances' => $system->caves->map(fn (Cave $cave) => [
                    'cave_id' => $cave->id,
                    'name' => $cave->name,
                    'registry' => $cave->getRawOriginal('registry'),
                    'registry_id' => $cave->getRawOriginal('registry_id'),
                    'length_m' => $cave->length,
                    'depth_m' => $cave->depth,
                ])->values(),
            ])
            ->values()
            ->all();
    }

    /**
     * Caves with no usable coordinates.
     *
     * @return array<int, array<string, mixed>>
     */
    public function cavesMissingCoordinates(int $limit = 25, int $offset = 0): array
    {
        return $this->cavesMissingCoordinatesQuery()
            ->with('system:id,name,slug')
            ->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (Cave $cave) => [
                'cave_id' => $cave->id,
                'name' => $cave->name,
                'slug' => $cave->slug,
                'cave_system_id' => $cave->cave_system_id,
                'system_name' => $cave->system?->name,
                'location_name' => $cave->location_name,
                'registry' => $cave->getRawOriginal('registry'),
            ])
            ->values()
            ->all();
    }

    /**
     * Caves carrying no region-category tag (breaks region search/filtering).
     *
     * @return array<int, array<string, mixed>>
     */
    public function cavesMissingRegionTag(int $limit = 25, int $offset = 0): array
    {
        return $this->cavesMissingRegionTagQuery()
            ->with('system:id,name,slug')
            ->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (Cave $cave) => [
                'cave_id' => $cave->id,
                'name' => $cave->name,
                'slug' => $cave->slug,
                'cave_system_id' => $cave->cave_system_id,
                'system_name' => $cave->system?->name,
                'location_name' => $cave->location_name,
                'location_lat' => $cave->location_lat,
                'location_lng' => $cave->location_lng,
            ])
            ->values()
            ->all();
    }

    /**
     * Cave systems with no description (or a trivially short one).
     *
     * @return array<int, array<string, mixed>>
     */
    public function systemsMissingDescription(int $limit = 25, int $offset = 0): array
    {
        return $this->systemsMissingDescriptionQuery()
            ->withCount('caves')
            ->orderBy('name')
            ->skip($offset)
            ->take($limit)
            ->get()
            ->map(fn (CaveSystem $system) => [
                'cave_system_id' => $system->id,
                'name' => $system->name,
                'slug' => $system->slug,
                'entrance_count' => $system->caves_count,
            ])
            ->values()
            ->all();
    }

    /**
     * Pairs of caves in DIFFERENT systems whose entrances sit within a few
     * hundred metres of each other — strong candidates for being the same
     * system that was imported as separate records.
     *
     * @return array<int, array<string, mixed>>
     */
    /**
     * Hard cap on candidate pairs pulled from the bounding-box join. Keeps the
     * scan (and summary()) bounded on large datasets; ordering by id keeps
     * paging deterministic. Pairs beyond the cap are simply not surfaced in
     * this scan — they show up once nearer pairs have been resolved.
     */
    private const MAX_PAIR_SCAN = 2000;

    public function unlinkedEntranceCandidates(int $limit = 25, int $offset = 0, float $maxDistanceM = 400.0): array
    {
        // Bounding-box self-join first (cheap), haversine refinement second.
        // 0.01 degrees latitude ≈ 1.1 km, comfortably wider than any sensible
        // $maxDistanceM, so the box never excludes a true positive.
        $pairs = DB::table('caves as a')
            ->join('caves as b', function ($join) {
                $join->on('a.id', '<', 'b.id')
                    ->whereColumn('a.cave_system_id', '!=', 'b.cave_system_id');
            })
            ->join('cave_systems as sa', 'sa.id', '=', 'a.cave_system_id')
            ->join('cave_systems as sb', 'sb.id', '=', 'b.cave_system_id')
            ->whereNotNull('a.location_lat')
            ->whereNotNull('a.location_lng')
            ->whereNotNull('b.location_lat')
            ->whereNotNull('b.location_lng')
            ->whereRaw('ABS(a.location_lat - b.location_lat) < 0.01')
            ->whereRaw('ABS(a.location_lng - b.location_lng) < 0.02')
            ->select([
                'a.id as a_id', 'a.name as a_name', 'a.location_lat as a_lat', 'a.location_lng as a_lng',
                'a.cave_system_id as a_system_id', 'sa.name as a_system_name',
                'b.id as b_id', 'b.name as b_name', 'b.location_lat as b_lat', 'b.location_lng as b_lng',
                'b.cave_system_id as b_system_id', 'sb.name as b_system_name',
            ])
            ->orderBy('a.id')
            ->orderBy('b.id')
            ->limit(self::MAX_PAIR_SCAN)
            ->get();

        $candidates = [];
        foreach ($pairs as $pair) {
            $distanceM = $this->haversineKm(
                (float) $pair->a_lat,
                (float) $pair->a_lng,
                (float) $pair->b_lat,
                (float) $pair->b_lng
            ) * 1000;

            if ($distanceM > $maxDistanceM) {
                continue;
            }

            $candidates[] = [
                'cave_a' => ['cave_id' => $pair->a_id, 'name' => $pair->a_name, 'cave_system_id' => $pair->a_system_id, 'system_name' => $pair->a_system_name],
                'cave_b' => ['cave_id' => $pair->b_id, 'name' => $pair->b_name, 'cave_system_id' => $pair->b_system_id, 'system_name' => $pair->b_system_name],
                'distance_m' => (int) round($distanceM),
                'name_similarity' => $this->nameSimilarity($pair->a_system_name, $pair->b_system_name),
            ];
        }

        usort($candidates, fn ($x, $y) => $x['distance_m'] <=> $y['distance_m']);

        return array_slice($candidates, $offset, $limit);
    }

    /**
     * For one cave system, find other systems it might need linking/merging
     * with — by entrance proximity and by name similarity.
     *
     * @return array<string, mixed>
     */
    public function findLinkCandidates(CaveSystem $system, float $maxDistanceKm = 2.0): array
    {
        $system->loadMissing('caves');

        $byProximity = [];
        $reference = $system->caves->first(fn (Cave $c) => $c->location_lat && $c->location_lng);

        if ($reference) {
            $nearby = Cave::query()
                ->where('cave_system_id', '!=', $system->id)
                ->whereNotNull('location_lat')
                ->whereNotNull('location_lng')
                ->whereRaw('ABS(location_lat - ?) < ?', [$reference->location_lat, $maxDistanceKm / 111.0 * 1.5])
                ->whereRaw('ABS(location_lng - ?) < ?', [$reference->location_lng, $maxDistanceKm / 70.0 * 1.5])
                ->with('system:id,name,slug,length,vertical_range')
                ->get();

            foreach ($nearby as $cave) {
                $distanceKm = $this->haversineKm(
                    (float) $reference->location_lat,
                    (float) $reference->location_lng,
                    (float) $cave->location_lat,
                    (float) $cave->location_lng
                );

                if ($distanceKm > $maxDistanceKm || !$cave->system) {
                    continue;
                }

                $existing = $byProximity[$cave->cave_system_id]['distance_m'] ?? PHP_INT_MAX;
                $distanceM = (int) round($distanceKm * 1000);
                if ($distanceM < $existing) {
                    $byProximity[$cave->cave_system_id] = [
                        'cave_system_id' => $cave->cave_system_id,
                        'system_name' => $cave->system->name,
                        'system_slug' => $cave->system->slug,
                        'nearest_entrance' => $cave->name,
                        'distance_m' => $distanceM,
                        'name_similarity' => $this->nameSimilarity($system->name, $cave->system->name),
                    ];
                }
            }
        }

        $byProximity = collect($byProximity)->sortBy('distance_m')->take(10)->values()->all();

        // Name similarity sweep over systems sharing a significant word
        $tokens = collect(preg_split('/\s+/', (string) $system->name))
            ->map(fn ($t) => trim($t, " '’-"))
            ->filter(fn ($t) => mb_strlen($t) >= 4 && !in_array(mb_strtolower($t), ['cave', 'pot', 'hole', 'mine', 'system'], true));

        $byName = [];
        if ($tokens->isNotEmpty()) {
            $query = CaveSystem::query()->where('id', '!=', $system->id);
            $query->where(function ($q) use ($tokens) {
                foreach ($tokens as $token) {
                    $q->orWhereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($token).'%']);
                }
            });

            $byName = $query->limit(10)->get()
                ->map(fn (CaveSystem $match) => [
                    'cave_system_id' => $match->id,
                    'system_name' => $match->name,
                    'system_slug' => $match->slug,
                    'name_similarity' => $this->nameSimilarity($system->name, $match->name),
                ])
                ->sortByDesc('name_similarity')
                ->values()
                ->all();
        }

        return [
            'cave_system_id' => $system->id,
            'system_name' => $system->name,
            'entrances' => $system->caves->map(fn (Cave $c) => [
                'cave_id' => $c->id,
                'name' => $c->name,
                'has_coordinates' => (bool) ($c->location_lat && $c->location_lng),
            ])->values(),
            'nearby_systems' => $byProximity,
            'similar_name_systems' => $byName,
            'note' => $reference
                ? null
                : 'This system has no entrance coordinates, so proximity matching was skipped — only name matches are shown.',
        ];
    }

    private function systemsMissingLengthDepthQuery()
    {
        return CaveSystem::query()->where(function ($q) {
            $q->whereNull('length')
                ->orWhere('length', 0)
                ->orWhereNull('vertical_range')
                ->orWhere('vertical_range', 0);
        });
    }

    private function cavesMissingCoordinatesQuery()
    {
        return Cave::query()->where(function ($q) {
            $q->whereNull('location_lat')->orWhereNull('location_lng');
        });
    }

    private function cavesMissingRegionTagQuery()
    {
        return Cave::query()->whereDoesntHave('tags', fn ($q) => $q->where('category', 'region'));
    }

    private function systemsMissingDescriptionQuery()
    {
        return CaveSystem::query()->where(function ($q) {
            $q->whereNull('description')->orWhereRaw("LENGTH(TRIM(description)) < 20");
        });
    }

    private function filterSystemsByRegion($query, string $region): void
    {
        $lowered = mb_strtolower($region);
        $query->where(function ($q) use ($lowered) {
            $q->whereHas('tags', function ($tq) use ($lowered) {
                $tq->where('category', 'region')->whereRaw('LOWER(tag) = ?', [$lowered]);
            })->orWhereHas('caves.tags', function ($tq) use ($lowered) {
                $tq->where('category', 'region')->whereRaw('LOWER(tag) = ?', [$lowered]);
            });
        });
    }

    /** 0.0–1.0 similarity between two names, ignoring case and punctuation. */
    private function nameSimilarity(?string $a, ?string $b): float
    {
        $normalise = fn (?string $s) => preg_replace('/[^a-z0-9 ]/', '', mb_strtolower((string) $s));
        $a = $normalise($a);
        $b = $normalise($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);

        return round($percent / 100, 2);
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
