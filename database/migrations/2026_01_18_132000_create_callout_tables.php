<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. On-Call Shifts
        Schema::create('on_call_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->dateTime('start_at');
            $table->dateTime('end_at');
            $table->timestamps();

            // Indexes for range queries
            $table->index(['start_at', 'end_at']);
        });

        // 2. Callouts
        Schema::create('callouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Location
            $table->foreignId('trip_id')->nullable()->constrained('trips')->nullOnDelete();
            $table->foreignId('cave_id')->nullable()->constrained('caves')->nullOnDelete();
            $table->foreignId('exit_cave_id')->nullable()->constrained('caves')->nullOnDelete();
            
            // Timing
            $table->dateTime('callout_time');

            // Details
            $table->text('description'); // Fallback / Simple Description
            $table->text('trip_plan')->nullable();
            $table->string('car_details')->nullable();
            $table->text('team_details')->nullable(); // Previously medical_info

            // Emergency Contact (Now Optional/Legacy)
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            
            $table->enum('status', ['active', 'resolved', 'triggered'])->default('active');
            $table->string('aws_watchdog_id')->nullable(); 
            
            $table->timestamps();
        });

        // 3. Callout Participants
        Schema::create('callout_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('callout_id')->constrained('callouts')->onDelete('cascade');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        // 4. Incidents
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('callout_id')->unique()->constrained('callouts')->onDelete('cascade');
            $table->enum('status', ['open', 'managed', 'resolved'])->default('open');
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });

        // 5. Incident Notes
        Schema::create('incident_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users'); // The admin making the note
            $table->text('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_notes');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('callout_participants');
        Schema::dropIfExists('callouts');
        Schema::dropIfExists('on_call_shifts');
    }
};
