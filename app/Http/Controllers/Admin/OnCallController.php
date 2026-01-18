<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OnCallShift;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class OnCallController extends Controller
{
    /**
     * Get shifts for a date range (e.g. month view)
     */
    public function index(Request $request)
    {
        $start = Carbon::parse($request->query('start', now()->startOfMonth()));
        $end = Carbon::parse($request->query('end', now()->endOfMonth()));

        $shifts = OnCallShift::with('user')
            ->where('start_at', '<=', $end)
            ->where('end_at', '>=', $start)
            ->get();

        return response()->json([
            'data' => $shifts
        ]);
    }

    /**
     * Add a shift
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after:start_at',
        ]);

        // Simple overlap check
        $exists = OnCallShift::where('user_id', $data['user_id'])
            ->where(function ($query) use ($data) {
                $query->whereBetween('start_at', [$data['start_at'], $data['end_at']])
                      ->orWhereBetween('end_at', [$data['start_at'], $data['end_at']]);
            })->exists();

        if ($exists) {
            return response()->json(['message' => 'User already has a shift in this range'], 409);
        }

        $shift = OnCallShift::create($data);

        return response()->json([
            'message' => 'Shift created',
            'data' => $shift->load('user')
        ]);
    }

    /**
     * Remove a shift
     */
    public function destroy($id)
    {
        $shift = OnCallShift::findOrFail($id);
        $shift->delete();

        return response()->json(['message' => 'Shift removed']);
    }
}
