<?php

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::table('caves')->orderBy('id')->chunk(100, function ($caves) {
            foreach ($caves as $cave) {
                if ($cave->hero_image) {
                    \Illuminate\Support\Facades\DB::table('cave_media')->updateOrInsert(
                        ['cave_id' => $cave->id, 'type' => 'hero'],
                        [
                            'filename' => $cave->hero_image,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                if ($cave->entrance_image) {
                    \Illuminate\Support\Facades\DB::table('cave_media')->updateOrInsert(
                        ['cave_id' => $cave->id, 'type' => 'entrance'],
                        [
                            'filename' => $cave->entrance_image,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('cave_media')->truncate();
    }
};
