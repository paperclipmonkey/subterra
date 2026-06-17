<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Http\Resources\UserResource;
use App\Models\Club;
use App\Models\Trip;
use App\Models\TripMedia;
use App\Support\MediaUrl;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\DB;

class ClubDataController extends Controller
{
    /**
     * Get the 10 most recent trips for a club.
     */
    public function recentTrips(Club $club): ResourceCollection
    {
        // The Direct Individual Member catch-all club has no club trips.
        if ($club->isIndividualMembership()) {
            return TripResource::collection(collect());
        }

        $trips = Trip::whereHas('participants', function ($query) use ($club) {
            $query->whereIn('user_id', $club->users()->wherePivot('status', 'approved')->pluck('users.id'));
        })
            ->where('start_time', '>=', Carbon::now()->subYear())
            ->with(['system', 'entrance.heroImage', 'entrance.entranceImage', 'participants', 'media'])
            ->orderBy('start_time', 'desc')
            ->limit(10)
            ->get();

        return TripResource::collection($trips);
    }

    /**
     * Get the members of a club.
     */
    public function members(Club $club): ResourceCollection
    {
        // Members of the Direct Individual Member catch-all club shouldn't be
        // able to browse each other, so its roster is never exposed.
        if ($club->isIndividualMembership()) {
            return UserResource::collection(collect());
        }

        $members = $club->users()->wherePivot('status', 'approved')->orderBy('name')->get();

        // Used by ClubEditModal for club admins who can't access the full admin endpoint
        return UserResource::collection($members->map(function ($user) {
            $user->is_club_admin = (bool) $user->pivot->is_admin;

            return $user;
        }));
    }

    /**
     * Headline stats, cross-club connections and a recent-photo feed for a club.
     *
     * Powers the "by the numbers", "caved alongside" and photo-wall sections of
     * the club page. Everything is scoped to trips that at least one *approved*
     * member took part in, over the trailing 12 months (matching recentTrips and
     * the heatmap), except the "new caves this year" figure which looks at the
     * club's whole history to decide whether a cave is genuinely new.
     */
    public function summary(Club $club): JsonResponse
    {
        // The Direct Individual Member catch-all club has no club stats.
        if ($club->isIndividualMembership()) {
            return response()->json([
                'stats' => $this->emptyStats(),
                'allied_clubs' => [],
                'photos' => [],
                'photo_count' => 0,
            ]);
        }

        $approvedIds = $club->approvedUsers()->pluck('users.id');

        // No members yet → nothing to aggregate. Bail early with empty shells so
        // the frontend can render its empty states without special-casing nulls.
        if ($approvedIds->isEmpty()) {
            return response()->json([
                'stats' => $this->emptyStats(),
                'allied_clubs' => [],
                'photos' => [],
                'photo_count' => 0,
            ]);
        }

        $oneYearAgo = Carbon::now()->subYear();
        $startOfMonth = Carbon::now()->startOfMonth();
        $startOfYear = Carbon::now()->startOfYear();

        // Base query: trips with at least one approved member as a participant.
        $clubTrips = fn () => Trip::whereHas('participants', function ($query) use ($approvedIds) {
            $query->whereIn('user_id', $approvedIds);
        });

        $recentTrips = $clubTrips()
            ->where('start_time', '>=', $oneYearAgo)
            ->get(['id', 'start_time', 'end_time', 'entrance_cave_id']);

        $hoursUnderground = 0.0;
        $tripsThisMonth = 0;
        foreach ($recentTrips as $trip) {
            $hoursUnderground += $trip->duration / 60; // duration is whole minutes
            if ($trip->start_time && $trip->start_time->gte($startOfMonth)) {
                ++$tripsThisMonth;
            }
        }

        $cavesVisited = $recentTrips->pluck('entrance_cave_id')->filter()->unique()->count();

        // Caves whose first-ever club visit falls in the current calendar year.
        // Trips with no start_time are skipped — a null MIN() would otherwise
        // parse to "now" and be miscounted as a brand-new visit.
        $newCavesThisYear = $clubTrips()
            ->whereNotNull('entrance_cave_id')
            ->whereNotNull('start_time')
            ->selectRaw('entrance_cave_id, MIN(start_time) as first_visit')
            ->groupBy('entrance_cave_id')
            ->get()
            ->filter(fn ($row) => Carbon::parse($row->first_visit)->gte($startOfYear))
            ->count();

        // Most active member: most trips in the window, ties broken by name.
        $mostActiveRow = DB::table('trip_user')
            ->join('trips', 'trips.id', '=', 'trip_user.trip_id')
            ->join('users', 'users.id', '=', 'trip_user.user_id')
            ->whereIn('trip_user.user_id', $approvedIds)
            ->where('trips.start_time', '>=', $oneYearAgo)
            ->select('users.id', 'users.name', DB::raw('count(*) as trip_count'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('trip_count')
            ->orderBy('users.name')
            ->first();

        $tripIds = $recentTrips->pluck('id');

        // Other clubs whose members shared these trips, by distinct trip count.
        $alliedClubs = DB::table('club_user')
            ->join('trip_user', 'trip_user.user_id', '=', 'club_user.user_id')
            ->join('clubs', 'clubs.id', '=', 'club_user.club_id')
            ->whereIn('trip_user.trip_id', $tripIds)
            ->where('club_user.status', 'approved')
            ->where('club_user.club_id', '!=', $club->id)
            ->select('clubs.name', 'clubs.slug', DB::raw('count(distinct trip_user.trip_id) as trip_count'))
            ->groupBy('clubs.id', 'clubs.name', 'clubs.slug')
            ->orderByDesc('trip_count')
            ->orderBy('clubs.name')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'slug' => $row->slug,
                'trip_count' => (int) $row->trip_count,
            ]);

        $photoCount = TripMedia::whereIn('trip_id', $tripIds)->whereNotNull('filename')->count();

        $base = MediaUrl::base();
        $photos = TripMedia::whereIn('trip_id', $tripIds)
            ->whereNotNull('filename')
            ->with('trip:id,short_id')
            ->orderByDesc('taken_at')
            ->orderByDesc('id')
            ->limit(12)
            ->get()
            ->map(fn ($media) => [
                'id' => $media->id,
                'url' => MediaUrl::url($media->filename, $base),
                'srcset' => MediaUrl::srcset($media->filename, $base),
                'title' => $media->title,
                'trip_id' => $media->trip?->short_id,
            ]);

        return response()->json([
            'stats' => [
                'hours_underground' => (int) round($hoursUnderground),
                'trips_logged' => $recentTrips->count(),
                'trips_this_month' => $tripsThisMonth,
                'caves_visited' => $cavesVisited,
                'new_caves_this_year' => $newCavesThisYear,
                'most_active' => $mostActiveRow ? [
                    'id' => $mostActiveRow->id,
                    'name' => $mostActiveRow->name,
                    'trip_count' => (int) $mostActiveRow->trip_count,
                ] : null,
            ],
            'allied_clubs' => $alliedClubs,
            'photos' => $photos,
            'photo_count' => $photoCount,
        ]);
    }

    /**
     * Empty stats payload for clubs with no approved members.
     *
     * @return array<string, mixed>
     */
    private function emptyStats(): array
    {
        return [
            'hours_underground' => 0,
            'trips_logged' => 0,
            'trips_this_month' => 0,
            'caves_visited' => 0,
            'new_caves_this_year' => 0,
            'most_active' => null,
        ];
    }

    /**
     * Get activity heatmap data for a club (hours underground per day in the last year).
     */
    public function activityHeatmap(Club $club): JsonResponse
    {
        // The Direct Individual Member catch-all club has no club activity.
        if ($club->isIndividualMembership()) {
            return response()->json([]);
        }

        $oneYearAgo = Carbon::now()->subYear();
        $approvedMemberIdsList = $club->approvedUsers()->pluck('users.id');
        $approvedMemberIds = $approvedMemberIdsList->flip();

        $trips = Trip::with('participants')
            ->where('start_time', '>=', $oneYearAgo)
            ->whereHas('participants', function ($query) use ($approvedMemberIdsList) {
                $query->whereIn('users.id', $approvedMemberIdsList);
            })
            ->get();

        $dailyHours = [];

        foreach ($trips as $trip) {
            // Skip invalid dates or times
            if (!$trip->start_time || !$trip->end_time) {
                continue;
            }

            $date = $trip->start_time->toDateString();
            $durationHours = $trip->start_time->diffInMinutes($trip->end_time) / 60;

            $memberCount = 0;
            foreach ($trip->participants as $participant) {
                if ($approvedMemberIds->has($participant->id)) {
                    ++$memberCount;
                }
            }

            if ($memberCount > 0) {
                if (!isset($dailyHours[$date])) {
                    $dailyHours[$date] = 0;
                }
                $dailyHours[$date] += ($durationHours * $memberCount);
            }
        }

        // Transform into array of objects and sort by date
        $activity = collect($dailyHours)
            ->sortKeys()
            ->map(function ($count, $date) {
                return ['date' => $date, 'count' => round($count, 1)];
            })
            ->values();

        return response()->json($activity);
    }
}
