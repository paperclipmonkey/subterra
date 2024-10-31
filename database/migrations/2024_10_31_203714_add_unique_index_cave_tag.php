<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cave_tag', function (Blueprint $table) {
            $table->unique(['cave_id', 'tag_id'], 'cave_tag_cave_id_tag_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cave_tag', function (Blueprint $table) {
            $table->dropIndex('cave_tag_cave_id_tag_id_unique');
        });
    }
};
