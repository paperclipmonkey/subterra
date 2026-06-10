<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools\Admin;

use App\Models\CaveSystem;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use App\Services\DataHealth\DataHealthService;

class FindLinkCandidatesTool implements AssistantTool
{
    public function __construct(
        private readonly DataHealthService $dataHealth,
    ) {
    }

    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'find_link_candidates',
                'description' => 'For one cave system, find other systems it may need merging with — matched by '
                    .'entrance proximity and by name similarity. Use this to verify a suspected bad link before '
                    .'proposing propose_system_merge or a cave_system_id change via propose_data_fix.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'cave_system_id' => [
                            'type' => 'integer',
                            'description' => 'The numeric ID of the cave system to find link/merge candidates for.',
                        ],
                        'max_distance_km' => [
                            'type' => 'number',
                            'description' => 'Maximum entrance-to-entrance distance to consider, in km (default 2).',
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
        $maxDistanceKm = isset($arguments['max_distance_km'])
            ? max(0.1, min((float) $arguments['max_distance_km'], 10.0))
            : 2.0;

        $system = CaveSystem::find($systemId);
        if (!$system) {
            return ['error' => "Cave system with ID {$systemId} not found."];
        }

        return $this->dataHealth->findLinkCandidates($system, $maxDistanceKm);
    }
}
