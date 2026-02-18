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
        Schema::table('on_call_shifts', function (Blueprint $table) {
            $table->dateTime('notified_at')->nullable()->after('end_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('on_call_shifts', function (Blueprint $table) {
            $table->dropColumn('notified_at');
        });
    }
};
