<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Tracks outbound SMS so we can follow delivery via the provider's status callbacks
 * (currently Twilio). Deliberately stores only a MASKED recipient number — the provider
 * message SID is what status callbacks match on, so the full number isn't needed here.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->default('twilio');
            // Provider message id (Twilio SID). Null if the send was rejected outright.
            $table->string('provider_sid')->nullable()->unique();
            $table->string('to_masked')->nullable();
            $table->string('recipient_name')->nullable();
            // users.id / callouts.id / incidents.id are random strings, so these are
            // plain indexed columns rather than typed foreign keys.
            $table->string('user_id')->nullable()->index();
            $table->string('callout_id')->nullable()->index();
            $table->string('incident_id')->nullable()->index();
            // A short label for what this message was, e.g. "overdue_do", "imminent_participant".
            $table->string('context')->nullable();
            // Lifecycle: queued → sent → delivered, or undelivered/failed/rejected.
            $table->string('status')->default('queued')->index();
            $table->string('error_code')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
