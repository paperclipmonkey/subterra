<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Phone-number verification: users confirm their number by entering a code we SMS them.
 * A verified number is required to create a callout or be on the duty-officer rota, so the
 * safety-critical contact data is known-good.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            // The most recent verification code (hashed) + when it was sent, and how many
            // confirm attempts have been made against it (to throttle guessing).
            $table->string('phone_verification_code')->nullable()->after('phone_verified_at');
            $table->timestamp('phone_verification_sent_at')->nullable()->after('phone_verification_code');
            $table->unsignedTinyInteger('phone_verification_attempts')->default(0)->after('phone_verification_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verified_at',
                'phone_verification_code',
                'phone_verification_sent_at',
                'phone_verification_attempts',
            ]);
        });
    }
};
