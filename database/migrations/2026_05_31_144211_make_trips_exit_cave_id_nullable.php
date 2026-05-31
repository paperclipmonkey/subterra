<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // No-op: exit_cave_id remains NOT NULL; tools default to entrance_cave_id.
    }

    public function down(): void
    {
        // No-op
    }
};
