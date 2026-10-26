<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PostTooLargeReportingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_oversized_trip_upload_is_rejected_with_a_413_and_logged_as_an_error(): void
    {
        $postMaxSize = $this->postMaxSizeInBytes();
        if ($postMaxSize <= 0) {
            $this->markTestSkipped('post_max_size is unlimited in this PHP build.');
        }

        Log::spy();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->call(
            'POST',
            '/api/trips',
            server: ['CONTENT_LENGTH' => (string) ($postMaxSize + 1), 'HTTP_ACCEPT' => 'application/json'],
        );

        $response->assertStatus(413);

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(fn (string $message, array $context) => $message === 'Request rejected: body larger than post_max_size'
                && $context['method'] === 'POST'
                && $context['path'] === 'api/trips'
                && $context['content_length'] === $postMaxSize + 1);
    }

    #[Test]
    public function other_client_errors_are_still_not_logged(): void
    {
        Log::spy();

        $this->getJson('/api/trips')->assertUnauthorized();

        Log::shouldNotHaveReceived('error');
    }

    private function postMaxSizeInBytes(): int
    {
        $value = (string) ini_get('post_max_size');
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'K' => $number * 1024,
            'M' => $number * 1024 ** 2,
            'G' => $number * 1024 ** 3,
            default => $number,
        };
    }
}
