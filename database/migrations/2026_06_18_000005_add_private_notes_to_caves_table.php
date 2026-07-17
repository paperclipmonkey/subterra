<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Free-text private notes for a cave — sensitive information (landowner
     * contacts, key locations, access caveats) that data admins record but which
     * must never reach ordinary users or the AI search index. Only surfaced to
     * data admins via CaveResource.
     */
    public function up(): void
    {
        Schema::table('caves', function (Blueprint $table): void {
            $table->text('private_notes')->nullable()->after('access_info');
        });
    }

    public function down(): void
    {
        Schema::table('caves', function (Blueprint $table): void {
            $table->dropColumn('private_notes');
        });
    }
};
