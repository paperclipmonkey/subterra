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
        if (!$request->user()->is_approved) {
            abort(403, 'You must be an approved member to open callouts.');
        }

        $data = $request->validate([
            'cave_id' => 'nullable|exists:caves,id',
            'exit_cave_id' => 'nullable|exists:caves,id',
            'callout_time' => 'required|date|after:now',
            'description' => 'nullable|string',
            'trip_plan' => 'required|string',
            'car_details' => 'nullable|string', // kept for backward compatibility
            'car_registration' => 'required|string',
            'car_parking' => 'required|string',
            'location_data' => 'nullable|array',
            'team_details' => 'nullable|string',
            'trip_id' => 'nullable|exists:trips,id',
            'participants' => 'required|array|min:1',
            'participants.*.user_id' => 'nullable|exists:users,id',
            'participants.*.name' => 'required|string',
            'participants.*.phone' => 'nullable|string',
            'participants.*.email' => 'nullable|email',
        ]);

        $data['request_data'] = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];

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
        
        $trip = $this->calloutService->cancel($callout);

        return response()->json([
            'message' => 'Callout cancelled successfully.',
            'trip_id' => $trip ? $trip->short_id : null
        ]);
    }

    public function show($id)
    {
        $callout = Auth::user()->callouts()->with('participants')->findOrFail($id);
        return response()->json(['data' => $callout]);
    }

    /**
     * Get Open callouts for the public map.
     */
    public function active()
    {
        $callouts = \App\Models\Callout::query()
            ->whereIn('status', ['active', 'triggered'])
            ->with(['cave:id,name,location_lat,location_lng,location_name', 'participants'])
            ->get()
            ->map(function ($callout) {
                // Resolve location: Cave takes precedence, then manual location data
                $lat = null;
                $lng = null;
                $caveName = 'Unknown Location';

                if ($callout->cave) {
                    $lat = $callout->cave->location_lat;
                    $lng = $callout->cave->location_lng;
                    $caveName = $callout->cave->name;
                } elseif (!empty($callout->location_data) && isset($callout->location_data['latitude'], $callout->location_data['longitude'])) {
                    $lat = $callout->location_data['latitude'];
                    $lng = $callout->location_data['longitude'];
                }

                return [
                    'id' => $callout->id,
                    'cave_name' => $caveName,
                    'lat' => $lat,
                    'lng' => $lng,
                ];
            });

        return response()->json(['data' => $callouts]);
    }
}
