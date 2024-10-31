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
        Schema::table('caves', function (Blueprint $table) {
            $table->index(['name'], 'caves_name_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caves', function (Blueprint $table) {
            $table->dropIndex('caves_name_index');
        });
    }
};
