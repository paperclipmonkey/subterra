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
        Schema::create('hut_tag', function (Blueprint $table) {
            $table->bigInteger('hut_id');
            $table->bigInteger('tag_id');
            $table->index(['hut_id', 'tag_id'], 'hut_tag_hut_id_tag_id_index');
            $table->index(['tag_id', 'hut_id'], 'hut_tag_tag_id_hut_id_index');

            $table->foreign('hut_id')->references('id')->on('huts')->onDelete('cascade');
            $table->foreign('tag_id')->references('id')->on('tags')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hut_tag');
    }
};
