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
