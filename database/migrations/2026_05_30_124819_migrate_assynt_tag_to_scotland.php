<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        // Ensure the Scotland tag exists.
        $scotlandId = DB::table('tags')
            ->where('tag', 'Scotland')
            ->where('category', 'region')
            ->value('id');

        if (! $scotlandId) {
            $scotlandId = DB::table('tags')->insertGetId([
                'tag'         => 'Scotland',
                'type'        => 'cave',
                'category'    => 'region',
                'description' => "Scotland is home to some of the UK's most remote and challenging caves, spread across the Highlands and Islands.",
            ]);
        }

        $assyntId = DB::table('tags')
            ->where('tag', 'Assynt')
            ->where('category', 'region')
            ->value('id');

        if (! $assyntId) {
            return; // Nothing to migrate.
        }

        // Re-point cave_tag rows from Assynt -> Scotland, skipping caves that
        // already have Scotland (to avoid unique constraint violations).
        $alreadyScotland = DB::table('cave_tag')
            ->where('tag_id', $scotlandId)
            ->pluck('cave_id')
            ->all();

        DB::table('cave_tag')
            ->where('tag_id', $assyntId)
            ->whereNotIn('cave_id', $alreadyScotland)
            ->update(['tag_id' => $scotlandId]);

        // Delete any remaining Assynt rows (caves that already had Scotland).
        DB::table('cave_tag')->where('tag_id', $assyntId)->delete();

        // Repeat for cave_system_tag.
        $alreadyScotlandSys = DB::table('cave_system_tag')
            ->where('tag_id', $scotlandId)
            ->pluck('cave_system_id')
            ->all();

        DB::table('cave_system_tag')
            ->where('tag_id', $assyntId)
            ->whereNotIn('cave_system_id', $alreadyScotlandSys)
            ->update(['tag_id' => $scotlandId]);

        DB::table('cave_system_tag')->where('tag_id', $assyntId)->delete();

        // Remove the Assynt tag record entirely.
        DB::table('tags')->where('id', $assyntId)->delete();
    }

    public function down(): void
    {
        // Recreate the Assynt tag. Cave associations are not reversible since
        // we don't know which records were originally Assynt vs Scotland.
        DB::table('tags')->updateOrInsert(
            ['tag' => 'Assynt', 'category' => 'region'],
            [
                'type'        => 'cave',
                'description' => "Scotland has some of the UK's most remote and challenging caves, including the famous Claonaite System.",
            ]
        );
    }
};
