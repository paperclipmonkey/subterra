<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add short_id column to trips table as nullable first
        Schema::table('trips', function (Blueprint $table) {
            $table->string('short_id', 10)->after('id')->nullable();
        });

        // Generate short IDs for existing trips
        $trips = DB::table('trips')->select('id')->get();
        foreach ($trips as $trip) {
            DB::table('trips')
                ->where('id', $trip->id)
                ->update(['short_id' => $this->generateShortId()]);
        }

        // Apply Not Null and Unique constraints
        Schema::table('trips', function (Blueprint $table) {
            $table->string('short_id', 10)->nullable(false)->change();
            $table->unique('short_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('short_id');
        });
    }

    /**
     * Generate a unique short ID.
     */
    private function generateShortId(): string
    {
        $attempts = 0;
        $maxAttempts = 10;

        do {
            $shortId = $this->generateRandomString(8);
            ++$attempts;

            $exists = DB::table('trips')->where('short_id', $shortId)->exists();

            if (!$exists) {
                return $shortId;
            }
        } while ($attempts < $maxAttempts);

        // Fallback to a longer ID
        return $this->generateRandomString(10);
    }

    /**
     * Generate a random alphanumeric string.
     */
    private function generateRandomString(int $length): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $string = '';

        for ($i = 0; $i < $length; ++$i) {
            $string .= $characters[random_int(0, strlen($characters) - 1)];
        }

        return $string;
    }
};
