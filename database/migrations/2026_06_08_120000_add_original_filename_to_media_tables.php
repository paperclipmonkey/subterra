<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     *
     * Records the preserved source image (the file the resized variants were
     * generated from) so originals are never lost and images can be
     * re-processed in future without quality loss.
     */
    public function up(): void
    {
        Schema::table('cave_media', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('filename');
        });

        Schema::table('trip_media', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('filename');
        });

        Schema::table('route_media', function (Blueprint $table) {
            $table->string('original_filename')->nullable()->after('path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cave_media', function (Blueprint $table) {
            $table->dropColumn('original_filename');
        });

        Schema::table('trip_media', function (Blueprint $table) {
            $table->dropColumn('original_filename');
        });

        Schema::table('route_media', function (Blueprint $table) {
            $table->dropColumn('original_filename');
        });
    }
};
