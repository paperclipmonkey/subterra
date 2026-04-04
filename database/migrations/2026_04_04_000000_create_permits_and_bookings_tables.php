<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('permits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('conditions')->nullable();
            $table->boolean('has_max_groups_per_day')->default(false);
            $table->unsignedInteger('max_groups_per_day')->nullable();
            $table->boolean('auto_approve')->default(false);
            $table->text('booking_info')->nullable()->comment('Sent to applicant on approval (e.g. key codes, meeting points)');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        Schema::create('cave_permit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cave_id')->constrained()->onDelete('cascade');
            $table->foreignId('permit_id')->constrained()->onDelete('cascade');
            $table->unique('cave_id');
        });

        Schema::create('permit_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('permit_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['permit_id', 'user_id']);
        });

        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('short_id', 8)->unique();
            $table->foreignId('permit_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->date('date');
            $table->unsignedInteger('participants')->default(1);
            $table->string('status')->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('conditions_accepted_at');
            $table->timestamps();

            $table->index(['permit_id', 'date', 'status']);
            $table->index(['user_id', 'status']);
        });

        // Seed the access_officer role
        \Illuminate\Support\Facades\DB::table('roles')->insert([
            'name' => 'Access Officer',
            'slug' => 'access_officer',
            'description' => 'Manages cave access permits and approves booking applications.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('permit_user');
        Schema::dropIfExists('cave_permit');
        Schema::dropIfExists('permits');

        \Illuminate\Support\Facades\DB::table('roles')->where('slug', 'access_officer')->delete();
    }
};
