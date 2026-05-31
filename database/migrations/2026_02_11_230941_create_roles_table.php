<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            $table->unique(['role_id', 'user_id']);
        });

        // Seed default roles
        $roles = [
            ['name' => 'Platform Admin', 'slug' => 'platform_admin', 'description' => 'Manages users, clubs, pages, communications, and platform settings.'],
            ['name' => 'Duty Officer', 'slug' => 'duty_officer', 'description' => 'Managed on-call rota and receives notifications.'],
            ['name' => 'Data Admin', 'slug' => 'data_admin', 'description' => 'Managed pages and approves data changes.'],
        ];

        foreach ($roles as $role) {
            \Illuminate\Support\Facades\DB::table('roles')->insert(array_merge($role, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        // Migrate existing admins
        $superAdminRoleId = \Illuminate\Support\Facades\DB::table('roles')->where('slug', 'platform_admin')->value('id');
        $adminUsers = \Illuminate\Support\Facades\DB::table('users')->where('is_admin', true)->get();

        foreach ($adminUsers as $user) {
            \Illuminate\Support\Facades\DB::table('role_user')->insert([
                'role_id' => $superAdminRoleId,
                'user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
