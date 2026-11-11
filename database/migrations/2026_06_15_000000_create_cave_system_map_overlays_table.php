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
        Schema::create('cave_system_map_overlays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cave_system_id');
            $table->string('name');
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            // [west, south, east, north] in WGS84, computed client-side on upload
            $table->json('bounds')->nullable();
            $table->float('opacity')->default(0.8);
            $table->boolean('visible_by_default')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->foreign('cave_system_id')
                  ->references('id')
                  ->on('cave_systems')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cave_system_map_overlays');
    }
};
