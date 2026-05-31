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
        Schema::table('caves', function (Blueprint $table) {
            $table->string('registry')->nullable()->after('location_alt');
            $table->string('registry_id')->nullable()->after('registry');
            $table->index(['registry', 'registry_id'], 'caves_registry_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('caves', function (Blueprint $table) {
            $table->dropIndex('caves_registry_id_index');
            $table->dropColumn(['registry', 'registry_id']);
        });
    }
};
