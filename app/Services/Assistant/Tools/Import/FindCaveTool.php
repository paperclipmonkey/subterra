<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\CaveSystem;
use App\Models\User;
use App\Services\TripImport\CaveMatcher;

class FindCaveTool extends ImportTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'find_cave',
                'description' => 'Look up a cave by name to get its cave_system_slug and entrance slugs, e.g. when the user gives '
                    .'a different name for a cave that was not found. Returns up to 5 systems with their entrances.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'description' => 'Cave, system or entrance name.'],
                    ],
                    'required' => ['name'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $name = mb_substr(trim((string) ($arguments['name'] ?? '')), 0, 100);
        if (mb_strlen($name) < 2) {
            return ['error' => 'Give at least 2 characters.'];
        }

        $match = (new CaveMatcher())->match($name);
        $systems = $match['cave_system_id']
            ? [CaveMatcher::describeSystem(CaveSystem::findOrFail($match['cave_system_id']))]
            : $match['candidates'];

        return [
            'count' => count($systems),
            'exact' => $match['cave_system_id'] !== null,
            'systems' => $systems,
            'note' => $systems === []
                ? "Not in Subterra. Ask whether it has another name; otherwise skip those rows. Don't search again with small variations."
                : null,
        ];
    }
}
