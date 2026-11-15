<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Name matching for cave imports.
 *
 * Registries spell the same place inconsistently — "Uamh an Claonaite" vs
 * "Uamh An Claonaite", "St Cuthbert's Swallet" vs "St Cuthberts Swallet". The
 * old exact, case-sensitive `where('name', …)` lookup treated these as
 * different places, so the same cave arriving from a second registry created a
 * duplicate record. Matching case-insensitively (and ignoring apostrophes)
 * lets an import recognise a place it already holds.
 *
 * The comparison is done in SQL with LOWER()/REPLACE() so it stays portable
 * between Postgres (production) and SQLite (tests). It can't use an index on
 * `name`, but imports process one cave at a time behind network fetches, so a
 * scan over a few thousand rows is negligible here.
 */
class CaveName
{
    /** Apostrophes, straight and curly ("It’s a Cracker" is "It's a Cracker"). */
    private const APOSTROPHES = ["'", '’', '‘'];

    /**
     * Normalise a name for comparison: lower-cased with apostrophes removed.
     * Mirrors exactly the transformation applied to the column in {@see match()}.
     */
    public static function normalise(?string $name): string
    {
        return mb_strtolower(str_replace(self::APOSTROPHES, '', trim((string) $name)));
    }

    /**
     * Words that say what kind of place it is, or which way in, rather than
     * which place: "Thrupe Lane Swallett" and "Thrupe Lane Swallet", "Hagg
     * Gill" and "Hagg Gill Pot", "Ogof Pwll-y-Rhyd" and "Pwll Y Rhyd Cave".
     */
    private const GENERIC = [
        'cave', 'caves', 'cavern', 'caverns', 'hole', 'holes', 'pot', 'pots', 'pothole', 'swallet', 'swallett',
        'ogof', 'uamh', 'poll', 'the', 'an', 'a', 'y', 'yr', 'na', 'nan', 'nam', 'of', 'and', 'rift',
        'entrance', 'enterance', 'top', 'upper', 'lower', 'bottom', 'main', 'old', 'new', 'original',
    ];

    /** Words that make a name one particular way in ("Yordas Pot (Top Entrance)"). */
    private const ENTRANCE_WORDS = ['entrance', 'enterance', 'top', 'upper', 'lower', 'bottom', 'middle', 'north', 'south', 'east', 'west', 'northern', 'southern', 'eastern', 'western', 'wet', 'dry', 'route'];

    /**
     * The ways a name can be compared loosely: the whole name, and the name
     * outside and inside any brackets ("Liar's Sink (Poll Breugair)"), each
     * reduced to its distinctive words, run together so spacing doesn't
     * matter ("Dwy Sir", "Ddwysir"; "Eye Holes", "Eyeholes").
     *
     * @return list<string>
     */
    public static function looseKeys(?string $name): array
    {
        $name = trim((string) $name);
        $parts = [$name];
        if (preg_match('/^(.*?)\((.*?)\)(.*)$/u', $name, $m)) {
            $parts[] = $m[1].' '.$m[3];
            $parts[] = $m[2];
        }
        foreach (preg_split('#\s*/\s*#u', $name) ?: [] as $part) {   // "Calf Holes / Dry Lathe Cave"
            $parts[] = $part;
        }
        // "Eglwys Faen Crawl Entrance", "Entrance to Ravenswell": the cave, without the way in
        $unbracketed = trim((string) preg_replace('/\(.*?\)/u', ' ', $name));
        $bare = trim((string) preg_replace('/\b(\w+\s+)?ent(e)?rance\b(\s+to\b)?/iu', ' ', $unbracketed));
        if ($bare !== $unbracketed) {
            $parts[] = $bare;
        }

        $keys = [];
        foreach ($parts as $part) {
            $plain = self::plain($part);
            $words = array_values(array_diff(explode(' ', $plain), self::GENERIC, ['']));
            foreach ([implode('', $words), str_replace(' ', '', $plain)] as $key) {
                if (mb_strlen($key) >= 3) {
                    $keys[] = $key;
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * Whether two names plausibly name the same place, allowing for the
     * spellings registries and OpenStreetMap differ by: "G.B. Cave" and "GB
     * cave", "Vespers Pot" and "Vesper Pot", "Ogof Tarddiad Rhymni" and "Ogof
     * Tarddiad Rhymney". Loose on purpose: only for places already close
     * together, never on its own.
     */
    public static function similar(?string $a, ?string $b): bool
    {
        // numbered caves are different caves: "Cwm Dwr 2" isn't "Cwm Dwr"
        if (self::numbers($a) !== self::numbers($b)) {
            return false;
        }
        foreach (self::looseKeys($a) as $x) {
            foreach (self::looseKeys($b) as $y) {
                if ($x === $y) {
                    return true;
                }
                $len = min(mb_strlen($x), mb_strlen($y));
                // a letter or two apart, in a name long enough for that to be a spelling
                if ($len >= 5 && levenshtein($x, $y) <= max(1, intdiv($len, 7))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether a name says it's one particular way into a cave: "Yordas Pot
     * (Top Entrance)", "Sell Gill Holes (dry route)", "Meregill Hole Aven
     * Entrance".
     */
    public static function namesAnEntrance(?string $name): bool
    {
        $plain = self::plain((string) $name);
        if (preg_match('/\bent[e]?rance\b/u', $plain) || preg_match('/^(upper|lower|top|bottom) /u', $plain)) {
            return true;
        }
        if (preg_match('/\(([^)]*)\)/u', (string) $name, $m)) {
            $words = explode(' ', self::plain($m[1]));

            return array_intersect($words, self::ENTRANCE_WORDS) !== [];
        }

        return false;
    }

    /**
     * The numbers in a name, outside brackets.
     *
     * @return list<string>
     */
    private static function numbers(?string $name): array
    {
        preg_match_all('/\d+/u', (string) preg_replace('/\(.*?\)/u', '', (string) $name), $m);

        return $m[0];
    }

    /** Lower case, no accents or apostrophes, and only letters, digits and single spaces. */
    private static function plain(string $name): string
    {
        $s = mb_strtolower(str_replace(self::APOSTROPHES, '', $name));
        $s = Str::ascii($s);   // "Uamh a’ Bhreugadair", accents
        $s = str_replace('.', '', $s);   // "G.B." is "GB"

        return trim(preg_replace('/[^a-z0-9]+/u', ' ', $s) ?? '');
    }

    /** Find an existing cave whose name matches $name (case/apostrophe-insensitive). */
    public static function findCave(string $name): ?Cave
    {
        return self::match(Cave::query(), $name)->first();
    }

    /** Find an existing cave system whose name matches $name (case/apostrophe-insensitive). */
    public static function findSystem(string $name): ?CaveSystem
    {
        return self::match(CaveSystem::query(), $name)->first();
    }

    /** Caves/systems closer than this (km) are treated as the same physical place. */
    private const SAME_PLACE_KM = 10.0;

    /**
     * Find a cave a registry sync may adopt by name or slug.
     *
     * The same name can refer to two different real-world caves in different
     * regions (e.g. Giant's Cave in both Mendip and the Peak District). A bare
     * name match let one registry's sync fight over another registry's cave, so
     * a name/slug match is only adopted when it is plausibly the *same* place:
     *
     *  - it is unowned (manually created / legacy, no registry), or
     *  - it is already owned by this same registry (idempotent re-sync), or
     *  - it sits within {@see SAME_PLACE_KM} of the incoming coordinates.
     *
     * Geography is what separates "same cave listed by two registries" (adopt)
     * from "two different caves that share a name" (leave alone — the caller
     * creates a distinct record).
     */
    public static function findCaveForRegistry(string $name, ?string $slug, ?string $registryId, $lat = null, $lng = null): ?Cave
    {
        $candidates = self::match(Cave::query(), $name)->get();

        if ($slug !== null && $slug !== '' && $candidates->doesntContain('slug', $slug)) {
            $bySlug = Cave::where('slug', $slug)->first();
            if ($bySlug) {
                $candidates->push($bySlug);
            }
        }

        foreach ($candidates as $cave) {
            if (self::caveAdoptableBy($cave, $registryId, $lat, $lng)) {
                return $cave;
            }
        }

        return null;
    }

    /**
     * Find a cave system a registry sync may adopt by name or slug.
     *
     * System slugs are not region-prefixed, so "Giant's Cave" from two
     * registries collides on both name and slug. Systems carry no registry or
     * coordinates of their own, so adoptability is inferred from their caves: a
     * system is adoptable if it has no caves yet, or any of its caves is itself
     * adoptable (see {@see findCaveForRegistry()}).
     */
    public static function findSystemForRegistry(string $name, ?string $slug, ?string $registryId, $lat = null, $lng = null): ?CaveSystem
    {
        $candidates = self::match(CaveSystem::query(), $name)->get();

        if ($slug !== null && $slug !== '' && $candidates->doesntContain('slug', $slug)) {
            $bySlug = CaveSystem::where('slug', $slug)->first();
            if ($bySlug) {
                $candidates->push($bySlug);
            }
        }

        foreach ($candidates as $system) {
            if (self::systemAdoptableBy($system, $registryId, $lat, $lng)) {
                return $system;
            }
        }

        return null;
    }

    /** Whether $registryId may adopt $cave as the same place. */
    private static function caveAdoptableBy(Cave $cave, ?string $registryId, $lat, $lng): bool
    {
        if ($cave->registry === null || $cave->registry === '') {
            return true; // unowned — claim the manual/legacy record
        }

        if ($registryId !== null && $cave->registry === $registryId) {
            return true; // our own record — idempotent re-sync
        }

        return self::sameLocation($cave->location_lat, $cave->location_lng, $lat, $lng);
    }

    /** Whether $registryId may adopt $system (empty, or holding an adoptable cave). */
    private static function systemAdoptableBy(CaveSystem $system, ?string $registryId, $lat = null, $lng = null): bool
    {
        $caves = $system->caves;

        if ($caves->isEmpty()) {
            return true; // brand-new / empty system
        }

        foreach ($caves as $cave) {
            if (self::caveAdoptableBy($cave, $registryId, $lat, $lng)) {
                return true;
            }
        }

        return false;
    }

    /** Whether two coordinate pairs are within {@see SAME_PLACE_KM} of each other. */
    private static function sameLocation($lat1, $lng1, $lat2, $lng2): bool
    {
        if (!self::hasCoords($lat1, $lng1) || !self::hasCoords($lat2, $lng2)) {
            return false;
        }

        return self::haversineKm((float) $lat1, (float) $lng1, (float) $lat2, (float) $lng2) <= self::SAME_PLACE_KM;
    }

    /** Coordinates are usable when present and not the null-island (0,0) placeholder. */
    private static function hasCoords($lat, $lng): bool
    {
        return $lat !== null && $lng !== null
            && (abs((float) $lat) > 0.0001 || abs((float) $lng) > 0.0001);
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthKm * 2 * asin(min(1.0, sqrt($a)));
    }

    /**
     * Constrain a query to rows whose `name` matches $name, ignoring case and
     * apostrophes. The column is transformed identically to {@see normalise()}.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function match(Builder $query, string $name): Builder
    {
        return $query->whereRaw(
            "LOWER(REPLACE(REPLACE(REPLACE(name, '''', ''), '’', ''), '‘', '')) = ?",
            [self::normalise($name)]
        );
    }
}
