<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Routes through a cave's 3D survey (cave-viewer, e.g. the CaveSurveying sites):
     * a cave system can name its 3D view, and a trip can keep the route it took
     * there, as cave-viewer reports it (its link, length, stops and passages), with
     * the times the party reached each stop where they were noted.
     */
    public function up(): void
    {
        Schema::table('cave_systems', function (Blueprint $table) {
            $table->string('viewer_url', 500)->nullable()->after('references');
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->json('route')->nullable()->after('exit_cave_id');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('route');
        });
        Schema::table('cave_systems', function (Blueprint $table) {
            $table->dropColumn('viewer_url');
        });
    }
};
