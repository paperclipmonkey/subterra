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
        Schema::create('api_interactions', function (Blueprint $table) {
            $table->id();
            $table->string('trackable_type');
            $table->unsignedBigInteger('trackable_id');
            // Interactions are immutable audit records, so we only store when they were created.
            // The corresponding Eloquent model disables automatic timestamps ($timestamps = false),
            // hence there is no "updated_at" column by design.
            $table->timestamp('created_at');

            $table->index(['trackable_type', 'trackable_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_interactions');
    }
};
