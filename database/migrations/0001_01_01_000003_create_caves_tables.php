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

        Schema::create('cave_systems', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index('cave_systems_slug');
            $table->text('description');
            $table->integer('length');
            $table->integer('depth');
        });

        Schema::create('caves', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index('caves_slug');
            $table->text('description');
            $table->foreignId('cave_system_id')->constrained('cave_systems', 'id');
            $table->string('location_name');
            $table->string('location_country');
            $table->string('location_lat');
            $table->string('location_lng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('caves');
        Schema::dropIfExists('cave_systems');
    }
};
