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
        Schema::table('callouts', function (Blueprint $table) {
            $table->string('car_registration')->nullable()->after('car_details');
            $table->string('car_parking')->nullable()->after('car_registration');
            $table->json('location_data')->nullable()->after('car_parking');
            $table->json('request_data')->nullable()->after('location_data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('callouts', function (Blueprint $table) {
            $table->dropColumn(['car_registration', 'car_parking', 'location_data', 'request_data']);
        });
    }
};
