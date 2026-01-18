<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('callouts', function (Blueprint $table) {
            $table->dateTime('expected_exit_time')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('callouts', function (Blueprint $table) {
            $table->dateTime('expected_exit_time')->nullable(false)->change();
        });
    }
};
