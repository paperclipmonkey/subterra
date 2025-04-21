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
        Schema::table('club_user', function (Blueprint $table) {
            // Add status column after user_id, default to 'pending'
            $table->string('status')->default('pending')->after('user_id');
            // Add index for faster lookups on status
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('club_user', function (Blueprint $table) {
            // Drop index first
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
