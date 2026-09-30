<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add a self-declared date of birth so the platform can tell which accounts
     * belong to under-18s and apply the higher-privacy defaults the ICO
     * Children's Code requires (high privacy by default, and not making a
     * child's whereabouts visible to others).
     *
     * A full date rather than an age band is stored deliberately: a band goes
     * stale silently, so a 17-year-old would stay flagged as a child forever and
     * a child who banded themselves as an adult would never be re-checked. The
     * date is never exposed to other members — only the derived boolean is, and
     * only to the account owner and admins.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });
    }
};
