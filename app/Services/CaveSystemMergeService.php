<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\CaveSystemFile;
use App\Models\Route as CaveRoute;
use App\Models\SuggestedEdit;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Merges one cave system into another: migrates all caves, routes, trips,
 * files, tags, and suggested edits from the source system to the target,
 * then deletes the source.
 *
 * Used by the admin merge endpoint and by approval of AI-proposed merges.
 */
class CaveSystemMergeService
{
    /**
     * @throws \InvalidArgumentException when source and target are the same system
     * @throws \Throwable on failure (transaction is rolled back)
     */
    public function merge(CaveSystem $target, CaveSystem $source): void
    {
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot merge a cave system into itself.');
        }

        DB::beginTransaction();

        try {
            // 1. Migrate caves
            Cave::where('cave_system_id', $source->id)
                ->update(['cave_system_id' => $target->id]);

            // 2. Migrate routes
            CaveRoute::where('cave_system_id', $source->id)
                ->update(['cave_system_id' => $target->id]);

            // 3. Migrate trips
            Trip::where('cave_system_id', $source->id)
                ->update(['cave_system_id' => $target->id]);

            // 4. Migrate files — move storage and update records
            $sourceFiles = CaveSystemFile::where('cave_system_id', $source->id)->get();
            foreach ($sourceFiles as $file) {
                $oldPath = "cave_system_files/{$source->id}/{$file->filename}";
                $newPath = "cave_system_files/{$target->id}/{$file->filename}";

                if (Storage::disk('media')->exists($oldPath)) {
                    Storage::disk('media')->move($oldPath, $newPath);
                }

                if ($file->thumbnail_filename) {
                    $oldThumb = "cave_system_files/{$source->id}/{$file->thumbnail_filename}";
                    $newThumb = "cave_system_files/{$target->id}/{$file->thumbnail_filename}";
                    if (Storage::disk('media')->exists($oldThumb)) {
                        Storage::disk('media')->move($oldThumb, $newThumb);
                    }
                }

                $file->update(['cave_system_id' => $target->id]);
            }

            // 5. Migrate tags (sync without detaching to avoid duplicates)
            $sourceTags = $source->tags()->pluck('tags.id')->toArray();
            if (!empty($sourceTags)) {
                $target->tags()->syncWithoutDetaching($sourceTags);
            }

            // 6. Migrate suggested edits
            SuggestedEdit::where('suggestable_type', CaveSystem::class)
                ->where('suggestable_id', $source->id)
                ->update(['suggestable_id' => $target->id]);

            // 7. Merge metadata — keep target values, fill in blanks from source
            $fieldsToMerge = ['description', 'references', 'catchment_id'];
            foreach ($fieldsToMerge as $field) {
                if (empty($target->$field) && !empty($source->$field)) {
                    $target->$field = $source->$field;
                }
            }

            // Take the larger length and vertical_range
            $target->length = max($target->length ?? 0, $source->length ?? 0);
            $target->vertical_range = max($target->vertical_range ?? 0, $source->vertical_range ?? 0);
            $target->save();

            // 8. Delete source system (all relations already migrated)
            $source->tags()->detach();
            $source->delete();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }
}
