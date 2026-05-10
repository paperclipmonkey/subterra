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
            'type' => 'function',
            'function' => [
                'name' => 'get_user_experience',
                'description' => "Get the current user's caving experience: recent trips, medals, club memberships, and the FULL list of every cave system they've ever visited (all_visited_systems — name + slug). Use all_visited_systems to filter out already-done caves when recommending — recent_trips only shows the last 10. Always call this first before making personalised recommendations.",
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                    'required' => [],
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
                    'trip_name' => $trip->trip_name,
                    'cave_system' => $trip->cave_system,
                    'entrance' => $trip->entrance,
                    'date' => $trip->start_time ? date('Y-m-d', strtotime($trip->start_time)) : null,
                    'duration_minutes' => $duration,
                ];
            });

        $totalTrips = DB::table('trip_user')->where('user_id', $user->id)->count();

        // Full all-time list of systems the user has visited — names + slugs.
        // Without this the model only sees the last 10 trips and would happily
        // recommend caves the user did 4 years ago. Capped at 200 to keep the
        // tool result a reasonable size; users with longer histories will still
        // get the most recent 200.
        $visitedSystems = DB::table('trip_user')
            ->join('trips', 'trips.id', '=', 'trip_user.trip_id')
            ->join('cave_systems', 'cave_systems.id', '=', 'trips.cave_system_id')
            ->where('trip_user.user_id', $user->id)
            ->whereNotNull('trips.cave_system_id')
            ->select([
                'cave_systems.name as name',
                'cave_systems.slug as slug',
                DB::raw('MAX(trips.start_time) as last_visit'),
                DB::raw('COUNT(*) as visit_count'),
            ])
            ->groupBy('cave_systems.id', 'cave_systems.name', 'cave_systems.slug')
            ->orderByDesc('last_visit')
            ->limit(200)
            ->get()
            ->map(fn ($s) => [
                'name' => $s->name,
                'slug' => $s->slug,
                'visit_count' => (int) $s->visit_count,
                'last_visit' => $s->last_visit ? date('Y-m-d', strtotime($s->last_visit)) : null,
            ]);

        $approvedClubs = $user->clubs
            ->filter(fn ($c) => $c->pivot->status === 'approved')
            ->map(fn ($c) => $c->name)
            ->values();

        $medals = $user->medals->map(fn ($m) => [
            'name' => $m->name,
            'description' => $m->description,
        ])->values();

        $uniqueSystemsCount = $visitedSystems->count();

        // Pre-built sentence the model can quote verbatim. We've seen small
        // models conflate `total_trips` with `unique_systems_visited` (e.g. a
        // user with 47 unique systems across 68 trips gets reported as "68
        // systems"). Giving them a ready-made phrase removes the math.
        $summary = "{$totalTrips} trips logged across {$uniqueSystemsCount} unique cave systems.";

        return [
            'total_trips' => $totalTrips,
            'unique_systems_visited' => $uniqueSystemsCount,
            'experience_summary' => $summary,
            'clubs' => $approvedClubs,
            'medals' => $medals,
            'recent_trips' => $recentTrips->values(),
            'all_visited_systems' => $visitedSystems->values(),
            'visited_systems_note' => 'all_visited_systems lists every cave system the user has '
                .'EVER visited (most recent first, capped at 200). Check this list before recommending '
                .'a cave — anything in it is already done. Pair with search_caves(not_visited=true) for '
                .'truly fresh suggestions. When mentioning the user\'s scale, quote experience_summary '
                .'verbatim — do not reword it. total_trips and unique_systems_visited are different '
                .'numbers (a user can visit the same system multiple times).',
        ];
    }
}
