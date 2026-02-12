<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->foreignId('incident_controller_id')->nullable()->constrained('users');
            $table->dateTime('acknowledged_at')->nullable();
            $table->string('police_log_number')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropForeign(['incident_controller_id']);
            $table->dropColumn(['incident_controller_id', 'acknowledged_at', 'police_log_number']);
        });
    }
};
