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
        Schema::table('permits', function (Blueprint $table) {
            $table->boolean('has_season')->default(false)->after('max_participants');
            $table->string('season_start', 5)->nullable()->after('has_season')->comment('MM-DD e.g. 04-01');
            $table->string('season_end', 5)->nullable()->after('season_start')->comment('MM-DD e.g. 03-10');
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            $table->dropColumn(['has_season', 'season_start', 'season_end']);
        });
    }
};
