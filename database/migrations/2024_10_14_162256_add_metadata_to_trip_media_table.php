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
        Schema::table('trip_media', function (Blueprint $table) {
            $table->timestamp('taken_at')->nullable()->after('filename');
            $table->string('photographer')->nullable()->after('taken_at');
            $table->string('copyright')->nullable()->after('photographer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trip_media', function (Blueprint $table) {
            $table->dropColumn(['taken_at', 'photographer', 'copyright']);
        });
    }
};