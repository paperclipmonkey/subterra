<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        // Ensure the canonical "Mendip" tag exists.
        $mendipId = DB::table('tags')
            ->where('tag', 'Mendip')
            ->where('category', 'region')
            ->value('id');

        if (!$mendipId) {
            $mendipId = DB::table('tags')->insertGetId([
                'tag' => 'Mendip',
                'type' => 'cave',
                'category' => 'region',
                'description' => "The Mendip hills in Somerset are home to some of the UK's most famous caves, including Wookey Hole, Swildon's Hole, and GB Cave.",
            ]);
        }

        $mendipsId = DB::table('tags')
            ->where('tag', 'Mendips')
            ->where('category', 'region')
            ->value('id');

        if (!$mendipsId) {
            return; // Nothing to migrate.
        }

        // Re-point cave_tag rows from Mendips -> Mendip, skipping caves that
        // already have Mendip (to avoid unique constraint violations).
        $alreadyMendip = DB::table('cave_tag')
            ->where('tag_id', $mendipId)
            ->pluck('cave_id')
            ->all();

        DB::table('cave_tag')
            ->where('tag_id', $mendipsId)
            ->whereNotIn('cave_id', $alreadyMendip)
            ->update(['tag_id' => $mendipId]);

        // Delete any remaining Mendips rows (caves that already had Mendip).
        DB::table('cave_tag')->where('tag_id', $mendipsId)->delete();

        // Repeat for cave_system_tag.
        $alreadyMendipSys = DB::table('cave_system_tag')
            ->where('tag_id', $mendipId)
            ->pluck('cave_system_id')
            ->all();

        DB::table('cave_system_tag')
            ->where('tag_id', $mendipsId)
            ->whereNotIn('cave_system_id', $alreadyMendipSys)
            ->update(['tag_id' => $mendipId]);

        DB::table('cave_system_tag')->where('tag_id', $mendipsId)->delete();

        // Remove the duplicate "Mendips" tag record entirely.
        DB::table('tags')->where('id', $mendipsId)->delete();
    }

    public function down(): void
    {
        // Recreate the Mendips tag. Cave associations are not reversible since
        // we don't know which records were originally Mendips vs Mendip.
        DB::table('tags')->updateOrInsert(
            ['tag' => 'Mendips', 'category' => 'region'],
            [
                'type' => 'cave',
                'category' => 'region',
                'description' => "The Mendip hills in Somerset are home to some of the UK's most famous caves, including Wookey Hole, Swildon's Hole, and GB Cave.",
            ]
        );
    }
};
