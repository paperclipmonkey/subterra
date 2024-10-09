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
            $table->string('description');
        });

        Schema::create('caves', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description');
            $table->foreignId('cave_system_id')->nullable()->constrained('cave_systems', 'id');
            $table->integer('length');
            $table->integer('depth'); 
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
