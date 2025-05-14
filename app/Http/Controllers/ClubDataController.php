<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Club;
use App\Models\Trip;
use App\Http\Resources\TripResource;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ClubDataController extends Controller
{
    /**
     * Get the 10 most recent trips for a club.
     */
    public function recentTrips(Club $club): ResourceCollection
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
    public function members(Club $club): ResourceCollection
    {
        $members = $club->users()->wherePivot('status', 'approved')->get();
        return UserResource::collection($members);
    }

    /**
     * Get activity heatmap data for a club (trips per day in the last year).
     */
    public function activityHeatmap(Club $club): JsonResponse
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
