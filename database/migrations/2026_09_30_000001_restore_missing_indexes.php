<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Indexes to create, as [table, columns]. Names follow Laravel's default
     * `{table}_{columns}_index` convention.
     *
     * Postgres does not index foreign-key columns automatically, and
     * 2026_05_19_000002 (users.id → string) dropped every index on the old
     * integer user_id columns without restoring bookings(user_id, status)
     * or audits(user_id, user_type). bookings(user_id, status) is not
     * restored: bookings(user_id, date) below serves the same lookups.
     *
     * @return array<int, array{0: string, 1: array<int, string>}>
     */
    private function indexes(): array
    {
        return [
            // Lost in 2026_05_19_000002
            ['audits', ['user_id', 'user_type']],

            // Trip lists: filter by cave system, newest first
            ['trips', ['cave_system_id', 'start_time']],
            ['trips', ['entrance_cave_id']],
            ['trips', ['exit_cave_id']],
            ['trip_media', ['trip_id']],
            ['caves', ['cave_system_id']],
            // Trip::scopeVisibleTo club visibility check, hasApprovedClub()
            ['club_user', ['user_id', 'status']],
            // User::hasRole() on every admin-gated request
            ['role_user', ['user_id', 'role_id']],
            // "My bookings" (ordered by date) and the double-booking guard
            ['bookings', ['user_id', 'date']],

            ['callout_participants', ['callout_id']],
            ['callout_participants', ['user_id']],
            ['callouts', ['user_id', 'status']],
            ['callouts', ['trip_id']],
            ['suggested_edits', ['status', 'created_at']],
            ['suggested_edits', ['user_id']],
            ['cave_collection', ['cave_id']],
            ['routes', ['cave_system_id']],
        ];
    }

    /**
     * Plain indexes that exactly duplicate a unique index on the same columns.
     *
     * @return array<string, array{0: string, 1: array<int, string>}>
     */
    private function duplicates(): array
    {
        return [
            'trip_user_trip_id_user_id_index' => ['trip_user', ['trip_id', 'user_id']],
            'cave_tag_cave_id_tag_id_index' => ['cave_tag', ['cave_id', 'tag_id']],
            'clubs_slug_index' => ['clubs', ['slug']],
        ];
    }

    public function up(): void
    {
        foreach ($this->indexes() as [$table, $columns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                $blueprint->index($columns);
            });
        }

        // IF EXISTS: not every environment still has all of these.
        foreach (array_keys($this->duplicates()) as $name) {
            DB::statement("DROP INDEX IF EXISTS \"{$name}\"");
        }
    }

    public function down(): void
    {
        foreach ($this->duplicates() as $name => [$table, $columns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
                $blueprint->index($columns, $name);
            });
        }

        foreach (array_reverse($this->indexes()) as [$table, $columns]) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns): void {
                $blueprint->dropIndex($columns);
            });
        }
    }
};
