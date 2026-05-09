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
                'description' => 'Find caving huts and accommodation near a cave system, sorted by distance. Returns up to 8 closest huts with coordinates suitable for plotting alongside the cave on a geojson map. Use this when the user asks about accommodation, where to stay, or is planning a weekend away.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'cave_system_id' => [
                            'type'        => 'integer',
                            'description' => 'The numeric ID of the cave system to find huts near.',
                        ],
                        'max_distance_km' => [
                            'type'        => 'number',
                            'description' => 'Optional maximum great-circle distance in km. Defaults to 50km.',
                        ],
                    ],
                    'required' => ['cave_system_id'],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $systemId    = (int) ($arguments['cave_system_id'] ?? 0);
        $maxDistance = isset($arguments['max_distance_km'])
            ? (float) $arguments['max_distance_km']
            : 50.0;

        $system = CaveSystem::with('caves')->find($systemId);
        if (!$system) {
            return ['error' => "Cave system with ID {$systemId} not found."];
        }

        // Find the first entrance with coordinates to use as the reference point
        $referenceCave = $system->caves->first(
            fn ($c) => $c->location_lat && $c->location_lng
        );

        $huts = Hut::with('club')
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->get()
            ->map(function ($hut) use ($referenceCave) {
                $distanceKm = null;
                if ($referenceCave) {
                    $distanceKm = round(
                        $this->haversine(
                            (float) $referenceCave->location_lat,
                            (float) $referenceCave->location_lng,
                            (float) $hut->location_lat,
                            (float) $hut->location_lng
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
                    'hut_url'      => "/huts/{$hut->id}",
                    'latitude'     => (float) $hut->location_lat,
                    'longitude'    => (float) $hut->location_lng,
                ];
            });

        if ($referenceCave) {
            // Filter to within max distance, then sort + cap at 8 results
            $huts = $huts
                ->filter(fn ($h) => $h['distance_km'] !== null && $h['distance_km'] <= $maxDistance)
                ->sortBy('distance_km')
                ->take(8)
                ->values();
        } else {
            $huts = $huts->take(8)->values();
        }

        return [
            'cave_system'        => $system->name,
            'cave_system_slug'   => $system->slug,
            'reference_cave'     => $referenceCave?->name,
            'reference_cave_url' => $referenceCave ? "/caves/{$referenceCave->slug}" : null,
            'reference_lat'      => $referenceCave?->location_lat,
            'reference_lng'      => $referenceCave?->location_lng,
            'max_distance_km'    => $maxDistance,
            'count'              => $huts->count(),
            'huts'               => $huts,
            'note'               => $referenceCave
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
