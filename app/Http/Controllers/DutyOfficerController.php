<?php

namespace App\Http\Controllers;

use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DutyOfficerController extends Controller
{
    /**
     * Get the current "On Call" duty officer.
     */
    public function current(Request $request): JsonResponse
    {
        // Check for an active On Call Shift
        $currentShift = OnCallShift::with('user')->covering(now())->first();
        $officer = $currentShift?->user;

        // Calculate next gap
        // 1. Get all future shifts starting from now (or the start of the current shift)
        $searchStart = $currentShift ? $currentShift->start_at : now();

        $futureShifts = OnCallShift::where('end_at', '>', now())
            ->orderBy('start_at')
            ->get();

        $coveredUntil = now();

        if ($currentShift) {
            $coveredUntil = $currentShift->end_at;
        }

        // Check continuity
        foreach ($futureShifts as $shift) {
            // If the shift starts after the current coverage ends (with a small buffer for seconds alignment), we found a gap
            // Using 1 minute buffer to be safe against second-precision issues
            if ($shift->start_at->subMinutes(1)->gt($coveredUntil)) {
                break;
            }

            // Extend coverage if this shift goes later
            if ($shift->end_at->gt($coveredUntil)) {
                $coveredUntil = $shift->end_at;
            }
        }

        $nextGapStart = $coveredUntil;

        if (!$officer) {
            return response()->json([
                'data' => [
                    'name' => null,
                    'photo' => null,
                    'next_gap_start' => $nextGapStart,
                    'is_covered' => false,
                ],
            ], 200);
        }

        return response()->json([
            'data' => [
                'name' => $officer->name,
                'photo' => $officer->photo,
                'next_gap_start' => $nextGapStart,
                'is_covered' => true,
            ],
        ]);
    }

    /**
     * Get the public rota: upcoming shifts and duty officer profiles.
     * Accessible to any authenticated user.
     */
    public function rotaPublic(Request $request): JsonResponse
    {
        $officers = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['duty_officer', 'platform_admin']);
        })->with(['clubs' => function ($q) {
            $q->wherePivot('status', 'approved')->select('clubs.id', 'clubs.name', 'clubs.slug');
        }])->get(['id', 'name', 'photo', 'bio']);

        $shifts = OnCallShift::with('user:id,name,photo')
            ->where('end_at', '>', now())
            ->where('start_at', '<', now()->addWeeks(1))
            ->orderBy('start_at')
            ->get(['id', 'user_id', 'start_at', 'end_at']);

        return response()->json([
            'data' => [
                'officers' => $officers,
                'shifts' => $shifts,
            ],
        ]);
    }

    /**
     * Get a list of potential duty officers (users with duty_officer or platform_admin role).
     */
    public function index(Request $request): JsonResponse
    {
        $officers = User::whereHas('roles', function ($q) {
            $q->whereIn('slug', ['duty_officer', 'platform_admin']);
        })->get(['id', 'name']);

        return response()->json(['data' => $officers]);
    }
}
