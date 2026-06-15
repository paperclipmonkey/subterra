<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->string('hero_image_photographer')->nullable()->after('hero_image');
            $table->string('hero_image_copyright')->nullable()->after('hero_image_photographer');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn(['hero_image_photographer', 'hero_image_copyright']);
        });
    }
};
