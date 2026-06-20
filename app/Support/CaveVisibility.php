<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

/**
 * Centralises the rule for which caves may surface on public/normal paths.
 *
 * The cave list, search, map and AI index use raw query-builder queries that
 * bypass Eloquent's SoftDeletes global scope, so soft-deleted and `admin_only`
 * caves would otherwise leak through them. Apply this to every such query.
 */
class CaveVisibility
{
    /**
     * Restrict a query-builder query to caves that may appear on public/normal
     * surfaces: not soft-deleted and visibility = public.
     *
     * @param  QueryBuilder|JoinClause  $query
     * @return QueryBuilder|JoinClause
     */
    public static function publicOnly($query, string $alias = 'caves')
    {
        return $query
            ->whereNull("{$alias}.deleted_at")
            ->where("{$alias}.visibility", 'public');
    }
}
