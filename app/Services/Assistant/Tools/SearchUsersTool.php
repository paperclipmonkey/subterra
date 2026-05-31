<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use Illuminate\Support\Facades\DB;

class SearchUsersTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'search_users',
                'description' => 'Search for Subterra users by name to tag them as participants on a trip report. '
                    .'Returns users who are searchable (visibility_addable = true) or share a club with the current user. '
                    .'Use this when the user mentions caving companions by name and you need their user IDs to tag them.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'Name to search for (partial match, case-insensitive). At least 2 characters.',
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if (mb_strlen($query) < 2) {
            return ['error' => 'Search query must be at least 2 characters.', 'users' => []];
        }

        // Limit query length to prevent abuse
        $query = mb_substr($query, 0, 100);

        // Find clubs the current user belongs to (approved membership only)
        $userClubIds = DB::table('club_user')
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->pluck('club_id')
            ->toArray();

        // Search for users whose name matches AND who are either:
        //   a) publicly searchable (visibility_addable = true), OR
        //   b) share an approved club membership with the current user
        $results = DB::table('users')
            ->where('users.is_active', true)
            ->where('users.id', '!=', $user->id)
            ->where(DB::raw('LOWER(users.name)'), 'like', '%'.mb_strtolower($query).'%')
            ->where(function ($q) use ($userClubIds) {
                $q->where('users.visibility_addable', true);
                if (!empty($userClubIds)) {
                    $q->orWhereExists(function ($sub) use ($userClubIds) {
                        $sub->select(DB::raw(1))
                            ->from('club_user')
                            ->whereColumn('club_user.user_id', 'users.id')
                            ->where('club_user.status', 'approved')
                            ->whereIn('club_user.club_id', $userClubIds);
                    });
                }
            })
            ->select(['users.id', 'users.name'])
            ->orderBy('users.name')
            ->limit(15)
            ->get();

        // Enrich with club names for disambiguation
        $userIds = $results->pluck('id')->toArray();
        $clubsByUser = [];
        if (!empty($userIds)) {
            $clubsByUser = DB::table('club_user')
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

        $users = $results->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'clubs' => $clubsByUser[$u->id] ?? [],
        ])->values()->all();

        return [
            'count' => count($users),
            'users' => $users,
            'note' => count($users) === 0
                ? 'No matching users found. If the person is not on Subterra, note their name in additional_participants when creating the trip.'
                : null,
        ];
    }
}
