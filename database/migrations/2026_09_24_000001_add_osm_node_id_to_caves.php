<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Link a cave to its OpenStreetMap node without taking ownership of it.
 *
 * `registry`/`registry_id` record where a cave's data came from. A cave we
 * already hold from a registry can also be mapped in OSM; recording that here
 * (rather than overwriting registry, or merging OSM data into the record) keeps
 * the ODbL-licensed OSM data cleanly separable from registry data.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('caves', function (Blueprint $table) {
            $table->string('osm_node_id', 20)->nullable()->after('registry_id');
            $table->index('osm_node_id');
        });

        // Caves the OSM sync already created are OSM-sourced: link them too.
        DB::table('caves')
            ->where('registry', 'osm')
            ->whereNotNull('registry_id')
            ->update(['osm_node_id' => DB::raw('registry_id')]);

        DB::table('tags')->updateOrInsert(
            ['tag' => 'Northern Ireland', 'category' => 'region'],
            [
                'type' => 'cave',
                'description' => 'Northern Ireland, centred on the Fermanagh uplands — home to the Marble Arch Caves and the Cuilcagh karst.',
            ]
        );
    }

    public function down(): void
    {
        DB::table('tags')->where('tag', 'Northern Ireland')->where('category', 'region')->delete();

        Schema::table('caves', function (Blueprint $table) {
            $table->dropIndex(['osm_node_id']);
            $table->dropColumn('osm_node_id');
        });
    }
};
