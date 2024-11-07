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
        Schema::table("caves", function (Blueprint $table) {
            $table->string( 'hero_image', 255)->nullable();
            $table->string( 'entrance_image', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("caves", function (Blueprint $table) {
            $table->dropColumn('hero_image');
            $table->dropColumn('entrance_image');
        });
    }
};
