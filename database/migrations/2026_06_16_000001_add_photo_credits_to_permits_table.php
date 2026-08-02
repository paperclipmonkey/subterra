<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            $table->string('photo_photographer')->nullable()->after('photo_path');
            $table->string('photo_copyright')->nullable()->after('photo_photographer');
            // Preserves the raw upload so the image can be re-processed without
            // quality loss; written by the GCP media webhook (see GcpMediaWebhookController).
            $table->string('original_filename')->nullable()->after('photo_copyright');
        });
    }

    public function down(): void
    {
        Schema::table('permits', function (Blueprint $table) {
            $table->dropColumn(['photo_photographer', 'photo_copyright', 'original_filename']);
        });
    }
};
