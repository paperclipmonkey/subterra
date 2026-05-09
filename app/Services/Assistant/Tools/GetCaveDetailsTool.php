<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\CaveSystem;
use App\Models\User;
use App\Services\Assistant\AssistantTool;

class GetCaveDetailsTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => 'get_cave_details',
                'description' => 'Get full details for a specific cave system: entrances with access info, surveyed routes with grades and durations, tags, and a description. Use this after search_caves when you need to present detailed information about a particular system.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'cave_system_id' => [
                            'type'        => 'integer',
                            'description' => 'The numeric ID of the cave system (returned by search_caves).',
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

        $system = CaveSystem::with(['caves', 'tags', 'routes'])->find($systemId);

        if (!$system) {
            return ['error' => "Cave system with ID {$systemId} not found."];
        }

        $caves = $system->caves->map(fn ($cave) => [
            'id'            => $cave->id,
            'name'          => $cave->name,
            'slug'          => $cave->slug,
            'location_name' => $cave->location_name,
            'depth_m'       => $cave->depth,
            'length_m'      => $cave->length,
            'access_info'   => $cave->access_info,
            'latitude'      => $cave->location_lat ? (float) $cave->location_lat : null,
            'longitude'     => $cave->location_lng ? (float) $cave->location_lng : null,
        ])->values();

        $routes = $system->routes->map(fn ($route) => [
            'name'             => $route->name,
            'grade'            => $route->grade,
            'duration_minutes' => $route->duration,
            'description'      => $route->description
                ? mb_substr(strip_tags($route->description), 0, 500)
                : null,
        ])->values();

        $tags = $system->tags->map(fn ($t) => [
            'tag'      => $t->tag,
            'category' => $t->category,
        ])->values();

        $description = $system->description
            ? mb_substr(strip_tags($system->description), 0, 1000)
            : null;

        return [
            'id'               => $system->id,
            'name'             => $system->name,
            'slug'             => $system->slug,
            'length_m'         => $system->length,
            'vertical_range_m' => $system->vertical_range,
            'description'      => $description,
            'tags'             => $tags,
            'entrances'        => $caves,
            'routes'           => $routes,
        ];
    }
}
