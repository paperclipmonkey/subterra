<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\CaveSystemFile;
use App\Models\Route as CaveRoute;
use App\Models\SuggestedEdit;
use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CaveSystemController extends Controller
{
    /**
     * Merge another cave system into this one.
     *
     * Migrates all caves, routes, trips, files, tags, and suggested edits
     * from the source system to the target, then deletes the source.
     */
    public function merge(Request $request, CaveSystem $caveSystem)
    {
        $request->validate([
            'source_id' => 'required|integer|exists:cave_systems,id',
        ]);

        $sourceId = (int) $request->input('source_id');

        if ($sourceId === $caveSystem->id) {
            return response()->json(['error' => 'Cannot merge a cave system into itself.'], 422);
        }

        $source = CaveSystem::findOrFail($sourceId);

        try {
            app(\App\Services\CaveSystemMergeService::class)->merge($caveSystem, $source);

            $caveSystem->load(['caves', 'routes', 'files', 'tags']);

            return response()->json([
                'message' => "Cave system \"{$source->name}\" has been merged into \"{$caveSystem->name}\".",
                'data' => $caveSystem,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Merge failed: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview what will happen when merging a source system into this one.
     */
    public function mergePreview(Request $request, CaveSystem $caveSystem)
    {
        $request->validate([
            'source_id' => 'required|integer|exists:cave_systems,id',
        ]);

        $sourceId = (int) $request->input('source_id');

        if ($sourceId === $caveSystem->id) {
            return response()->json(['error' => 'Cannot merge a cave system into itself.'], 422);
        }

        $source = CaveSystem::findOrFail($sourceId);

        return response()->json([
            'target' => [
                'id' => $caveSystem->id,
                'name' => $caveSystem->name,
                'caves_count' => $caveSystem->caves()->count(),
                'routes_count' => $caveSystem->routes()->count(),
                'files_count' => $caveSystem->files()->count(),
            ],
            'source' => [
                'id' => $source->id,
                'name' => $source->name,
                'caves_count' => $source->caves()->count(),
                'routes_count' => $source->routes()->count(),
                'files_count' => $source->files()->count(),
            ],
            'result' => [
                'caves_count' => $caveSystem->caves()->count() + $source->caves()->count(),
                'routes_count' => $caveSystem->routes()->count() + $source->routes()->count(),
                'files_count' => $caveSystem->files()->count() + $source->files()->count(),
                'source_will_be_deleted' => true,
            ],
        ]);
    }

    /**
     * Delete a cave system and all associated caves, routes, files, tags, and suggested edits.
     *
     * Only allowed when no trips exist for this system.
     */
    public function destroy(CaveSystem $caveSystem)
    {
        $tripCount = Trip::where('cave_system_id', $caveSystem->id)->count();

        if ($tripCount > 0) {
            return response()->json([
                'error' => 'Cannot delete a cave system that has trips. Remove all trips first.',
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Delete suggested edits for the system and its caves
            $caveIds = $caveSystem->caves()->pluck('id')->toArray();
            SuggestedEdit::where('suggestable_type', CaveSystem::class)
                ->where('suggestable_id', $caveSystem->id)
                ->delete();
            if (!empty($caveIds)) {
                SuggestedEdit::where('suggestable_type', Cave::class)
                    ->whereIn('suggestable_id', $caveIds)
                    ->delete();
            }

            // Delete files from storage
            $files = $caveSystem->files()->get();
            foreach ($files as $file) {
                $path = "cave_system_files/{$caveSystem->id}/{$file->filename}";
                if (Storage::disk('media')->exists($path)) {
                    Storage::disk('media')->delete($path);
                }
                if ($file->thumbnail_filename) {
                    $thumbPath = "cave_system_files/{$caveSystem->id}/{$file->thumbnail_filename}";
                    if (Storage::disk('media')->exists($thumbPath)) {
                        Storage::disk('media')->delete($thumbPath);
                    }
                }
            }
            $caveSystem->files()->delete();

            // Delete routes
            $caveSystem->routes()->delete();

            // Delete caves
            $caveSystem->caves()->delete();

            // Detach tags
            $caveSystem->tags()->detach();

            // Delete the system
            $caveSystem->delete();

            DB::commit();

            return response()->json([
                'message' => "Cave system \"{$caveSystem->name}\" has been deleted.",
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => 'Delete failed: '.$e->getMessage(),
            ], 500);
        }
    }
}
