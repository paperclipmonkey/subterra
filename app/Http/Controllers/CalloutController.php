<?php

namespace App\Http\Controllers;

use App\Services\CalloutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Exception;

class CalloutController extends Controller
{
    protected CalloutService $calloutService;

    public function __construct(CalloutService $calloutService)
    {
        $this->calloutService = $calloutService;
    }

    /**
     * Create a new callout.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'cave_id' => 'nullable|exists:caves,id',
            'exit_cave_id' => 'nullable|exists:caves,id',
            // 'expected_exit_time' => 'required|date|after:now', // Removed
            'callout_time' => 'required|date|after:now', // Removed dependency on expected_exit_time
            'description' => 'nullable|string',
            'trip_plan' => 'required|string',
            'car_details' => 'nullable|string',
            'team_details' => 'nullable|string',
            // 'emergency_contact_name' => 'required|string', // Removed
            // 'emergency_contact_phone' => 'required|string', // Removed
            'trip_id' => 'nullable|exists:trips,id',
            'participants' => 'nullable|array',
            'participants.*.user_id' => 'nullable|exists:users,id',
            'participants.*.name' => 'required|string',
            'participants.*.phone' => 'nullable|string',
            'participants.*.email' => 'nullable|email',
        ]);

        try {
            $callout = $this->calloutService->create($request->user(), $data);
            return response()->json([
                'message' => 'Callout activated successfully.',
                'callout' => $callout
            ], 201);
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cancel a callout.
     */
    public function cancel($id)
    {
        $callout = Auth::user()->callouts()->findOrFail($id);
        
        $this->calloutService->cancel($callout);

        return response()->json(['message' => 'Callout cancelled successfully.']);
    }

    public function show($id)
    {
        $callout = Auth::user()->callouts()->with('participants')->findOrFail($id);
        return response()->json(['data' => $callout]);
    }
}
