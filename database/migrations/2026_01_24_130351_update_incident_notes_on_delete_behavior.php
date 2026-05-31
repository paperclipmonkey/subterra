<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('incident_notes', function (Blueprint $table) {
            // Drop current foreign key if it exists.
            // In SQLite this might be tricky, but for standard DBs we'd do this:
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_notes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users');
        });
    }
};
