<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add the uuid column to the trips table
        Schema::table('trips', function (Blueprint $table) {
            $table->uuid('uuid')->after('id')->unique();
        });

        // Generate UUIDs for existing trips
        DB::table('trips')->get()->each(function ($trip) {
            DB::table('trips')
                ->where('id', $trip->id)
                ->update(['uuid' => Str::uuid()->toString()]);
        });

        // Add uuid column to trip_user pivot table
        Schema::table('trip_user', function (Blueprint $table) {
            $table->uuid('trip_uuid')->after('trip_id');
        });

        // Populate trip_uuid in pivot table based on existing trip_id relationships
        DB::statement('
            UPDATE trip_user 
            SET trip_uuid = (
                SELECT uuid 
                FROM trips 
                WHERE trips.id = trip_user.trip_id
            )
        ');

        // Add uuid column to trip_media table if it exists
        if (Schema::hasTable('trip_media')) {
            Schema::table('trip_media', function (Blueprint $table) {
                $table->uuid('trip_uuid')->after('trip_id');
            });

            // Populate trip_uuid in trip_media table
            DB::statement('
                UPDATE trip_media 
                SET trip_uuid = (
                    SELECT uuid 
                    FROM trips 
                    WHERE trips.id = trip_media.trip_id
                )
            ');
        }

        // Now we'll modify the tables to use UUID as primary key
        // First drop foreign key constraints
        Schema::table('trip_user', function (Blueprint $table) {
            $table->dropForeign(['trip_id']);
        });

        if (Schema::hasTable('trip_media')) {
            Schema::table('trip_media', function (Blueprint $table) {
                $table->dropForeign(['trip_id']);
            });
        }

        // Drop the old trip_id columns and rename uuid columns
        Schema::table('trip_user', function (Blueprint $table) {
            $table->dropColumn('trip_id');
            $table->renameColumn('trip_uuid', 'trip_id');
        });

        if (Schema::hasTable('trip_media')) {
            Schema::table('trip_media', function (Blueprint $table) {
                $table->dropColumn('trip_id');
                $table->renameColumn('trip_uuid', 'trip_id');
            });
        }

        // In the trips table, drop the old id and rename uuid to id
        Schema::table('trips', function (Blueprint $table) {
            $table->dropPrimary(['id']);
            $table->dropColumn('id');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->renameColumn('uuid', 'id');
            $table->primary('id');
        });

        // Re-add foreign key constraints
        Schema::table('trip_user', function (Blueprint $table) {
            $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
        });

        if (Schema::hasTable('trip_media')) {
            Schema::table('trip_media', function (Blueprint $table) {
                $table->foreign('trip_id')->references('id')->on('trips')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This is a destructive migration - we cannot reverse it without data loss
        // because we cannot recreate the original auto-incrementing IDs
        throw new \Exception('This migration cannot be reversed as it would result in data loss.');
    }
};