<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cave;
use App\Services\CaveMergeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Merge a duplicate cave into the one to keep: the same cave held twice, as
 * when an OpenStreetMap import didn't recognise a cave under another spelling.
 */
class CaveMergeController extends Controller
{
    public function __construct(private CaveMergeService $merger)
    {
    }

    public function preview(Request $request, Cave $cave): JsonResponse
    {
        $source = $this->source($request, $cave);
        if ($source instanceof JsonResponse) {
            return $source;
        }

        return response()->json([
            'target' => ['id' => $cave->id, 'name' => $cave->name, 'slug' => $cave->slug, 'cave_system_id' => $cave->cave_system_id],
            'source' => ['id' => $source->id, 'name' => $source->name, 'slug' => $source->slug, 'cave_system_id' => $source->cave_system_id],
            'moves' => $this->merger->preview($cave, $source),
        ]);
    }

    public function merge(Request $request, Cave $cave): JsonResponse
    {
        $source = $this->source($request, $cave);
        if ($source instanceof JsonResponse) {
            return $source;
        }

        $this->merger->merge($cave, $source);

        return response()->json([
            'message' => "\"{$source->name}\" has been merged into \"{$cave->name}\".",
            'data' => ['id' => $cave->id, 'name' => $cave->name, 'slug' => $cave->slug, 'cave_system_id' => $cave->fresh()?->cave_system_id],
        ]);
    }

    private function source(Request $request, Cave $cave): Cave|JsonResponse
    {
        $request->validate(['source_id' => 'required|integer|exists:caves,id']);
        $source = Cave::findOrFail((int) $request->input('source_id'));

        if ($source->id === $cave->id) {
            return response()->json(['error' => 'Cannot merge a cave into itself.'], 422);
        }

        return $source;
    }
}
