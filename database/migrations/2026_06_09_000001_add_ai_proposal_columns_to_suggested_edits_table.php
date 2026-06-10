<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('suggested_edits', function (Blueprint $table) {
            // 'user' for community suggestions, 'pip' for AI data-steward proposals
            $table->string('source')->default('user')->after('status');
            // Groups proposals created by a single AI bulk operation so they can
            // be reviewed and approved/rejected together
            $table->string('batch_id')->nullable()->after('source')->index();
            // The AI's stated evidence for the proposed change, shown to reviewers
            $table->text('reasoning')->nullable()->after('batch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('suggested_edits', function (Blueprint $table) {
            $table->dropColumn(['source', 'batch_id', 'reasoning']);
        });
    }
};
