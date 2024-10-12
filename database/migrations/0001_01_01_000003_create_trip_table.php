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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('cave_system_id')->constrained('cave_systems', 'id');
            $table->foreignId('entrance_cave_id')->constrained('caves', 'id');
            $table->foreignId('exit_cave_id')->constrained('caves', 'id');
            $table->dateTime('start_time')->nullable();
            $table->dateTime('end_time')->nullable();
        });

        Schema::create('trip_user', function (Blueprint $table) {
            $table->bigInteger('trip_id');
            $table->bigInteger('user_id');
            $table->index(columns: ['trip_id','user_id'], name: 'trip_user_trip_id_user_id_index');
            $table->index(columns: ['user_id','trip_id'], name: 'trip_user_user_id_trip_id_index');

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('trip_id')->references('id')->on('trips');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'trip_user');
        Schema::dropIfExists('trips');
    }
};
