<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('on_call_shifts', function (Blueprint $table) {
            $table->boolean('notify_do')->default(false)->after('notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('on_call_shifts', function (Blueprint $table) {
            $table->dropColumn('notify_do');
        });
    }
};
