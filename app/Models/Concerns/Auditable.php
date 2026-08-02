<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\Auditing\StringKeyMorphMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OwenIt\Auditing\Auditable as BaseAuditable;

/**
 * Wraps the owen-it Auditable trait so that the audits relationship compares
 * auditable_id as a string.
 *
 * auditable_id is a VARCHAR column (it stores both integer model IDs and the
 * User model's string ID). Without this override, integer-keyed models build
 * the relationship with an integer key and PostgreSQL rejects the query with
 * "operator does not exist: character varying = integer" (SQLSTATE 42883).
 * SQLite's loose typing hides this locally, so the failure only surfaces in
 * production. See {@see StringKeyMorphMany} for the details.
 *
 * Models should use this concern instead of OwenIt\Auditing\Auditable directly.
 *
 * `audits` is the only morph relationship on the auditable models, so routing
 * every morphMany through {@see StringKeyMorphMany} is safe and keeps the
 * relationship type unchanged.
 */
trait Auditable
{
    use BaseAuditable;

    /**
     * @template TRelatedModel of Model
     * @template TDeclaringModel of Model
     *
     * @param  Builder<TRelatedModel>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     * @return MorphMany<TRelatedModel, TDeclaringModel>
     */
    protected function newMorphMany(Builder $query, Model $parent, $type, $id, $localKey): MorphMany
    {
        return new StringKeyMorphMany($query, $parent, $type, $id, $localKey);
    }
}
