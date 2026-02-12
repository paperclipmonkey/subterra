<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing tables to avoid type conversion issues in PostgreSQL
        Schema::dropIfExists('incident_notes');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('callout_participants');
        Schema::dropIfExists('callouts');

        // Recreate Callouts
        Schema::create('callouts', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');

            // Location
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('cave_id')->nullable()->constrained('caves')->nullOnDelete();
            $table->foreignId('exit_cave_id')->nullable()->constrained('caves')->nullOnDelete();

            // Timing
            $table->dateTime('callout_time');

            // Details
            $table->text('description');
            $table->text('trip_plan')->nullable();
            $table->string('car_details')->nullable();
            $table->string('car_registration')->nullable();
            $table->string('car_parking')->nullable();
            $table->text('team_details')->nullable();

            // Emergency Contact
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            $table->enum('status', ['active', 'resolved', 'triggered', 'cancelled'])->default('active');
            $table->string('aws_watchdog_id')->nullable();

            // Snapshots & Metadata
            $table->json('location_data')->nullable();
            $table->json('request_data')->nullable();
            $table->string('cancelled_ip')->nullable();
            $table->text('cancelled_user_agent')->nullable();
            $table->string('cancelled_location')->nullable();

            $table->timestamps();
        });

        // Recreate Callout Participants
        Schema::create('callout_participants', function (Blueprint $table) {
            $table->id();
            $table->string('callout_id', 36);
            $table->foreign('callout_id')->references('id')->on('callouts')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // Recreate Incidents
        Schema::create('incidents', function (Blueprint $table) {
            $table->string('id', 36)->primary();
            $table->string('callout_id', 36)->unique();
            $table->foreign('callout_id')->references('id')->on('callouts')->onDelete('cascade');
            $table->enum('status', ['open', 'managed', 'resolved'])->default('open');
            $table->dateTime('resolved_at')->nullable();
            $table->foreignId('incident_controller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('acknowledged_at')->nullable();
            $table->string('police_log_number')->nullable();
            $table->timestamps();
        });

        // Recreate Incident Notes
        Schema::create('incident_notes', function (Blueprint $table) {
            $table->id();
            $table->string('incident_id', 36);
            $table->foreign('incident_id')->references('id')->on('incidents')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop them if needed
        Schema::dropIfExists('incident_notes');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('callout_participants');
        Schema::dropIfExists('callouts');
    }
};
