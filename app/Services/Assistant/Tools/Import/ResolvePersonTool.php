<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Import;

use App\Models\User;

class ResolvePersonTool extends ImportTool
{
    private const MAX_PER_CALL = 15;

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'resolve_person',
                'description' => 'Settle who a companion name refers to, across every pending row with that name (or just row_numbers). '
                    .'action "tag" links a Subterra user (user_id from people_to_resolve candidates or search_users); '
                    .'"guest" records them by name in the trip description (not on Subterra); "remove" drops them.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'resolutions' => [
                            'type' => 'array',
                            'description' => 'Up to 15.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'name' => ['type' => 'string', 'description' => 'The name exactly as shown in the import.'],
                                    'action' => ['type' => 'string', 'enum' => ['tag', 'guest', 'remove']],
                                    'user_id' => ['type' => 'string', 'description' => 'Required for tag.'],
                                    'row_numbers' => ['type' => 'array', 'items' => ['type' => 'integer']],
                                ],
                                'required' => ['name', 'action'],
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
            $results[] = $this->imports->resolvePerson(
                $import,
                $user,
                (string) ($r['name'] ?? ''),
                (string) ($r['action'] ?? ''),
                isset($r['user_id']) ? (string) $r['user_id'] : null,
                self::rowNumbers($r['row_numbers'] ?? null),
            );
        }

        if ($results === []) {
            return ['error' => 'No resolutions given.'];
        }

        return ['results' => $results, 'counts' => $this->imports->counts($import)];
    }
}
