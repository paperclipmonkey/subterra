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
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('tag');
            $table->string('type');
            $table->text('description')->nullable();
        });

        Schema::create('cave_system_tag', function (Blueprint $table) {
            $table->bigInteger('cave_system_id');
            $table->bigInteger('tag_id');
            $table->index(columns: ['cave_system_id','tag_id'], name: 'cave_system_tag_cave_system_id_tag_id_index');
            $table->index(columns: ['tag_id','cave_system_id'], name: 'cave_system_tag_tag_id_cave_system_id_index');

            $table->foreign('cave_system_id')->references('id')->on('cave_systems');
            $table->foreign('tag_id')->references('id')->on('tags');
        });

        Schema::create('cave_tag', function (Blueprint $table) {
            $table->bigInteger('cave_id');
            $table->bigInteger('tag_id');
            $table->index(columns: ['cave_id','tag_id'], name: 'cave_tag_cave_id_tag_id_index');
            $table->index(columns: ['tag_id','cave_id'], name: 'cave_tag_tag_id_cave_id_index');

            $table->foreign('cave_id')->references('id')->on('caves');
            $table->foreign('tag_id')->references('id')->on('tags');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(table: 'cave_system_tag');
        Schema::dropIfExists('cave_tag');
        Schema::dropIfExists('tags');
    }
};
