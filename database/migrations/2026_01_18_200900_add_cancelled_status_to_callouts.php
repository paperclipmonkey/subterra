<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: Update check constraint
            DB::statement('ALTER TABLE callouts DROP CONSTRAINT IF EXISTS callouts_status_check');
            DB::statement("ALTER TABLE callouts ADD CONSTRAINT callouts_status_check CHECK (status IN ('active', 'resolved', 'triggered', 'cancelled'))");
        }
        // SQLite doesn't support ALTER TABLE ADD CONSTRAINT, and its CHECK constraints are enforced at table level
        // For SQLite, the status column is just a text field without constraints in this context
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE callouts DROP CONSTRAINT IF EXISTS callouts_status_check');
            DB::statement("ALTER TABLE callouts ADD CONSTRAINT callouts_status_check CHECK (status IN ('active', 'resolved', 'triggered'))");
        }
    }
};
