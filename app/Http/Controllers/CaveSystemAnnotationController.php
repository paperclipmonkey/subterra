<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CaveSystem;
use Illuminate\Http\Request;

class CaveSystemAnnotationController extends Controller
{
    public function show(CaveSystem $caveSystem)
    {
        $annotation = $caveSystem->annotation;

        if (!$annotation) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $annotation]);
    }

    public function store(Request $request, CaveSystem $caveSystem)
    {
        $validated = $request->validate([
            'geojson' => 'required|array',
            'geojson.type' => 'required|string|in:FeatureCollection',
            'geojson.features' => 'required|array',
            'geojson.features.*.type' => 'required|string|in:Feature',
            'geojson.features.*.geometry' => 'required|array',
            'geojson.features.*.geometry.type' => 'required|string|in:Point,LineString',
            'geojson.features.*.geometry.coordinates' => 'required|array',
            'geojson.features.*.properties' => 'nullable|array',
            'geojson.features.*.properties.annotation_type' => 'nullable|string|in:parking,house,walking_route',
            'geojson.features.*.properties.description' => 'nullable|string|max:500',
        ]);

        $annotation = $caveSystem->annotation()->updateOrCreate(
            ['cave_system_id' => $caveSystem->id],
            ['geojson' => $validated['geojson']]
        );

        return response()->json(['data' => $annotation], 200);
    }

    public function destroy(CaveSystem $caveSystem)
    {
        $caveSystem->annotation?->delete();

        return response()->json(null, 204);
    }
}
