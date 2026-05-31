<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('tags')
            ->where('category', 'access')
            ->where('tag', 'Gated')
            ->update(['tag' => 'Padlocked']);

        DB::table('tags')
            ->where('category', 'access')
            ->where('tag', 'Leader')
            ->update(['tag' => 'Warden']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('tags')
            ->where('category', 'access')
            ->where('tag', 'Padlocked')
            ->update(['tag' => 'Gated']);

        DB::table('tags')
            ->where('category', 'access')
            ->where('tag', 'Warden')
            ->update(['tag' => 'Leader']);
    }
};
