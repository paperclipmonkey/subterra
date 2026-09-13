<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Records that the Article 14 notice has been sent for an account another
     * member created on this person's behalf.
     *
     * Members can add a fellow caver as a trip participant by name and email,
     * which creates a real user record for someone who has never visited the
     * site. UK GDPR Article 14 requires telling that person their data is held,
     * where it came from, and how to object. The timestamp makes the send
     * idempotent, so re-running or retrying can't spam someone who was already
     * told.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('placeholder_notice_sent_at')->nullable()->after('date_of_birth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('placeholder_notice_sent_at');
        });
    }
};
