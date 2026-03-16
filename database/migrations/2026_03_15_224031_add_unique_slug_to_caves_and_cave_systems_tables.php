<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // De-duplicate any existing slugs before adding the unique constraint.
        // Appends -2, -3, etc. to later duplicates so the constraint can be applied safely.
        foreach (['caves', 'cave_systems'] as $tableName) {
            $duplicates = DB::table($tableName)
                ->select('slug', DB::raw('COUNT(*) as count'))
                ->groupBy('slug')
                ->havingRaw('COUNT(*) > 1')
                ->pluck('slug');

            foreach ($duplicates as $slug) {
                $rows = DB::table($tableName)
                    ->where('slug', $slug)
                    ->orderBy('id')
                    ->get(['id', 'slug']);

                // Keep the first occurrence, rename the rest
                foreach ($rows->skip(1)->values() as $i => $row) {
                    $newSlug = $slug.'-'.($i + 2);
                    while (DB::table($tableName)->where('slug', $newSlug)->exists()) {
                        $newSlug .= '-x';
                    }
                    DB::table($tableName)->where('id', $row->id)->update(['slug' => $newSlug]);
                }
            }
        }

        Schema::table('caves', function (Blueprint $table) {
            $table->dropIndex('caves_slug');
            $table->unique('slug', 'caves_slug_unique');
        });

        Schema::table('cave_systems', function (Blueprint $table) {
            $table->dropIndex('cave_systems_slug');
            $table->unique('slug', 'cave_systems_slug_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caves', function (Blueprint $table) {
            $table->dropUnique('caves_slug_unique');
            $table->index('slug', 'caves_slug');
        });

        Schema::table('cave_systems', function (Blueprint $table) {
            $table->dropUnique('cave_systems_slug_unique');
            $table->index('slug', 'cave_systems_slug');
        });
    }
};
