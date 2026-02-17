<?php

namespace App\Http\Controllers;

use App\Http\Resources\TripResource;
use App\Http\Resources\UserResource;
use App\Models\Club;
use App\Models\Trip;
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
        $members = $club->users()->wherePivot('status', 'approved')->orderBy('name')->get();

        // Used by ClubEditModal for club admins who can't access the full admin endpoint
        return UserResource::collection($members->map(function ($user) {
            $user->is_club_admin = (bool) $user->pivot->is_admin;

            return $user;
        }));
    }

    /**
     * Get activity heatmap data for a club (hours underground per day in the last year).
     */
    public function activityHeatmap(Club $club): JsonResponse
    {
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
