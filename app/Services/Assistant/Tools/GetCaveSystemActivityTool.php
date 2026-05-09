<?php

declare(strict_types=1);

namespace App\Services\Assistant\Tools;

use App\Models\Trip;
use App\Models\User;
use App\Services\Assistant\AssistantTool;
use Illuminate\Support\Facades\DB;

class GetCaveSystemActivityTool implements AssistantTool
{
    public static function definition(): array
    {
        return [
            'type'     => 'function',
            'function' => [
                'name'        => 'get_cave_system_activity',
                'description' => 'Get recent community trip activity for a cave system: trip counts, the most recent trip date, the most popular entrance, average duration, and up to 5 recent trip reports with descriptions. Use this when asked about recent trips, conditions, or community activity for a specific system.',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => [
                        'cave_system_id' => [
                            'type'        => 'integer',
                            'description' => 'The numeric ID of the cave system.',
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

        $cutoff = now()->subDays(90)->toDateString();

        // Count trips in the last 90 days and get the most recent trip date
        $stats = DB::table('trips')
            ->where('cave_system_id', $systemId)
            ->whereDate('start_time', '>=', $cutoff)
            ->selectRaw('COUNT(*) as trip_count_90d')
            ->first();

        // Duration expression differs between PostgreSQL (production) and SQLite (tests)
        $isPgsql = DB::getDriverName() === 'pgsql';
        $durationExpr = $isPgsql
            ? 'AVG(CASE WHEN start_time IS NOT NULL AND end_time IS NOT NULL THEN EXTRACT(EPOCH FROM (end_time - start_time)) / 60 ELSE NULL END) as avg_duration_minutes'
            : 'AVG(CASE WHEN start_time IS NOT NULL AND end_time IS NOT NULL THEN (JULIANDAY(end_time) - JULIANDAY(start_time)) * 1440 ELSE NULL END) as avg_duration_minutes';

        // Overall stats (all time)
        $allTime = DB::table('trips')
            ->where('cave_system_id', $systemId)
            ->selectRaw('COUNT(*) as total_trips')
            ->selectRaw('MAX(start_time) as most_recent_trip')
            ->selectRaw($durationExpr)
            ->first();

        // Most popular entrance (cave with most trips as entrance)
        $popularEntrance = DB::table('trips')
            ->join('caves', 'caves.id', '=', 'trips.entrance_cave_id')
            ->where('trips.cave_system_id', $systemId)
            ->whereNotNull('trips.entrance_cave_id')
            ->selectRaw('caves.name, COUNT(*) as count')
            ->groupBy('caves.id', 'caves.name')
            ->orderByDesc('count')
            ->first();

        // Recent trip reports (public or club-visible, with descriptions)
        $recentReports = Trip::where('cave_system_id', $systemId)
            ->whereIn('visibility', ['public', 'club'])
            ->whereNotNull('description')
            ->where('description', '!=', '')
            ->orderByDesc('start_time')
            ->limit(5)
            ->get(['short_id', 'name', 'description', 'start_time'])
            ->map(fn ($t) => [
                'short_id'    => $t->short_id,
                'title'       => $t->name ?: 'Trip report',
                'date'        => $t->start_time ? $t->start_time->format('Y-m-d') : null,
                'description' => mb_substr($t->description, 0, 400),
                'url'         => "/trips/{$t->short_id}",
            ])
            ->values()
            ->all();

        $lastTripDate = null;
        if ($allTime && $allTime->most_recent_trip) {
            $lastTripDate = date('Y-m-d', strtotime($allTime->most_recent_trip));
        }

        $avgDuration = null;
        if ($allTime && $allTime->avg_duration_minutes !== null) {
            $avgDuration = (int) round((float) $allTime->avg_duration_minutes);
        }

        return [
            'cave_system_id'         => $systemId,
            'trips_last_90_days'     => (int) ($stats->trip_count_90d ?? 0),
            'total_trips_logged'     => (int) ($allTime->total_trips ?? 0),
            'last_trip_date'         => $lastTripDate,
            'most_popular_entrance'  => $popularEntrance ? $popularEntrance->name : null,
            'avg_trip_duration_mins' => $avgDuration,
            'recent_reports'         => $recentReports,
        ];
    }
}
