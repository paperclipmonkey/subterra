<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class () extends Migration {
    /**
     * Change users.id from an auto-incrementing integer to a random 7-character
     * string, and update every table that holds a foreign key reference to users.id.
     *
     * Using a random string as the primary key prevents sequential-ID enumeration
     * while keeping all existing code that accesses ->id unchanged.
     */

    /** @return array<int, array<string, mixed>> */
    private function fkTables(): array
    {
        return [
            // table, column, constraint name, ON DELETE rule, nullable
            ['table' => 'bookings',            'column' => 'user_id',                'constraint' => 'bookings_user_id_foreign',                   'delete' => 'CASCADE',  'nullable' => true],
            ['table' => 'bookings',            'column' => 'approved_by',            'constraint' => 'bookings_approved_by_foreign',               'delete' => 'SET NULL', 'nullable' => true],
            ['table' => 'callout_participants', 'column' => 'user_id',               'constraint' => 'callout_participants_user_id_foreign',        'delete' => 'SET NULL', 'nullable' => true],
            ['table' => 'callouts',            'column' => 'user_id',                'constraint' => 'callouts_user_id_foreign',                   'delete' => 'CASCADE',  'nullable' => false],
            ['table' => 'club_user',           'column' => 'user_id',                'constraint' => 'club_user_user_id_foreign',                  'delete' => 'CASCADE',  'nullable' => false],
            ['table' => 'collections',         'column' => 'user_id',                'constraint' => 'collections_user_id_foreign',                'delete' => 'CASCADE',  'nullable' => false],
            ['table' => 'incident_notes',      'column' => 'user_id',                'constraint' => 'incident_notes_user_id_foreign',             'delete' => 'SET NULL', 'nullable' => true],
            ['table' => 'incidents',           'column' => 'incident_controller_id', 'constraint' => 'incidents_incident_controller_id_foreign',   'delete' => 'SET NULL', 'nullable' => true],
            ['table' => 'medal_user',          'column' => 'user_id',                'constraint' => 'medal_user_user_id_foreign',                 'delete' => 'CASCADE',  'nullable' => false],
            ['table' => 'on_call_shifts',      'column' => 'user_id',                'constraint' => 'on_call_shifts_user_id_foreign',             'delete' => 'CASCADE',  'nullable' => false],
            ['table' => 'pages',               'column' => 'user_id',                'constraint' => 'pages_user_id_foreign',                      'delete' => 'SET NULL', 'nullable' => true],
            ['table' => 'permit_user',         'column' => 'user_id',                'constraint' => 'permit_user_user_id_foreign',                'delete' => 'CASCADE',  'nullable' => false],
            ['table' => 'permits',             'column' => 'created_by',             'constraint' => 'permits_created_by_foreign',                 'delete' => 'CASCADE',  'nullable' => false],
            ['table' => 'pip_feedback',        'column' => 'user_id',                'constraint' => 'pip_feedback_user_id_foreign',               'delete' => 'SET NULL', 'nullable' => true],
            ['table' => 'role_user',           'column' => 'user_id',                'constraint' => 'role_user_user_id_foreign',                  'delete' => 'CASCADE',  'nullable' => false],
            ['table' => 'suggested_edits',     'column' => 'user_id',                'constraint' => 'suggested_edits_user_id_foreign',            'delete' => 'CASCADE',  'nullable' => true],
            ['table' => 'trip_user',           'column' => 'user_id',                'constraint' => 'trip_user_user_id_foreign',                  'delete' => 'CASCADE',  'nullable' => false],
        ];
    }

    public function up(): void
    {
        $fkTables = $this->fkTables();
        $mapping = $this->buildIdMapping();

        $this->addNewColumns($mapping, $fkTables);

        if (DB::getDriverName() === 'sqlite') {
            $this->applySqlite($fkTables);
        } else {
            $this->applyPostgres($fkTables);
        }
    }

    // ── Shared steps (DB-agnostic) ────────────────────────────────────────────

    /**
     * Build the old-integer-ID → new-string-ID mapping for all existing users.
     *
     * @return array<int, string>
     */
    private function buildIdMapping(): array
    {
        $mapping = [];
        DB::table('users')->orderBy('id')->each(function (object $user) use (&$mapping): void {
            $mapping[$user->id] = Str::random(7);
        });

        return $mapping;
    }

    /**
     * Add the new string columns alongside the old integer ones and populate
     * them using the ID mapping.  This step is identical for both databases.
     *
     * @param array<int, string>         $mapping
     * @param array<int, array<string, mixed>> $fkTables
     */
    private function addNewColumns(array $mapping, array $fkTables): void
    {
        // users: add _new_id next to the existing integer id
        Schema::table('users', function (Blueprint $table): void {
            $table->string('_new_id', 7)->nullable()->after('id');
        });
        $this->populateMappedColumn('users', 'id', '_new_id', $mapping);

        // FK tables: add _n_{column} and populate from the mapping
        foreach ($fkTables as $fk) {
            $tmp = '_n_'.$fk['column'];
            Schema::table($fk['table'], function (Blueprint $table) use ($tmp): void {
                $table->string($tmp, 7)->nullable();
            });
            $this->populateMappedColumn($fk['table'], $fk['column'], $tmp, $mapping);
        }

        // audits.user_id has no FK constraint — handled separately
        Schema::table('audits', function (Blueprint $table): void {
            $table->string('_n_user_id', 7)->nullable();
        });
        $this->populateMappedColumn('audits', 'user_id', '_n_user_id', $mapping);

        // api_interactions.trackable_id is polymorphic (no FK constraint).
        // User-type records get the new string ID; all other records get their
        // existing integer value cast to a string.
        Schema::table('api_interactions', function (Blueprint $table): void {
            $table->string('_n_trackable_id', 255)->nullable();
        });
        $this->populateMappedColumn('api_interactions', 'trackable_id', '_n_trackable_id', $mapping, "trackable_type = 'App\\Models\\User'");
        DB::statement(
            'UPDATE "api_interactions" SET "_n_trackable_id" = CAST("trackable_id" AS TEXT)'.
                ' WHERE "trackable_type" != ? OR "trackable_type" IS NULL',
            ['App\Models\User']
        );
    }

    /**
     * Rename the _n_{column} temp columns to their final names.
     * Called from both applySqlite() and applyPostgres() after the old columns
     * have been removed.
     *
     * @param array<int, array<string, mixed>> $fkTables
     */
    private function renameNewColumns(array $fkTables): void
    {
        foreach ($fkTables as $fk) {
            $tmp = '_n_'.$fk['column'];
            Schema::table($fk['table'], function (Blueprint $table) use ($tmp, $fk): void {
                $table->renameColumn($tmp, $fk['column']);
            });
        }
        Schema::table('audits', function (Blueprint $table): void {
            $table->renameColumn('_n_user_id', 'user_id');
        });
        Schema::table('api_interactions', function (Blueprint $table): void {
            $table->renameColumn('_n_trackable_id', 'trackable_id');
        });
    }

    /**
     * Batch-update a column using a single CASE WHEN statement rather than one
     * UPDATE per row.  Drastically reduces query count for large tables.
     *
     * @param array<int, string> $mapping       [old_id => new_id]
     * @param string|null        $whereExtra    Optional extra WHERE condition (unparameterised)
     */
    private function populateMappedColumn(
        string $table,
        string $fromCol,
        string $toCol,
        array $mapping,
        ?string $whereExtra = null,
    ): void {
        if (empty($mapping)) {
            return;
        }

        $cases = implode(' ', array_fill(0, count($mapping), 'WHEN ? THEN ?'));
        $placeholders = implode(', ', array_fill(0, count($mapping), '?'));
        $caseBindings = collect($mapping)->flatMap(fn ($v, $k) => [$k, $v])->values()->all();
        $whereIn = array_keys($mapping);

        $where = "\"$fromCol\" IN ($placeholders)";
        if ($whereExtra !== null) {
            $where = "($whereExtra) AND $where";
        }

        DB::statement(
            "UPDATE \"$table\" SET \"$toCol\" = CASE \"$fromCol\" $cases END WHERE $where",
            array_merge($caseBindings, $whereIn)
        );
    }

    // ── SQLite path ───────────────────────────────────────────────────────────

    /**
     * Complete the migration on SQLite.
     *
     * SQLite >= 3.35 native ALTER TABLE DROP COLUMN refuses to drop any column
     * referenced by a FK constraint or an index, so we use a full table-swap
     * rebuild (dropColumnSqlite) for every affected column.
     *
     * @param array<int, array<string, mixed>> $fkTables
     */
    private function applySqlite(array $fkTables): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        // Drop the old integer columns via table-swap.  Pass the temp column as
        // $redirectFkTo so FK constraints are preserved and automatically updated
        // by SQLite's native RENAME COLUMN in renameNewColumns().
        foreach ($fkTables as $fk) {
            $this->dropColumnSqlite($fk['table'], $fk['column'], '_n_'.$fk['column']);
        }
        $this->dropColumnSqlite('audits', 'user_id');
        $this->dropColumnSqlite('api_interactions', 'trackable_id');

        $this->renameNewColumns($fkTables);

        // Restore NOT NULL on columns that were non-nullable before.
        foreach ($fkTables as $fk) {
            if (!$fk['nullable']) {
                Schema::table($fk['table'], function (Blueprint $table) use ($fk): void {
                    $table->string($fk['column'], 7)->nullable(false)->change();
                });
            }
        }

        // Replace users.id: drop the integer PK column, rename _new_id, set NOT NULL + PK
        $this->dropColumnSqlite('users', 'id');
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('_new_id', 'id');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('id', 7)->nullable(false)->primary()->change();
        });

        DB::statement('PRAGMA foreign_keys = ON');
        // Force a WAL checkpoint so all changes land in the main database file.
        // Without this, subsequent test-suite migrate:fresh runs can see the WAL
        // in an un-checkpointed state and report "database disk image is malformed".
        DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
    }

    // ── PostgreSQL path ───────────────────────────────────────────────────────

    /**
     * Complete the migration on PostgreSQL.
     *
     * @param array<int, array<string, mixed>> $fkTables
     */
    private function applyPostgres(array $fkTables): void
    {
        // Drop FK constraints, the users PK, and indexes that reference the
        // columns being removed.
        foreach ($fkTables as $fk) {
            DB::statement("ALTER TABLE \"{$fk['table']}\" DROP CONSTRAINT IF EXISTS \"{$fk['constraint']}\"");
        }
        DB::statement('ALTER TABLE "users" DROP CONSTRAINT IF EXISTS "users_pkey"');
        DB::statement('DROP SEQUENCE IF EXISTS "users_id_seq" CASCADE');
        DB::statement('DROP INDEX IF EXISTS "api_interactions_trackable_type_trackable_id_created_at_index"');

        // Drop the old integer columns
        foreach ($fkTables as $fk) {
            Schema::table($fk['table'], function (Blueprint $table) use ($fk): void {
                $table->dropColumn($fk['column']);
            });
        }
        Schema::table('audits', function (Blueprint $table): void {
            $table->dropColumn('user_id');
        });
        Schema::table('api_interactions', function (Blueprint $table): void {
            $table->dropColumn('trackable_id');
        });

        $this->renameNewColumns($fkTables);

        // Promote users._new_id to the primary key
        DB::statement('ALTER TABLE "users" DROP COLUMN "id"');
        DB::statement('ALTER TABLE "users" RENAME COLUMN "_new_id" TO "id"');
        DB::statement('ALTER TABLE "users" ALTER COLUMN "id" SET NOT NULL');
        DB::statement('ALTER TABLE "users" ADD PRIMARY KEY ("id")');

        // Enforce NOT NULL on columns that were non-nullable before
        foreach ($fkTables as $fk) {
            if (!$fk['nullable']) {
                DB::statement("ALTER TABLE \"{$fk['table']}\" ALTER COLUMN \"{$fk['column']}\" SET NOT NULL");
            }
        }

        // Re-add FK constraints with their original ON DELETE behaviour
        foreach ($fkTables as $fk) {
            $onDelete = $fk['delete'] === 'SET NULL' ? 'ON DELETE SET NULL' : 'ON DELETE CASCADE';
            DB::statement(
                "ALTER TABLE \"{$fk['table']}\" ADD CONSTRAINT \"{$fk['constraint']}\" ".
                    "FOREIGN KEY (\"{$fk['column']}\") REFERENCES \"users\" (\"id\") {$onDelete}"
            );
        }

        // Restore composite indexes that were dropped with the old integer columns
        DB::statement('CREATE UNIQUE INDEX "club_user_club_id_user_id_unique" ON "club_user" ("club_id", "user_id")');
        DB::statement('CREATE UNIQUE INDEX "medal_user_user_id_medal_id_unique" ON "medal_user" ("user_id", "medal_id")');
        DB::statement('CREATE UNIQUE INDEX "permit_user_permit_id_user_id_unique" ON "permit_user" ("permit_id", "user_id")');
        DB::statement('CREATE UNIQUE INDEX "role_user_role_id_user_id_unique" ON "role_user" ("role_id", "user_id")');
        DB::statement('CREATE INDEX "trip_user_trip_id_user_id_index" ON "trip_user" ("trip_id", "user_id")');
        DB::statement('CREATE INDEX "trip_user_user_id_trip_id_index" ON "trip_user" ("user_id", "trip_id")');
        DB::statement('CREATE INDEX "api_interactions_trackable_type_trackable_id_created_at_index" ON "api_interactions" ("trackable_type", "trackable_id", "created_at")');
    }

    // ── SQLite helpers ────────────────────────────────────────────────────────

    /**
     * Drop a column from a SQLite table via a full table-swap rebuild.
     *
     * SQLite >= 3.35 native ALTER TABLE DROP COLUMN refuses to drop any column
     * that is referenced by a FK constraint or appears in an index.  This
     * helper avoids those limitations by:
     *   1. Reading the current schema via PRAGMA table_info / foreign_key_list.
     *   2. Creating a temp table with the same schema minus the dropped column
     *      and minus any FK/index definitions that reference it.
     *   3. Copying all rows, then swapping old → new table.
     *
     * Pass $redirectFkTo to redirect FK constraints on $column to a different
     * (already existing) column rather than dropping them entirely.  Useful
     * when the old column is being replaced by a renamed temp column: the FK
     * will then be automatically updated by SQLite's native RENAME COLUMN.
     */
    private function dropColumnSqlite(string $table, string $column, ?string $redirectFkTo = null): void
    {
        // ── Column info ──────────────────────────────────────────────────────
        $cols = DB::select("PRAGMA table_info(\"$table\")");
        $keepCols = array_values(array_filter($cols, fn ($c) => $c->name !== $column));

        // ── Index info: keep indexes that do NOT reference the dropped column ─
        $rawIndexes = DB::select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'index' AND tbl_name = ? AND sql IS NOT NULL",
            [$table]
        );
        $keepIndexSql = [];
        foreach ($rawIndexes as $idx) {
            $idxCols = collect(DB::select("PRAGMA index_info(\"$idx->name\")"))->pluck('name');
            if (!$idxCols->contains($column)) {
                $keepIndexSql[] = $idx->sql;
            }
        }

        // ── FK info: keep/redirect FKs, dropping only those with no redirect ──
        $fkRows = DB::select("PRAGMA foreign_key_list(\"$table\")");
        $keepFkDefs = [];
        foreach (collect($fkRows)->groupBy('id') as $fkGroup) {
            $fromCols = $fkGroup->pluck('from');

            if ($fromCols->contains($column)) {
                if ($redirectFkTo === null) {
                    continue; // Drop the FK entirely
                }
                // Redirect: replace the old column name with the replacement
                $fromCols = $fromCols->map(fn ($c) => $c === $column ? $redirectFkTo : $c);
            }

            $fromStr = '"'.$fromCols->join('", "').'"';
            $toStr = '"'.$fkGroup->pluck('to')->join('", "').'"';
            $onDelete = strtoupper($fkGroup->first()->on_delete ?? '');
            $fkDef = "FOREIGN KEY ($fromStr) REFERENCES \"{$fkGroup->first()->table}\" ($toStr)";
            if ($onDelete && $onDelete !== 'NO ACTION') {
                $fkDef .= " ON DELETE $onDelete";
            }
            $keepFkDefs[] = $fkDef;
        }

        // ── Build the new table schema ────────────────────────────────────────
        $colDefs = [];
        foreach ($keepCols as $c) {
            $def = '"'.$c->name.'" '.($c->type ?: 'text');
            if ($c->pk) {
                $def .= ' PRIMARY KEY';
            }
            if ($c->notnull && !$c->pk) {
                $def .= ' NOT NULL';
            }
            if ($c->dflt_value !== null) {
                $def .= ' DEFAULT '.$c->dflt_value;
            }
            $colDefs[] = $def;
        }

        // ── Table swap ───────────────────────────────────────────────────────
        $tmp = '__mig_'.$table;
        $colList = implode(', ', array_map(fn ($c) => '"'.$c->name.'"', $keepCols));
        DB::statement(sprintf(
            'CREATE TABLE "%s" (%s)',
            $tmp,
            implode(', ', array_merge($colDefs, $keepFkDefs))
        ));
        DB::statement("INSERT INTO \"$tmp\" ($colList) SELECT $colList FROM \"$table\"");
        DB::statement("DROP TABLE \"$table\"");
        DB::statement("ALTER TABLE \"$tmp\" RENAME TO \"$table\"");

        // ── Recreate surviving indexes ────────────────────────────────────────
        foreach ($keepIndexSql as $sql) {
            DB::statement($sql);
        }
    }

    /**
     * This migration cannot be automatically reversed: random string IDs
     * cannot be converted back to their original sequential integers.
     */
    public function down(): void
    {
        throw new \RuntimeException(
            'This migration cannot be reversed. Restore from a database backup if needed.'
        );
    }
};
