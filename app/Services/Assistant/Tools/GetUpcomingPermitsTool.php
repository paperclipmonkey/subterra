<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\Cave;
use App\Models\User;
use App\Services\Assistant\AssistantTool;

class GetUpcomingPermitsTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'get_upcoming_permits',
                'description' => 'Check whether a cave requires a permit and how many bookings are already made on specific dates. Use this when the user is planning a trip on particular dates and the cave has access restrictions. Pass either cave_id (a specific entrance) or cave_system_id (any entrance of the system with a permit will be checked).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'cave_id' => [
                            'type' => 'integer',
                            'description' => 'The numeric ID of the cave entrance (from get_cave_details entrances array).',
                        ],
                        'cave_system_id' => [
                            'type' => 'integer',
                            'description' => 'Alternatively, the cave system ID — the first entrance with an active permit will be used.',
                        ],
                        'date_from' => [
                            'type' => 'string',
                            'description' => 'Start of the date range to check, format Y-m-d.',
                        ],
                        'date_to' => [
                            'type' => 'string',
                            'description' => 'End of the date range to check, format Y-m-d. Defaults to 30 days after date_from.',
                        ],
                    ],
                    'required' => ['date_from'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $caveId = (int) ($arguments['cave_id'] ?? 0);
        $caveSystemId = (int) ($arguments['cave_system_id'] ?? 0);

        // Try the specific cave first; fall back to scanning the system's
        // entrances for one with a permit. Models routinely conflate cave_id
        // and cave_system_id (the IDs are similar magnitude) so accepting
        // both makes the tool forgiving.
        $cave = null;
        $permit = null;

        if ($caveId > 0) {
            $cave = Cave::query()->where('id', $caveId)->first();
            $permit = $cave?->permit()->where('is_active', true)->first();
        }

        if (!$permit && $caveSystemId > 0) {
            $cave = Cave::query()
                ->where('cave_system_id', $caveSystemId)
                ->whereHas('permit', fn ($q) => $q->where('is_active', true))
                ->first()
                ?? Cave::query()->where('cave_system_id', $caveSystemId)->first();
            $permit = $cave?->permit()->where('is_active', true)->first();
        }

        if (!$cave) {
            $idLabel = $caveId > 0 ? "cave_id={$caveId}" : ($caveSystemId > 0 ? "cave_system_id={$caveSystemId}" : '(none)');

            return ['error' => "No cave found for {$idLabel}. Pass cave_id (an entrance) or cave_system_id."];
        }

        if (!$permit) {
            return [
                'has_permit' => false,
                'cave_name' => $cave->name,
                'message' => 'No active permit scheme is required for this cave.',
            ];
        }

        $dateFrom = $arguments['date_from'];
        $dateTo = $arguments['date_to'] ?? date('Y-m-d', strtotime($dateFrom.' +30 days'));

        $bookings = $permit->bookings()
            ->where('status', 'approved')
            ->whereDate('date', '>=', $dateFrom)
            ->whereDate('date', '<=', $dateTo)
            ->select('date')
            ->selectRaw('count(*) as booking_count')
            ->groupBy('date')
            ->get();

        $bookingsByDate = $bookings->mapWithKeys(fn ($b) => [
            $b->date->toDateString() => $b->booking_count,
        ]);

        return [
            'has_permit' => true,
            'cave_name' => $cave->name,
            'permit_name' => $permit->name,
            'has_max_groups' => (bool) ($permit->has_max_groups_per_day ?? false),
            'max_groups_per_day' => $permit->max_groups_per_day ?? null,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'bookings_by_date' => $bookingsByDate,
            'note' => 'A booking_count equal to max_groups_per_day means the date is fully booked.',
        ];
    }
}
