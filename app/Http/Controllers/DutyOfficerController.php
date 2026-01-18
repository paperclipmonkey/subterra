<?php

namespace App\Http\Controllers;

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
        // For now, we simulate an "On Call" rota by picking a random active admin.
        // In a real system, this might check a rota table or calendar.
        $officer = User::where('is_admin', true)
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();

        if (!$officer) {
            return response()->json([
                'data' => [
                    'name' => 'Subterra Team', // Fallback
                    'photo' => null,
                ]
            ]);
        }

        return response()->json([
            'data' => [
                'name' => $officer->name,
                'photo' => $officer->photo, // Assuming 'photo' is a URL or path
            ]
        ]);
    }
}
