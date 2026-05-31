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
        Schema::create('cave_system_annotations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cave_system_id');
            $table->json('geojson');
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
        Schema::dropIfExists('cave_system_annotations');
    }
};
