<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('pip_agreement_signed_at')->nullable()->after('onboarding_completed_at');
        });

        DB::table('roles')->updateOrInsert(
            ['slug' => 'pip_access'],
            [
                'name' => 'Pip Access',
                'description' => 'Allowed to use the Pip AI assistant.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pip_agreement_signed_at');
        });

        DB::table('roles')->where('slug', 'pip_access')->delete();
    }
};
