<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Trip;
use App\Http\Resources\TripResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ClubDataController extends Controller
{
    /**
     * Get the 10 most recent trips for a club.
     */
    public function recentTrips(Club $club)
    {
        $trips = Trip::whereHas('participants', function ($query) use ($club) {
                $query->whereIn('user_id', $club->users()->wherePivot('status', 'approved')->pluck('users.id'));
            })
            ->where('start_time', '>=', Carbon::now()->subYear())
            ->orderBy('start_time', 'desc')
            ->limit(10)
            ->get();

        return TripResource::collection($trips);
    }

    /**
     * Get the members of a club.
     */
    public function members(Club $club)
    {
        $members = $club->users()->wherePivot('status', 'approved')->get();
        return UserResource::collection($members);
    }

    /**
     * Get activity heatmap data for a club (trips per day in the last year).
     */
    public function activityHeatmap(Club $club)
    {
        $oneYearAgo = Carbon::now()->subYear();

        $activity = Trip::select(DB::raw('DATE(start_time) as date'), DB::raw('count(*) as count'))
            ->whereHas('participants', function ($query) use ($club) {
                $query->whereIn('user_id', $club->users()->wherePivot('status', 'approved')->pluck('users.id'));
            })
            ->where('start_time', '>=', $oneYearAgo)
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($item) {
                // Format for vue3-calendar-heatmap: { 'YYYY-MM-DD': count }
                return ['date' => $item->date, 'count' => $item->count];
            });

        return response()->json($activity);
    }
}
