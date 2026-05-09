<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\User;
use App\Services\Assistant\AssistantTool;
use Illuminate\Support\Facades\DB;

class GetUserExperienceTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => 'get_user_experience',
                'description' => 'Get the current user\'s caving experience: recent trips, medals awarded, club memberships, and a count of unique cave systems visited. Always call this first before making recommendations so you can match suggestions to their background.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => new \stdClass(),
                    'required'   => [],
                ],
            ],
        ];
    }

    public function handle(array $arguments, User $user): array
    {
        $user->loadMissing(['clubs', 'medals', 'roles']);

        $recentTrips = DB::table('trip_user')
            ->join('trips', 'trips.id', '=', 'trip_user.trip_id')
            ->join('cave_systems', 'cave_systems.id', '=', 'trips.cave_system_id')
            ->leftJoin('caves as entrance_caves', 'entrance_caves.id', '=', 'trips.entrance_cave_id')
            ->where('trip_user.user_id', $user->id)
            ->whereNotNull('trips.start_time')
            ->orderByDesc('trips.start_time')
            ->limit(10)
            ->select([
                'trips.name as trip_name',
                'cave_systems.name as cave_system',
                'entrance_caves.name as entrance',
                'trips.start_time',
                'trips.end_time',
            ])
            ->get()
            ->map(function ($trip) {
                $duration = null;
                if ($trip->start_time && $trip->end_time) {
                    $duration = (int) round(
                        (strtotime($trip->end_time) - strtotime($trip->start_time)) / 60
                    );
                }

                return [
                    'trip_name'        => $trip->trip_name,
                    'cave_system'      => $trip->cave_system,
                    'entrance'         => $trip->entrance,
                    'date'             => $trip->start_time ? date('Y-m-d', strtotime($trip->start_time)) : null,
                    'duration_minutes' => $duration,
                ];
            });

        $totalTrips = DB::table('trip_user')->where('user_id', $user->id)->count();

        $uniqueSystems = DB::table('trip_user')
            ->join('trips', 'trips.id', '=', 'trip_user.trip_id')
            ->where('trip_user.user_id', $user->id)
            ->whereNotNull('trips.cave_system_id')
            ->distinct()
            ->count('trips.cave_system_id');

        $approvedClubs = $user->clubs
            ->filter(fn ($c) => $c->pivot->status === 'approved')
            ->map(fn ($c) => $c->name)
            ->values();

        $medals = $user->medals->map(fn ($m) => [
            'name'        => $m->name,
            'description' => $m->description,
        ])->values();

        return [
            'total_trips'              => $totalTrips,
            'unique_systems_visited'   => $uniqueSystems,
            'clubs'                    => $approvedClubs,
            'medals'                   => $medals,
            'recent_trips'             => $recentTrips->values(),
        ];
    }
}
