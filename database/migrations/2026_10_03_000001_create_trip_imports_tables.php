<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Staging area for Pip's trip import.
     *
     * A logbook is parsed and matched against caves and people server-side,
     * then held here while Pip walks the user through whatever could not be
     * matched. The model only ever sees compact summaries of these rows, never
     * the raw file, and trips are created from the rows in one deterministic
     * step once the user confirms.
     */
    public function up(): void
    {
        Schema::create('trip_imports', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 7);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('status')->default('open'); // open, completed, discarded
            $table->string('default_visibility')->default('public');
            $table->string('filename')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('trip_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_import_id')->constrained('trip_imports')->cascadeOnDelete();
            // 1-based position within the import; the number Pip and the user refer to.
            $table->unsignedInteger('row_number');
            // Line in the uploaded file, when the row came from one.
            $table->unsignedInteger('source_line')->nullable();
            $table->string('source')->default('file'); // file, chat
            $table->string('status')->default('needs_review'); // ready, needs_review, duplicate, skipped, imported, failed

            $table->string('cave_name_raw')->nullable();
            $table->string('entrance_name_raw')->nullable();
            $table->string('exit_name_raw')->nullable();
            $table->foreignId('cave_system_id')->nullable()->constrained('cave_systems')->nullOnDelete();
            $table->foreignId('entrance_cave_id')->nullable()->constrained('caves')->nullOnDelete();
            $table->foreignId('exit_cave_id')->nullable()->constrained('caves')->nullOnDelete();
            $table->json('cave_candidates')->nullable();

            $table->date('date')->nullable();
            $table->string('date_raw')->nullable();
            $table->string('start_time', 5)->nullable(); // HH:MM, UK local time
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('visibility')->nullable(); // null = the import's default
            $table->boolean('allow_duplicate')->default(false);

            // [{name, user_id|null, status: matched|ambiguous|guest, candidates?}]
            $table->json('participants')->nullable();
            // [{code, message, blocking}]
            $table->json('issues')->nullable();

            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->timestamps();

            $table->unique(['trip_import_id', 'row_number']);
            $table->index(['trip_import_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_import_rows');
        Schema::dropIfExists('trip_imports');
    }
};
