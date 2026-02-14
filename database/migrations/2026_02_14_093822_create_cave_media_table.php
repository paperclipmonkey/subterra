<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cave_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cave_id')->constrained()->onDelete('cascade');
            $table->string('type'); // 'hero', 'entrance', etc.
            $table->string('filename');
            $table->string('title')->nullable();
            $table->string('photographer')->nullable();
            $table->string('copyright')->nullable();
            $table->timestamps();

            $table->unique(['cave_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cave_media');
    }
};
