<?php

namespace App\Http\Controllers;

use App\Models\Catchment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CatchmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Catchment::withCount('caveSystems')->orderBy('name')->get()
        ]);
    }

    public function show(Catchment $catchment): JsonResponse
    {
        return response()->json([
            'data' => $catchment->loadCount('caveSystems')
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'reference_id' => 'required|string|unique:catchments,reference_id',
            'gauges' => 'nullable|array',
            'gauges.*.type' => 'nullable|string|in:river,rain',
            'gauges.*.rloi_id' => 'required_without:gauges.*.station_id|nullable|string',
            'gauges.*.station_id' => 'required_without:gauges.*.rloi_id|nullable|string',
            'gauges.*.name' => 'required|string',
        ]);

        $catchment = Catchment::create($validated);

        return response()->json([
            'data' => $catchment
        ], 201);
    }

    public function update(Request $request, Catchment $catchment): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'reference_id' => 'required|string|unique:catchments,reference_id,' . $catchment->id,
            'gauges' => 'nullable|array',
            'gauges.*.type' => 'nullable|string|in:river,rain',
            'gauges.*.rloi_id' => 'required_without:gauges.*.station_id|nullable|string',
            'gauges.*.station_id' => 'required_without:gauges.*.rloi_id|nullable|string',
            'gauges.*.name' => 'required|string',
        ]);

        $catchment->update($validated);

        return response()->json([
            'data' => $catchment
        ]);
    }

    public function destroy(Catchment $catchment): JsonResponse
    {
        if ($catchment->caveSystems()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete catchment with associated cave systems'
            ], 422);
        }

        $catchment->delete();

        return response()->json(null, 204);
    }
}
