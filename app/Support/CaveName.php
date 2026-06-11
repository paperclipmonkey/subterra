<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Cave;
use App\Models\CaveSystem;
use Illuminate\Database\Eloquent\Builder;

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
    /**
     * Normalise a name for comparison: lower-cased with apostrophes removed.
     * Mirrors exactly the transformation applied to the column in {@see match()}.
     */
    public static function normalise(?string $name): string
    {
        return mb_strtolower(str_replace("'", '', trim((string) $name)));
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
            "LOWER(REPLACE(name, '''', '')) = ?",
            [self::normalise($name)]
        );
    }
}
