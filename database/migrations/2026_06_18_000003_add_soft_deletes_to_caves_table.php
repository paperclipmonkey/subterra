<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Deleting a registry record is high-stakes and irreversible. Add soft
     * deletes so a removed cave can be restored (the audit log already supports
     * a `restored` event).
     */
    public function up(): void
    {
        Schema::table('caves', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('caves', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
