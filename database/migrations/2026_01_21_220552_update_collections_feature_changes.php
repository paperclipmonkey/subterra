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
        Schema::table('collections', function (Blueprint $table) {
            $table->dropColumn('is_official');
        });

        Schema::table('cave_collection', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            $table->boolean('is_official')->default(false);
        });

        Schema::table('cave_collection', function (Blueprint $table) {
            $table->dropColumn(['description', 'sort_order']);
        });
    }
};
