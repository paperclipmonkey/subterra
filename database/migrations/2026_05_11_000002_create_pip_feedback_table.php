<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('pip_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // -1 = thumbs down, 1 = thumbs up
            $table->tinyInteger('rating');
            $table->text('comment')->nullable();
            // Full transcript at the point of rating, including the rated reply.
            $table->json('transcript');
            // Convenience: index just the assistant reply so reviewers can scan quickly.
            $table->text('rated_reply')->nullable();
            $table->boolean('reviewed')->default(false);
            $table->timestamps();

            $table->index(['rating', 'reviewed']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pip_feedback');
    }
};
