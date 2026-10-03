<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The people a user may tag on a trip: active users who are publicly addable,
 * or who share an approved club with them. Shared by Pip's user search and the
 * trip importer's participant matching so the two can never disagree.
 */
class AddableUsers
{
    public static function query(User $actor): Builder
    {
        $clubIds = DB::table('club_user')
            ->where('user_id', $actor->id)
            ->where('status', 'approved')
            ->pluck('club_id')
            ->all();

        return DB::table('users')
            ->where('users.is_active', true)
            ->where('users.id', '!=', $actor->id)
            ->where(function ($q) use ($clubIds) {
                // A string column ('public'/'club'), not a boolean: comparing it to
                // `true` silently matched nobody.
                $q->where('users.visibility_addable', 'public');
                if (!empty($clubIds)) {
                    $q->orWhereExists(function ($sub) use ($clubIds) {
                        $sub->select(DB::raw(1))
                            ->from('club_user')
                            ->whereColumn('club_user.user_id', 'users.id')
                            ->where('club_user.status', 'approved')
                            ->whereIn('club_user.club_id', $clubIds);
                    });
                }
            });
    }

    /**
     * Approved, active club names per user id, for telling namesakes apart.
     *
     * @param  array<int, string>  $userIds
     * @return array<string, array<int, string>>
     */
    public static function clubNames(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        return DB::table('club_user')
            ->join('clubs', 'clubs.id', '=', 'club_user.club_id')
            ->whereIn('club_user.user_id', $userIds)
            ->where('club_user.status', 'approved')
            ->where('clubs.is_active', true)
            ->select(['club_user.user_id', 'clubs.name as club_name'])
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => $rows->pluck('club_name')->values()->all())
            ->all();
    }
}
