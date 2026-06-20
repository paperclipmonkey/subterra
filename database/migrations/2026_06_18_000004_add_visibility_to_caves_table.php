<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Whole-record visibility. `public` is the current behaviour; `admin_only`
     * sites (e.g. coal mines with no public access) exist in the registry for
     * safety but are visible only to data admins and must never leak into public
     * lists, search, the map, or the AI index.
     */
    public function up(): void
    {
        Schema::table('caves', function (Blueprint $table): void {
            $table->string('visibility')->default('public')->index()->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('caves', function (Blueprint $table): void {
            $table->dropColumn('visibility');
        });
    }
};
