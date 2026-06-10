<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Trip;
use App\Models\User;
use App\Services\Assistant\AssistantTool;

class GetCaveDetailsTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_cave_details',
                'description' => 'Get full details for a specific cave system: entrances with access info, surveyed routes with grades and durations, tags, description, and the most recent public trip reports (useful for conditions context like high water levels). Use this when you need to present detailed information about a particular system.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'cave_system_id' => [
                            'type' => 'integer',
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

        $system = CaveSystem::with(['caves.heroImage', 'caves.entranceImage', 'tags', 'routes'])->find($systemId);

        if (!$system) {
            return ['error' => "Cave system with ID {$systemId} not found."];
        }

        // NB: no per-cave length/depth — those columns don't exist on the caves
        // table (length/vertical_range live on the system and are returned at
        // the top level of this payload). Including always-null keys here was
        // teaching the model that cave-level measurements might exist.
        $caves = $system->caves->map(fn (Cave $cave) => [
            'id' => $cave->id,
            'name' => $cave->name,
            'slug' => $cave->slug,
            'cave_url' => "/caves/{$cave->slug}",
            'location_name' => $cave->location_name,
            'access_info' => $cave->access_info,
            'latitude' => $cave->location_lat ? (float) $cave->location_lat : null,
            'longitude' => $cave->location_lng ? (float) $cave->location_lng : null,
            'image_url' => $cave->heroImage?->url ?? $cave->entranceImage?->url ?? null,
        ])->values();

        // System-level hero image — first cave's hero, then any cave's entrance image
        $systemImage = $caves->pluck('image_url')->first(fn ($u) => !empty($u));

        $routes = $system->routes->map(fn ($route) => [
            'name' => $route->name,
            'grade' => $route->grade,
            'duration_minutes' => $route->duration,
            'description' => $route->description
                ? mb_substr(strip_tags($route->description), 0, 500)
                : null,
        ])->values();

        $tags = $system->tags->map(fn ($t) => [
            'tag' => $t->tag,
            'category' => $t->category,
        ])->values();

        $description = $system->description
            ? mb_substr(strip_tags($system->description), 0, 1000)
            : null;

        // Most recent visible trip reports — useful for surfacing conditions, water levels, etc.
        $recentReports = Trip::where('cave_system_id', $system->id)
            ->whereIn('visibility', ['public', 'club'])
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderByDesc('start_time')
            ->limit(5)
            ->get(['short_id', 'name', 'description', 'start_time'])
            ->map(fn ($t) => [
                'short_id' => $t->short_id,
                'title' => $t->name ?: 'Trip report',
                'date' => $t->start_time?->format('Y-m-d'),
                'description' => mb_substr(strip_tags($t->description), 0, 600),
                'url' => "/trips/{$t->short_id}",
            ])
            ->values()
            ->all();

        $entranceCount = $caves->count();
        $primaryCave = $caves->first();
        $preferredLink = ($entranceCount === 1 && $primaryCave)
            ? $primaryCave['cave_url']
            : "/cave-systems/{$system->slug}";

        return [
            'id' => $system->id,
            'name' => $system->name,
            'slug' => $system->slug,
            'system_url' => "/cave-systems/{$system->slug}",
            'preferred_link' => $preferredLink,
            'entrance_count' => $entranceCount,
            'length_m' => $system->length,
            'vertical_range_m' => $system->vertical_range,
            'description' => $description,
            'image_url' => $systemImage,
            'tags' => $tags,
            'entrances' => $caves,
            'routes' => $routes,
            'recent_reports' => $recentReports,
        ];
    }
}
