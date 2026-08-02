<?php

declare(strict_types=1);

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
        // Remove duplicate participant rows first (keep one per pair). The
        // pivot has no primary key, so delete every copy of a duplicated pair
        // and re-insert a single row — portable across sqlite and postgres.
        $duplicates = DB::table('trip_user')
            ->select('trip_id', 'user_id')
            ->groupBy('trip_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $duplicate) {
            DB::table('trip_user')
                ->where('trip_id', $duplicate->trip_id)
                ->where('user_id', $duplicate->user_id)
                ->delete();

            DB::table('trip_user')->insert([
                'trip_id' => $duplicate->trip_id,
                'user_id' => $duplicate->user_id,
            ]);
        }

        Schema::table('trip_user', function (Blueprint $table) {
            $table->unique(['trip_id', 'user_id'], 'trip_user_trip_id_user_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_user', function (Blueprint $table) {
            $table->dropUnique('trip_user_trip_id_user_id_unique');
        });
    }
};
