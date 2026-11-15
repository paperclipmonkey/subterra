<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Callout;
use App\Models\Cave;
use App\Models\CaveMedia;
use App\Models\CaveSystem;
use App\Models\Route as CaveRoute;
use App\Models\SuggestedEdit;
use App\Models\Trip;
use Illuminate\Support\Facades\DB;

/**
 * Merges one cave record into another, for the same cave held twice (an
 * OpenStreetMap import that didn't recognise a cave we had under another
 * spelling, say): everything that points at the duplicate moves to the cave
 * kept, then the duplicate is deleted (softly, keeping its OpenStreetMap node
 * so an import doesn't bring it back). Its system goes too, merged into the
 * kept cave's, once nothing else is in it.
 */
class CaveMergeService
{
    public function __construct(private CaveSystemMergeService $systems)
    {
    }

    /**
     * What a merge would move, for the admin's preview.
     *
     * @return array<string, int|bool>
     */
    public function preview(Cave $target, Cave $source): array
    {
        return [
            'trips' => Trip::where('entrance_cave_id', $source->id)->orWhere('exit_cave_id', $source->id)->count(),
            'routes' => CaveRoute::where('entrance_id', $source->id)->orWhere('exit_id', $source->id)->count(),
            'media' => $source->media()->count(),
            'collections' => $source->collections()->count(),
            'system_merged' => $source->cave_system_id !== $target->cave_system_id && $this->onlyCaveInSystem($source),
        ];
    }

    /**
     * @throws \InvalidArgumentException when source and target are the same cave
     * @throws \Throwable on failure (the transaction is rolled back)
     */
    public function merge(Cave $target, Cave $source): void
    {
        if ($source->id === $target->id) {
            throw new \InvalidArgumentException('Cannot merge a cave into itself.');
        }

        DB::transaction(function () use ($target, $source) {
            $sourceSystemId = $source->cave_system_id;
            $mergeSystem = $sourceSystemId !== $target->cave_system_id && $this->onlyCaveInSystem($source);

            // 1. Trips: their entrance and exit, and so their system
            Trip::where('entrance_cave_id', $source->id)->update(['entrance_cave_id' => $target->id, 'cave_system_id' => $target->cave_system_id]);
            Trip::where('exit_cave_id', $source->id)->update(['exit_cave_id' => $target->id]);

            // 2. Route guides' entrance and exit, and callouts
            CaveRoute::where('entrance_id', $source->id)->update(['entrance_id' => $target->id]);
            CaveRoute::where('exit_id', $source->id)->update(['exit_id' => $target->id]);
            Callout::where('cave_id', $source->id)->update(['cave_id' => $target->id]);
            Callout::where('exit_cave_id', $source->id)->update(['exit_cave_id' => $target->id]);

            // 3. Photos: one of each kind a cave (hero, entrance…), so only the kinds the kept cave lacks
            $held = $target->media()->pluck('type')->all();
            CaveMedia::where('cave_id', $source->id)->whereNotIn('type', $held)->update(['cave_id' => $target->id]);

            // 4. Tags, collections and a permit (one a cave), without doubling up
            $target->tags()->syncWithoutDetaching($source->tags()->pluck('tags.id')->all());
            $collections = DB::table('cave_collection')->where('cave_id', $source->id)->pluck('collection_id');
            foreach ($collections as $collectionId) {
                DB::table('cave_collection')->updateOrInsert(
                    ['collection_id' => $collectionId, 'cave_id' => $target->id],
                    ['created_at' => now(), 'updated_at' => now()],
                );
            }
            DB::table('cave_collection')->where('cave_id', $source->id)->delete();
            if (!DB::table('cave_permit')->where('cave_id', $target->id)->exists()) {
                DB::table('cave_permit')->where('cave_id', $source->id)->update(['cave_id' => $target->id]);
            }

            // 5. Suggested edits to the duplicate are edits to the cave kept
            SuggestedEdit::where('suggestable_type', Cave::class)->where('suggestable_id', $source->id)
                ->update(['suggestable_id' => $target->id]);

            // 6. Its OpenStreetMap node, where the kept cave has none (the duplicate keeps
            //    its own too, so an import knows the node and doesn't bring it back)
            if (empty($target->osm_node_id) && !empty($source->osm_node_id)) {
                $target->osm_node_id = $source->osm_node_id;
                $target->save();
            }

            // 7. Into the kept cave's system, then deleted
            $source->cave_system_id = $target->cave_system_id;
            $source->save();
            $source->delete();

            // 8. Its system, if the duplicate was all there was in it
            if ($mergeSystem && ($system = CaveSystem::find($sourceSystemId)) && ($into = CaveSystem::find($target->cave_system_id))) {
                $this->systems->merge($into, $system);
            }
        });
    }

    private function onlyCaveInSystem(Cave $cave): bool
    {
        return Cave::where('cave_system_id', $cave->cave_system_id)->whereKeyNot($cave->id)->doesntExist();
    }
}
