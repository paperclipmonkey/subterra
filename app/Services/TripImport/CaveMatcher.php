<?php

declare(strict_types=1);

namespace App\Services\TripImport;

use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Matches the free-text cave names in a logbook ("Swildons", "GG via Bar Pot",
 * "Lancaster to County") to Subterra cave systems and entrances.
 *
 * A match is only taken automatically when it is exact (ignoring case,
 * apostrophes and spacing) and unambiguous. Anything looser comes back as
 * suggestions for the user to confirm: importing a trip into the wrong cave
 * is worse than asking.
 */
class CaveMatcher
{
    public const MATCHED = 'matched';
    public const NEEDS_ENTRANCE = 'needs_entrance';
    public const AMBIGUOUS = 'ambiguous';
    public const NOT_FOUND = 'not_found';
    public const MISSING = 'missing';

    private const MAX_SUGGESTIONS = 5;
    private const MAX_ENTRANCES = 10;

    /** @var array<string, array<string, mixed>> */
    private array $memo = [];

    /**
     * @return array{status: string, cave_system_id: int|null, entrance_cave_id: int|null, exit_cave_id: int|null, candidates: array<int, array<string, mixed>>}
     */
    public function match(?string $caveRaw, ?string $entranceRaw = null, ?string $exitRaw = null): array
    {
        $caveRaw = trim((string) $caveRaw);
        $entranceRaw = trim((string) $entranceRaw);
        $exitRaw = trim((string) $exitRaw);

        $key = self::normalise($caveRaw).'|'.self::normalise($entranceRaw).'|'.self::normalise($exitRaw);

        return $this->memo[$key] ??= $this->doMatch($caveRaw, $entranceRaw, $exitRaw);
    }

    /** Lower-case, drop apostrophes (straight and curly) and collapse whitespace. */
    public static function normalise(?string $name): string
    {
        $name = mb_strtolower(trim((string) $name));
        $name = str_replace(["'", '’', '‘', '`'], '', $name);

        return trim(preg_replace('/\s+/', ' ', $name) ?? $name);
    }

    /**
     * Compact description of a system and its entrances for the assistant.
     *
     * @return array<string, mixed>
     */
    public static function describeSystem(CaveSystem $system): array
    {
        $entrances = self::publicCaves($system->id)->orderBy('name')->limit(self::MAX_ENTRANCES + 1)->get(['id', 'name', 'slug']);

        return [
            'cave_system_slug' => $system->slug,
            'name' => $system->name,
            'entrances' => $entrances->take(self::MAX_ENTRANCES)
                ->map(fn (Cave $c) => ['slug' => $c->slug, 'name' => $c->name])->values()->all(),
            'more_entrances' => $entrances->count() > self::MAX_ENTRANCES,
        ];
    }

    /**
     * Pick a system's entrance from a free-text name, or the obvious default
     * (its only entrance, or the entrance named after the system).
     */
    public static function defaultEntrance(CaveSystem $system, ?string $entranceRaw = null): ?Cave
    {
        $caves = self::publicCaves($system->id)->get(['id', 'name', 'slug', 'cave_system_id']);

        $entranceRaw = self::normalise($entranceRaw);
        if ($entranceRaw !== '') {
            $exact = $caves->filter(fn (Cave $c) => self::normalise($c->name) === $entranceRaw || $c->slug === $entranceRaw);
            if ($exact->count() === 1) {
                return $exact->first();
            }
            $partial = $caves->filter(fn (Cave $c) => str_contains(self::normalise($c->name), $entranceRaw));

            return $partial->count() === 1 ? $partial->first() : null;
        }

        if ($caves->count() === 1) {
            return $caves->first();
        }

        $named = $caves->filter(fn (Cave $c) => self::normalise($c->name) === self::normalise($system->name));

        return $named->count() === 1 ? $named->first() : null;
    }

    /** @return Builder<Cave> */
    public static function publicCaves(?int $systemId = null): Builder
    {
        return Cave::query()
            ->where('visibility', 'public')
            ->when($systemId !== null, fn ($q) => $q->where('cave_system_id', $systemId));
    }

    /**
     * @return array{status: string, cave_system_id: int|null, entrance_cave_id: int|null, exit_cave_id: int|null, candidates: array<int, array<string, mixed>>}
     */
    private function doMatch(string $caveRaw, string $entranceRaw, string $exitRaw): array
    {
        // Some logbooks only have an "Entrance" column, or leave Cave blank
        // when the entrance says it all.
        if ($caveRaw === '') {
            $caveRaw = $entranceRaw;
        }
        if ($caveRaw === '') {
            return $this->result(self::MISSING);
        }

        $exact = $this->exactMatch($caveRaw, $entranceRaw, $exitRaw);
        if ($exact !== null) {
            return $exact;
        }

        // "Lancaster Hole to County Pot", "Bar Pot -> Main Shaft"
        $parts = preg_split('/\s+(?:to|->|→|=>|–>|into)\s+/iu', $caveRaw);
        if ($parts !== false && count($parts) === 2 && $entranceRaw === '') {
            $in = $this->exactCaves($parts[0]);
            $out = $this->exactCaves($parts[1]);
            if ($in->count() === 1 && $out->count() === 1 && $in->first()->cave_system_id === $out->first()->cave_system_id) {
                return $this->result(self::MATCHED, $in->first()->cave_system_id, $in->first()->id, $out->first()->id);
            }
        }

        // "Gaping Gill (Bar Pot)", "GG - Bar Pot"
        if (preg_match('/^(.+?)\s*(?:\((.+)\)|\s[-–:]\s(.+))$/u', $caveRaw, $m)) {
            $inner = trim($m[2] ?? '') !== '' ? $m[2] : ($m[3] ?? '');
            $retry = $this->exactMatch(trim($m[1]), $entranceRaw !== '' ? $entranceRaw : trim($inner), $exitRaw);
            if ($retry !== null && $retry['status'] === self::MATCHED) {
                return $retry;
            }
        }

        $suggestions = $this->fuzzySystems($caveRaw);

        return $suggestions->isEmpty()
            ? $this->result(self::NOT_FOUND)
            : $this->result(self::AMBIGUOUS, candidates: $suggestions->map(fn ($s) => self::describeSystem($s))->values()->all());
    }

    /**
     * @return array{status: string, cave_system_id: int|null, entrance_cave_id: int|null, exit_cave_id: int|null, candidates: array<int, array<string, mixed>>}|null
     */
    private function exactMatch(string $caveRaw, string $entranceRaw, string $exitRaw): ?array
    {
        $systems = $this->exactSystems($caveRaw);
        $caves = $this->exactCaves($caveRaw);

        /** @var array<int, CaveSystem> $candidateSystems */
        $candidateSystems = $systems->keyBy('id')->all();
        foreach ($caves as $cave) {
            if ($cave->system && !isset($candidateSystems[$cave->cave_system_id])) {
                $candidateSystems[$cave->cave_system_id] = $cave->system;
            }
        }

        if (count($candidateSystems) === 0) {
            return null;
        }

        if (count($candidateSystems) > 1) {
            return $this->result(self::AMBIGUOUS, candidates: collect($candidateSystems)
                ->take(self::MAX_SUGGESTIONS)->map(fn ($s) => self::describeSystem($s))->values()->all());
        }

        $system = reset($candidateSystems);
        $namedCave = $caves->firstWhere('cave_system_id', $system->id);

        $entrance = $entranceRaw !== ''
            ? self::defaultEntrance($system, $entranceRaw)
            : ($namedCave ?? self::defaultEntrance($system));

        $exit = $exitRaw !== '' ? self::defaultEntrance($system, $exitRaw) : null;

        if ($entrance === null || ($exitRaw !== '' && $exit === null)) {
            return $this->result(self::NEEDS_ENTRANCE, $system->id, $entrance?->id, $exit?->id, [self::describeSystem($system)]);
        }

        return $this->result(self::MATCHED, $system->id, $entrance->id, $exit?->id);
    }

    /** @return Collection<int, CaveSystem> */
    private function exactSystems(string $name): Collection
    {
        return CaveSystem::query()
            ->whereHas('caves', fn ($q) => $q->where('visibility', 'public'))
            ->whereRaw(self::normalisedColumn('name').' = ?', [self::normalise($name)])
            ->limit(self::MAX_SUGGESTIONS)
            ->get();
    }

    /** @return Collection<int, Cave> */
    private function exactCaves(string $name): Collection
    {
        return self::publicCaves()
            ->with('system')
            ->whereRaw(self::normalisedColumn('name').' = ?', [self::normalise($name)])
            ->limit(self::MAX_SUGGESTIONS)
            ->get();
    }

    /**
     * Systems whose name, or one of whose entrances' names, contains the raw
     * name — or, failing that, its most distinctive word. Ranked by similarity.
     *
     * @return \Illuminate\Support\Collection<int, CaveSystem>
     */
    private function fuzzySystems(string $raw): \Illuminate\Support\Collection
    {
        $needle = self::normalise($raw);
        $found = $this->systemsContaining($needle);

        if ($found->isEmpty()) {
            $words = collect(preg_split('/[\s\-,.()\/]+/', $needle) ?: [])
                ->reject(fn ($w) => mb_strlen($w) < 4 || in_array($w, ['cave', 'caves', 'hole', 'pot', 'pots', 'swallet', 'ogof', 'mine', 'system', 'entrance', 'trip'], true))
                ->sortByDesc(fn ($w) => mb_strlen($w));
            foreach ($words as $word) {
                $found = $this->systemsContaining($word);
                if ($found->isNotEmpty()) {
                    break;
                }
            }
        }

        return $found
            ->sortByDesc(function (CaveSystem $s) use ($needle) {
                similar_text($needle, self::normalise($s->name), $percent);

                return $percent;
            })
            ->take(self::MAX_SUGGESTIONS)
            ->values();
    }

    /** @return Collection<int, CaveSystem> */
    private function systemsContaining(string $needle): Collection
    {
        if (mb_strlen($needle) < 3) {
            return new Collection();
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $needle).'%';

        return CaveSystem::query()
            ->whereHas('caves', fn ($q) => $q->where('visibility', 'public'))
            ->where(function ($q) use ($like) {
                $q->whereRaw(self::normalisedColumn('cave_systems.name').' LIKE ?', [$like])
                    ->orWhereExists(function ($sub) use ($like) {
                        $sub->selectRaw('1')
                            ->from('caves')
                            ->whereColumn('caves.cave_system_id', 'cave_systems.id')
                            ->whereNull('caves.deleted_at')
                            ->where('caves.visibility', 'public')
                            ->whereRaw(self::normalisedColumn('caves.name').' LIKE ?', [$like]);
                    });
            })
            ->limit(25)
            ->get();
    }

    /** SQL mirroring {@see normalise()} — portable between SQLite and Postgres. */
    private static function normalisedColumn(string $column): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE({$column}, '''', ''), '’', ''), '  ', ' '), '  ', ' '))";
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array{status: string, cave_system_id: int|null, entrance_cave_id: int|null, exit_cave_id: int|null, candidates: array<int, array<string, mixed>>}
     */
    private function result(string $status, ?int $systemId = null, ?int $entranceId = null, ?int $exitId = null, array $candidates = []): array
    {
        return [
            'status' => $status,
            'cave_system_id' => $systemId,
            'entrance_cave_id' => $entranceId,
            'exit_cave_id' => $exitId,
            'candidates' => $candidates,
        ];
    }
}
