<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks the automated voice-call rung of the escalation ladder, so the scheduler
     * knows when it last called and how many attempts it has made for an incident.
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->timestamp('last_voice_call_at')->nullable()->after('escalated_at');
            $table->unsignedInteger('voice_call_count')->default(0)->after('last_voice_call_at');
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['last_voice_call_at', 'voice_call_count']);
        });
    }
};
