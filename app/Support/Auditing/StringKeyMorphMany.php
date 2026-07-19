<?php

declare(strict_types=1);

namespace App\Support\Auditing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A MorphMany that always compares the morph foreign key ("auditable_id") as a
 * string.
 *
 * The audits.auditable_id column is a VARCHAR (it has to hold both the integer
 * IDs of models like Trip/Cave and the string IDs of User). Eloquent, however,
 * builds the relationship constraint from the parent model's key type. For an
 * integer-keyed model that means:
 *
 *   - eager loading emits `whereIntegerInRaw`, producing an unquoted literal
 *     `... in (152)` which Postgres reads as an integer, and
 *   - lazy loading binds the parent key as an integer.
 *
 * Either way Postgres raises "operator does not exist: character varying =
 * integer". SQLite's loose typing hides this locally, so it only surfaces in
 * production. Casting the compared values to strings keeps the query valid on
 * both drivers.
 *
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends \Illuminate\Database\Eloquent\Relations\MorphMany<TRelatedModel, TDeclaringModel>
 */
class StringKeyMorphMany extends MorphMany
{
    /**
     * Cast the parent key to a string for the lazy-loading constraint.
     */
    public function getParentKey()
    {
        $key = parent::getParentKey();

        return $key === null ? null : (string) $key;
    }

    /**
     * Cast the collected keys to strings for the eager-loading constraint.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @return array<int, string>
     */
    protected function getKeys(array $models, $key = null)
    {
        return array_map(
            static fn ($value): string => (string) $value,
            parent::getKeys($models, $key),
        );
    }

    /**
     * Force a parameter-bound `whereIn` instead of `whereIntegerInRaw`, so the
     * keys are sent to Postgres as text rather than as bare integer literals.
     */
    protected function whereInMethod(Model $model, $key)
    {
        return 'whereIn';
    }
}
