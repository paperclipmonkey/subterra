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
        Schema::create('catchments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('reference_id')->unique(); // Random alphanumeric ID
            $table->jsonb('gauges')->nullable();
            $table->timestamps();
        });

        Schema::table('cave_systems', function (Blueprint $table) {
            $table->foreignId('catchment_id')->nullable()->constrained('catchments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cave_systems', function (Blueprint $table) {
            $table->dropForeign(['catchment_id']);
            $table->dropColumn('catchment_id');
        });

        Schema::dropIfExists('catchments');
    }
};
