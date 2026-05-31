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
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_trophies')->default(true)->after('privacy_policy_agreed_at');
            $table->boolean('email_tagged')->default(true)->after('email_trophies');
            $table->boolean('email_platform_news')->default(true)->after('email_tagged');
            $table->string('visibility_addable_to_trips')->default('public')->after('email_platform_news');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_trophies',
                'email_tagged',
                'email_platform_news',
                'visibility_addable_to_trips',
            ]);
        });
    }
};
