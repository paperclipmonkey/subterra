<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cave_system_files', function (Blueprint $table) {
            $table->string('thumbnail_filename')->nullable()->after('filename');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cave_system_files', function (Blueprint $table) {
            $table->dropColumn('thumbnail_filename');
        });
    }
};
