<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\User;

class ResolveCaveTool extends ImportTool
{
    private const MAX_PER_CALL = 15;

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'resolve_cave',
                'description' => 'Set the cave for staged trips once the user has confirmed it. Each resolution applies to every '
                    .'pending row with that cave_name (as shown in caves_to_resolve), or to the given row_numbers. '
                    .'Omit entrance_cave_slug to use the obvious entrance where there is one.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'resolutions' => [
                            'type' => 'array',
                            'description' => 'Up to 15.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'cave_name' => ['type' => 'string', 'description' => 'The cave_name exactly as shown in caves_to_resolve.'],
                                    'row_numbers' => ['type' => 'array', 'items' => ['type' => 'integer'], 'description' => 'Instead of cave_name: just these rows.'],
                                    'cave_system_slug' => ['type' => 'string'],
                                    'entrance_cave_slug' => ['type' => 'string'],
                                    'exit_cave_slug' => ['type' => 'string', 'description' => 'Through trips only.'],
                                ],
                                'required' => ['cave_system_slug'],
                            ],
                        ],
                    ],
                    'required' => ['resolutions'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $import = $this->openImport($user);
        if (!$import) {
            return $this->noImport();
        }

        $results = [];
        foreach (array_slice(array_filter((array) ($arguments['resolutions'] ?? []), 'is_array'), 0, self::MAX_PER_CALL) as $r) {
            $results[] = ['cave_name' => $r['cave_name'] ?? null, 'row_numbers' => $r['row_numbers'] ?? null] + $this->imports->resolveCave(
                $import,
                $user,
                isset($r['cave_name']) ? (string) $r['cave_name'] : null,
                self::rowNumbers($r['row_numbers'] ?? null),
                (string) ($r['cave_system_slug'] ?? ''),
                !empty($r['entrance_cave_slug']) ? (string) $r['entrance_cave_slug'] : null,
                !empty($r['exit_cave_slug']) ? (string) $r['exit_cave_slug'] : null,
            );
        }

        if ($results === []) {
            return ['error' => 'No resolutions given.'];
        }

        return ['results' => $results, 'counts' => $this->imports->counts($import)];
    }
}
