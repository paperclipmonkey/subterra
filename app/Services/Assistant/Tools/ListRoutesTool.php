<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\Route as CaveRoute;
use App\Models\User;
use App\Services\Assistant\AssistantTool;

class ListRoutesTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'list_routes',
                'description' => 'List all surveyed routes through a cave system with grade, typical duration, and description. Use this when the user asks about specific route options or when planning which route to take.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'cave_system_id' => [
                            'type' => 'integer',
                            'description' => 'The numeric ID of the cave system.',
                        ],
                    ],
                    'required' => ['cave_system_id'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $systemId = (int) ($arguments['cave_system_id'] ?? 0);

        $routes = CaveRoute::query()
            ->where('cave_system_id', '=', $systemId)
            ->orderBy('name')
            ->get();

        if ($routes->isEmpty()) {
            return [
                'cave_system_id' => $systemId,
                'routes' => [],
                'message' => 'No surveyed routes have been recorded for this cave system.',
            ];
        }

        return [
            'cave_system_id' => $systemId,
            'routes' => $routes->map(fn ($r) => [
                'name' => $r->name,
                'grade' => $r->grade,
                'duration_minutes' => $r->duration,
                'description' => $r->description
                    ? mb_substr(strip_tags($r->description), 0, 500)
                    : null,
            ])->values(),
        ];
    }
}
