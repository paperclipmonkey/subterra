<?php

use App\Models\Tag;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Tag::updateOrCreate(
            ['tag' => 'Curated', 'category' => 'curated'],
            [
                'type' => 'cave',
                'description' => 'A curated list of caves; likely longer, deeper or with more notable features. Caves without this tag may be smaller, less notable or less well-documented systems.',
                'assignable' => true,
            ]
        );
    }

    public function down(): void
    {
        Tag::where('tag', 'Curated')->where('category', 'curated')->delete();
    }
};
