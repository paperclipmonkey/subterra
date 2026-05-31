<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safety check: Ensure we have data in cave_media before dropping legacy columns
        // We only check if the table exists and has at least one record (if any caves had images)
        $caveWithImageCount = \Illuminate\Support\Facades\DB::table('caves')
            ->whereNotNull('hero_image')
            ->orWhereNotNull('entrance_image')
            ->count();

        if ($caveWithImageCount > 0) {
            $mediaCount = \Illuminate\Support\Facades\DB::table('cave_media')->count();
            if ($mediaCount === 0) {
                throw new \Exception('Safety check failed: cave_media table is empty but legacy columns have data. Migration aborted.');
            }
        }

        Schema::table('caves', function (Blueprint $table) {
            $table->dropColumn(['hero_image', 'entrance_image']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caves', function (Blueprint $table) {
            $table->string('hero_image')->nullable();
            $table->string('entrance_image')->nullable();
        });

        // Populate the columns back from cave_media
        \Illuminate\Support\Facades\DB::table('cave_media')
            ->whereIn('type', ['hero', 'entrance'])
            ->orderBy('id')
            ->chunk(100, function ($mediaItems) {
                foreach ($mediaItems as $item) {
                    \Illuminate\Support\Facades\DB::table('caves')
                        ->where('id', $item->cave_id)
                        ->update([
                            ($item->type === 'hero' ? 'hero_image' : 'entrance_image') => $item->filename,
                        ]);
                }
            });
    }
};
