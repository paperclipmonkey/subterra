<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Support\AddableUsers;
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
                    .'Returns users who are searchable (visibility_addable is public) or share a club with the current user. '
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

        $results = AddableUsers::query($user)
            ->where(DB::raw('LOWER(users.name)'), 'like', '%'.mb_strtolower($query).'%')
            ->select(['users.id', 'users.name'])
            ->orderBy('users.name')
            ->limit(15)
            ->get();

        // Enrich with club names for disambiguation
        $clubsByUser = AddableUsers::clubNames($results->pluck('id')->all());

        $users = $results->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'clubs' => $clubsByUser[$u->id] ?? [],
        ])->values()->all();

        return [
            'count' => count($users),
            'users' => $users,
            'note' => count($users) === 0
                ? 'No matching users found. If the person is not on Subterra, record them as a guest: their name is noted in the trip description.'
                : null,
        ];
    }
}
