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
            $table->float('location_lat')->nullable()->change();
            $table->float('location_lng')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caves', function (Blueprint $table) {
            $table->float('location_lat')->nullable(false)->change();
            $table->float('location_lng')->nullable(false)->change();
        });
    }
};
