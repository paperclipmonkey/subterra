<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\MedalProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MedalController extends Controller
{
    /**
     * All medals with the current user's earned state and progress toward
     * the ones they haven't been awarded yet.
     */
    public function indexMe(Request $request, MedalProgressService $medalProgress): JsonResponse
    {
        return response()->json([
            'data' => $medalProgress->progressForUser($request->user()),
        ]);
    }
}
