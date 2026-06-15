<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    private string $oldDescription = "Yorkshire is home to the UK's deepest cave, Gaping Gill, as well as the famous White Scar Cave.";

    private string $newDescription = "Northern England above the Peak District, including the Yorkshire Dales — the UK's premier caving region. Home to the country's deepest cave, Gaping Gill, as well as the famous White Scar Cave.";

    public function up(): void
    {
        $yorkshireId = DB::table('tags')
            ->where('tag', 'Yorkshire')
            ->where('category', 'region')
            ->value('id');

        if (!$yorkshireId) {
            return; // Nothing to rename.
        }

        $northernId = DB::table('tags')
            ->where('tag', 'Northern')
            ->where('category', 'region')
            ->value('id');

        // A "Northern" region tag already exists — consolidate Yorkshire into it
        // rather than colliding, re-pointing every association across the pivots.
        if ($northernId && $northernId !== $yorkshireId) {
            foreach (['cave_tag' => 'cave_id', 'cave_system_tag' => 'cave_system_id'] as $table => $column) {
                $alreadyTagged = DB::table($table)
                    ->where('tag_id', $northernId)
                    ->pluck($column)
                    ->all();

                DB::table($table)
                    ->where('tag_id', $yorkshireId)
                    ->whereNotIn($column, $alreadyTagged)
                    ->update(['tag_id' => $northernId]);

                DB::table($table)->where('tag_id', $yorkshireId)->delete();
            }

            DB::table('tags')->where('id', $yorkshireId)->delete();
            DB::table('tags')->where('id', $northernId)->update(['description' => $this->newDescription]);

            return;
        }

        // Plain rename — keeping the same tag id means every existing cave and
        // cave-system association stays attached untouched.
        DB::table('tags')->where('id', $yorkshireId)->update([
            'tag' => 'Northern',
            'description' => $this->newDescription,
        ]);
    }

    public function down(): void
    {
        DB::table('tags')
            ->where('tag', 'Northern')
            ->where('category', 'region')
            ->update([
                'tag' => 'Yorkshire',
                'description' => $this->oldDescription,
            ]);
    }
};
