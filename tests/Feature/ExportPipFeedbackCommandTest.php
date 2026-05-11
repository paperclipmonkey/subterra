<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PipFeedback;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExportPipFeedbackCommandTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\Test]
    public function command_writes_thumbs_down_rows_to_markdown(): void
    {
        $user = User::factory()->create(['name' => 'Cave Carl', 'email' => 'carl@example.test']);

        PipFeedback::create([
            'user_id' => $user->id,
            'rating' => -1,
            'comment' => 'Way off',
            'transcript' => [
                ['role' => 'user', 'content' => 'Best streamway?'],
                ['role' => 'assistant', 'content' => 'Try Niagara Falls.'],
            ],
            'rated_reply' => 'Try Niagara Falls.',
        ]);

        // Thumbs-up should be excluded by default.
        PipFeedback::create([
            'user_id' => $user->id,
            'rating' => 1,
            'transcript' => [['role' => 'user', 'content' => 'Thanks!']],
            'rated_reply' => 'You bet.',
        ]);

        $path = storage_path('app/test-pip-feedback-'.uniqid().'.md');

        try {
            $this->artisan('pip:export-feedback', ['--output' => $path])
                ->assertSuccessful();

            $this->assertFileExists($path);
            $contents = File::get($path);

            $this->assertStringContainsString('# Pip Feedback Review', $contents);
            $this->assertStringContainsString('thumbs down', $contents);
            $this->assertStringContainsString('Cave Carl', $contents);
            $this->assertStringContainsString('Way off', $contents);
            $this->assertStringContainsString('Try Niagara Falls.', $contents);
            $this->assertStringNotContainsString('You bet.', $contents);
        } finally {
            File::delete($path);
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function include_positive_flag_writes_thumbs_up_rows_too(): void
    {
        $user = User::factory()->create();
        PipFeedback::create([
            'user_id' => $user->id,
            'rating' => 1,
            'transcript' => [['role' => 'assistant', 'content' => 'Great spot.']],
            'rated_reply' => 'Great spot.',
        ]);

        $path = storage_path('app/test-pip-feedback-'.uniqid().'.md');

        try {
            $this->artisan('pip:export-feedback', ['--output' => $path, '--include-positive' => true])
                ->assertSuccessful();
            $this->assertStringContainsString('Great spot.', File::get($path));
        } finally {
            File::delete($path);
        }
    }
}
