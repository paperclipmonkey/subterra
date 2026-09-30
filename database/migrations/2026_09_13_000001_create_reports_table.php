<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * The moderation queue behind the platform's complaints process.
     *
     * The Online Safety Act requires an easy-to-find way for users to report
     * content and a route for complaints to be handled; until now the only
     * feedback channels were `corrections` and `suggested_edits`, which cover
     * cave data rather than conduct. Data-protection objections (someone
     * saying "you hold a record about me that I did not ask for") land in the
     * same queue, so there is one place an admin has to look.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Null for system-raised reports, such as an objection submitted
            // from a signed email link by someone who has never logged in.
            $table->string('reporter_id', 7)->nullable();
            $table->foreign('reporter_id')->references('id')->on('users')->nullOnDelete();

            // Polymorphic target, stored as a string because the platform mixes
            // integer keys (trips, media) with the users table's 7-char random
            // ids — the same approach api_interactions.trackable_id takes.
            $table->string('reportable_type')->nullable();
            $table->string('reportable_id')->nullable();

            $table->string('category');
            $table->text('details')->nullable();

            $table->string('status')->default('open'); // open, actioned, dismissed

            $table->string('handled_by', 7)->nullable();
            $table->foreign('handled_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('resolution_note')->nullable();

            $table->timestamps();

            // The admin queue lists open reports newest first, and the store
            // endpoint checks for an existing open report on the same target.
            $table->index(['status', 'created_at']);
            $table->index(['reportable_type', 'reportable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
