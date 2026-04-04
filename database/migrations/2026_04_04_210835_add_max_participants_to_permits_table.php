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
        Schema::table('permits', function (Blueprint $table) {
            $table->boolean('has_max_participants')->default(false)->after('max_groups_per_day');
            $table->unsignedInteger('max_participants')->nullable()->after('has_max_participants');
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            $table->dropColumn(['has_max_participants', 'max_participants']);
        });
    }
};
