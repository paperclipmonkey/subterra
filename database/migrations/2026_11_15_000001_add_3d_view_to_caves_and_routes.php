<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A cave's place in its system's 3D view (cave-viewer's #at=: an entrance's
     * name there, or a survey's), where it isn't the cave's own name; and a
     * route guide's way through the 3D view, as cave-viewer reports it (its
     * link, length, climb, stops and passages).
     */
    public function up(): void
    {
        Schema::table('caves', function (Blueprint $table) {
            $table->string('viewer_at', 255)->nullable();
        });
        Schema::table('routes', function (Blueprint $table) {
            $table->json('viewer_route')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn('viewer_route');
        });
        Schema::table('caves', function (Blueprint $table) {
            $table->dropColumn('viewer_at');
        });
    }
};
