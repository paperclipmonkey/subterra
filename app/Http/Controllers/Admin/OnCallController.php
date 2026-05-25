<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Callout;
use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class OnCallController extends Controller
{
    /**
     * Get shifts for a date range (e.g. month view).
     */
    public function index(Request $request)
    {
        $start = Carbon::parse($request->query('start', now()->startOfMonth()));
        $end = Carbon::parse($request->query('end', now()->endOfMonth()));

        $shifts = OnCallShift::with('user')
            ->where('start_at', '<=', $end)
            ->where('end_at', '>=', $start)
            ->orderBy('start_at')
            ->get();

        return response()->json([
            'data' => $shifts,
        ]);
    }

    /**
     * Add a shift.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if ($user && !$user->hasRole(['duty_officer', 'platform_admin'])) {
                        $fail('The selected user must have the Duty Officer role.');
                    }
                },
            ],
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'notify_do' => 'boolean',
        ]);

        // Normalise to UTC so timezone offsets (e.g. BST +01:00) are stored correctly
        $data['start_at'] = Carbon::parse($data['start_at'])->utc();
        $data['end_at'] = Carbon::parse($data['end_at'])->utc();

        // Strict global overlap check
        // Finds any shift where:
        // Existing Start < New End  AND  Existing End > New Start
        $exists = OnCallShift::where('start_at', '<', $data['end_at'])
            ->where('end_at', '>', $data['start_at'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This shift overlaps with an existing shift.'], 409);
        }

        $shift = OnCallShift::create($data);

        return response()->json([
            'message' => 'Shift created',
            'data' => $shift->load('user'),
        ]);
    }

    /**
     * Update a shift.
     */
    public function update(Request $request, $id)
    {
        $shift = OnCallShift::findOrFail($id);

        $data = $request->validate([
            'user_id' => [
                'required',
                'exists:users,id',
                function ($attribute, $value, $fail) {
                    $user = User::find($value);
                    if ($user && !$user->hasRole(['duty_officer', 'platform_admin'])) {
                        $fail('The selected user must have the Duty Officer role.');
                    }
                },
            ],
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
            'notify_do' => 'boolean',
        ]);

        // Normalise to UTC so timezone offsets (e.g. BST +01:00) are stored correctly
        $data['start_at'] = Carbon::parse($data['start_at'])->utc();
        $data['end_at'] = Carbon::parse($data['end_at'])->utc();

        // Check for overlaps with ANY other shift (global check)
        $exists = OnCallShift::where('id', '!=', $id)
            ->where('start_at', '<', $data['end_at'])
            ->where('end_at', '>', $data['start_at'])
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'This shift overlaps with an existing shift.'], 409);
        }

        // Check for orphaned callouts if the shift is shortened or person changed
        $uncoveredCallouts = $this->getUncoveredCalloutsAfterModification($shift, $data['start_at'], $data['end_at'], $data['user_id']);

        if ($uncoveredCallouts->isNotEmpty()) {
            return response()->json([
                'message' => 'Cannot modify shift: would leave '.$uncoveredCallouts->count().' callout(s) unmonitored.',
                'affected_callouts' => $uncoveredCallouts,
            ], 422);
        }

        $shift->update($data);

        return response()->json([
            'message' => 'Shift updated',
            'data' => $shift->load('user'),
        ]);
    }

    /**
     * Remove a shift.
     */
    public function destroy($id)
    {
        $shift = OnCallShift::findOrFail($id);

        // Check if deleting this shift leaves callouts uncovered
        $uncoveredCallouts = $this->getUncoveredCalloutsAfterModification($shift, null, null, null, true);

        if ($uncoveredCallouts->isNotEmpty()) {
            return response()->json([
                'message' => 'Cannot remove shift: would leave '.$uncoveredCallouts->count().' callout(s) unmonitored.',
                'affected_callouts' => $uncoveredCallouts,
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'message' => 'Shift removed',
        ]);
    }

    /**
     * Helper to find callouts that would become unmonitored.
     */
    private function getUncoveredCalloutsAfterModification($shift, $newStart = null, $newEnd = null, $newUser = null, $isDelete = false)
    {
        // 1. Find callouts that were covered by the original shift
        $callouts = Callout::whereIn('status', ['active', 'triggered'])
            ->where('callout_time', '>=', $shift->start_at)
            ->where('callout_time', '<=', $shift->end_at)
            ->get();

        if ($callouts->isEmpty()) {
            return collect();
        }

        // 2. Filter to those NOT covered by the new parameters OR not covered by any OTHER shift
        return $callouts->filter(function ($callout) use ($shift, $newStart, $newEnd, $isDelete) {
            // If it's still covered by the new shift bounds (and not a delete), it's fine
            if (!$isDelete && $newStart && $newEnd) {
                $calloutTime = $callout->callout_time;
                if ($calloutTime->between($newStart, $newEnd)) {
                    return false;
                }
            }

            // Otherwise, check if ANY OTHER shift covers it
            $hasOtherCoverage = OnCallShift::where('id', '!=', $shift->id)
                ->where('start_at', '<=', $callout->callout_time)
                ->where('end_at', '>=', $callout->callout_time)
                ->exists();

            return !$hasOtherCoverage;
        })->map(function ($callout) {
            return [
                'id' => $callout->id,
                'callout_time' => $callout->callout_time,
                'cave_name' => $callout->cave_name,
                'user_name' => $callout->user?->name ?? 'Unknown User',
            ];
        });
    }
}
