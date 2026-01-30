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
        Schema::table('callouts', function (Blueprint $table) {
            $table->dropColumn('aws_watchdog_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('callouts', function (Blueprint $table) {
            $table->string('aws_watchdog_id')->nullable();
        });
    }
};
