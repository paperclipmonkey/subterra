<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Records when (and whether) a callout was successfully registered with the
     * independent GCP backup watchdog. Null means the backup is NOT covering this
     * callout (watchdog down or unconfigured) — the callout is still monitored by the
     * primary Subterra scheduler, but the dashboard can surface the missing coverage.
     */
    public function up(): void
    {
        Schema::table('callouts', function (Blueprint $table) {
            $table->timestamp('watchdog_registered_at')->nullable()->after('warned_at');
        });
    }

    public function down(): void
    {
        Schema::table('callouts', function (Blueprint $table) {
            $table->dropColumn('watchdog_registered_at');
        });
    }
};
