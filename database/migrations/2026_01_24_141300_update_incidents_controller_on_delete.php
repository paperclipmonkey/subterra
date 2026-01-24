<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            // Drop old foreign key
            $table->dropForeign(['incident_controller_id']);
            
            // Re-add with onDelete('set null')
            $table->foreign('incident_controller_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign(['incident_controller_id']);
            
            $table->foreign('incident_controller_id')
                  ->references('id')
                  ->on('users');
        });
    }
};
