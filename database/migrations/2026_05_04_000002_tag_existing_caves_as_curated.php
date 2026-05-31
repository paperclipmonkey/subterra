<?php

declare(strict_types=1);

use App\Models\Cave;
use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $curatedTag = Tag::where('tag', 'Curated')->where('category', 'curated')->first();

        if (!$curatedTag) {
            return;
        }

        // Attach the Curated tag to all existing caves that don't already have it
        Cave::whereDoesntHave('tags', function ($q) use ($curatedTag) {
            $q->where('tag_id', $curatedTag->id);
        })->each(function (Cave $cave) use ($curatedTag) {
            $cave->tags()->attach($curatedTag->id);
        });
    }

    public function down(): void
    {
        $curatedTag = Tag::where('tag', 'Curated')->where('category', 'curated')->first();

        if (!$curatedTag) {
            return;
        }

        DB::table('cave_tag')->where('tag_id', $curatedTag->id)->delete();
    }
};
