<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration
{
    /**
     * Change audits.auditable_id from unsignedBigInteger to string(255).
     *
     * The User model is auditable and now has a string primary key.  When a
     * User is created or updated the auditing package writes its string ID into
     * auditable_id, which PostgreSQL rejects because the column is bigint.
     *
     * All other auditable models (Cave, Trip, etc.) still have integer IDs;
     * their existing integer values are cast to their string representations
     * during this migration.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite uses loose typing: it already stores string values in a bigint
            // column without error.  No schema change is needed for SQLite.
            return;
        }

        // PostgreSQL: drop the composite index first, change the column type
        // with an explicit USING cast (so existing integer values become their
        // string representations), then recreate the index.
        DB::statement('DROP INDEX IF EXISTS "audits_auditable_type_auditable_id_index"');
        DB::statement(
            'ALTER TABLE "audits" ALTER COLUMN "auditable_id" TYPE VARCHAR(255) USING "auditable_id"::text'
        );
        DB::statement(
            'CREATE INDEX "audits_auditable_type_auditable_id_index" ON "audits" ("auditable_type", "auditable_id")'
        );
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS "audits_auditable_type_auditable_id_index"');
        DB::statement(
            'ALTER TABLE "audits" ALTER COLUMN "auditable_id" TYPE BIGINT USING "auditable_id"::bigint'
        );
        DB::statement(
            'CREATE INDEX "audits_auditable_type_auditable_id_index" ON "audits" ("auditable_type", "auditable_id")'
        );
    }
};
