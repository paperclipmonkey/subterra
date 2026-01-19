<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\OnCallShift;
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
        $shift = OnCallShift::with('user')->covering(now())->first();
        $officer = $shift?->user;

        if (!$officer) {
            return response()->json([
                'message' => 'No duty officer currently on shift.',
            ], 404);
        }

        return response()->json([
            'data' => [
                'name' => $officer->name,
                'photo' => $officer->photo, // Assuming 'photo' is a URL or path
            ]
        ]);
    }
}
