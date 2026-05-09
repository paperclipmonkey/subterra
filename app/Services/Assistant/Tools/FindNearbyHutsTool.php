<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\CaveSystem;
use App\Models\Hut;
use App\Models\User;
use App\Services\Assistant\AssistantTool;

class FindNearbyHutsTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => 'find_nearby_huts',
                'description' => 'Find caving huts and accommodation near a cave system, sorted by distance. Use this when the user is planning a weekend away and needs to find somewhere to stay.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'cave_system_id' => [
                            'type'        => 'integer',
                            'description' => 'The numeric ID of the cave system to find huts near.',
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

        $system = CaveSystem::with('caves')->find($systemId);
        if (!$system) {
            return ['error' => "Cave system with ID {$systemId} not found."];
        }

        // Find the first entrance with coordinates to use as the reference point
        $referenceCave = $system->caves->first(
            fn ($c) => $c->location_lat && $c->location_lng
        );

        $huts = Hut::with('club')->get()->map(function ($hut) use ($referenceCave) {
            $distanceKm = null;
            if ($referenceCave && $hut->location_lat && $hut->location_lng) {
                $distanceKm = round(
                    $this->haversine(
                        $referenceCave->location_lat,
                        $referenceCave->location_lng,
                        $hut->location_lat,
                        $hut->location_lng
                    ),
                    1
                );
            }

            return [
                'id'           => $hut->id,
                'name'         => $hut->name,
                'club'         => $hut->club?->name,
                'distance_km'  => $distanceKm,
                'amenities'    => $hut->amenities ?? [],
                'booking_info' => $hut->booking_info,
                'external_url' => $hut->external_url,
            ];
        });

        if ($referenceCave) {
            $huts = $huts->sortBy('distance_km')->values();
        }

        return [
            'cave_system'    => $system->name,
            'reference_cave' => $referenceCave?->name,
            'huts'           => $huts->take(10)->values(),
            'note'           => $referenceCave
                ? null
                : 'No entrance coordinates found for this system — huts are listed without distance ordering.',
        ];
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
