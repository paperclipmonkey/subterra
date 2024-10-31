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
            $table->text('description')->nullable();
            $table->integer('length');
            $table->integer('vertical_range');
        });

        Schema::create('caves', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->index('caves_slug');
            $table->text('description')->nullable();
            $table->foreignId('cave_system_id')->constrained('cave_systems', 'id');
            $table->string('location_name');
            $table->string('location_country');
            $table->float('location_lat');
            $table->float('location_lng');
            $table->float('location_alt')->comment('Altitude in meters');
            $table->text('access_info')->nullable();
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
