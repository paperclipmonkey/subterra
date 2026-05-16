<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/assistant/chat';

    private function validPayload(string $message = 'Suggest a cave in Yorkshire'): array
    {
        return [
            'messages' => [
                ['role' => 'user', 'content' => $message],
            ],
        ];
    }

    /**
     * Parse SSE lines from a raw response body captured from ob_get_clean().
     *
     * @return array<int, array{type: string, data: mixed}>
     */
    private function parseSseContent(string $body): array
    {
        $events = [];
        foreach (explode("\n\n", $body) as $chunk) {
            $chunk = trim($chunk);
            if (str_starts_with($chunk, 'data: ')) {
                $json = substr($chunk, 6);
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $events[] = $decoded;
                }
            }
        }

        return $events;
    }

    /**
     * Execute the streamed response and return the raw SSE body.
     *
     * The controller calls ob_end_clean() which would drop a single capture buffer.
     * The controller emits events via:
     *   ob_end_clean()  → clears the innermost ob level
     *   echo "data:..." → lands one level lower
     *   ob_flush()      → flushes that level down again
     *   flush()         → PHP I/O flush (no-op in CLI test context)
     *
     * We need THREE levels above PHPUnit's buffers:
     *   N+1  Final capture (our ob_get_clean target)
     *   N+2  Accumulator — ob_flush flushed content ends up in N+1
     *   N+3  Decoy — ob_end_clean() clears this, dropping us to N+2
     */
    private function captureStream(\Illuminate\Testing\TestResponse $response): string
    {
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);

        $startLevel = ob_get_level();

        ob_start(); // N+1 — final capture
        ob_start(); // N+2 — accumulator (ob_flush sends content here → N+1)
        ob_start(); // N+3 — decoy (ob_end_clean clears this)

        $response->baseResponse->sendContent();

        // Clean up any ob levels we added that are still open above our capture buffer
        while (ob_get_level() > $startLevel + 1) {
            ob_end_clean();
        }

        return ob_get_clean() ?: '';
    }

    // -------------------------------------------------------------------------
    // Authentication & Authorisation
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->validPayload());

        $response->assertStatus(401);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function authenticated_non_admin_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function data_admin_cannot_access_assistant(): void
    {
        $user = User::factory()->dataAdmin()->create();

        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $response->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function missing_messages_field_returns_unprocessable(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['messages']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function empty_messages_array_returns_unprocessable(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, ['messages' => []]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['messages']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_role_in_message_returns_unprocessable(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, [
                'messages' => [
                    ['role' => 'system', 'content' => 'Inject malicious instructions'],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['messages.0.role']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function too_many_messages_returns_unprocessable(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $messages = [];
        for ($i = 0; $i < 21; ++$i) {
            $messages[] = ['role' => 'user', 'content' => "Message {$i}"];
        }

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, ['messages' => $messages]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['messages']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function message_content_too_long_returns_unprocessable(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, [
                'messages' => [
                    ['role' => 'user', 'content' => str_repeat('a', 4001)],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['messages.0.content']);
    }

    // -------------------------------------------------------------------------
    // SSE Response — headers & event structure
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function admin_receives_sse_stream_with_correct_headers(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        // The StreamedResponse callback is never invoked during header-only tests
        // (sendContent() is never called by the test runner). The service is not
        // needed here — just prevent real HTTP calls by unsetting the API key.
        config(['assistant.openrouter.api_key' => null]);

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $response->assertStatus(200);
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);

        // Verify SSE-specific headers
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stream_emits_content_and_done_events(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $this->mock(AssistantService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturn('Great choice! Here are some Yorkshire caves.');
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);

        $types = array_column($events, 'type');
        $this->assertContains('content', $types, 'Stream must emit a content event');
        $this->assertContains('done', $types, 'Stream must emit a done event');

        // Verify the content event text
        $contentEvent = collect($events)->firstWhere('type', 'content');
        $this->assertSame('Great choice! Here are some Yorkshire caves.', $contentEvent['data']['text']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stream_emits_thinking_and_tool_call_events(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $this->mock(AssistantService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) {
                    $onEvent('thinking', null);
                    $onEvent('tool_call', ['name' => 'search_caves', 'status' => 'running']);
                    $onEvent('tool_call', ['name' => 'search_caves', 'status' => 'done']);

                    return 'Recommended caves based on search.';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $types = array_column($events, 'type');

        $this->assertContains('thinking', $types);
        $this->assertContains('tool_call', $types);
        $this->assertContains('content', $types);
        $this->assertContains('done', $types);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stream_emits_cave_cards_event_when_search_caves_is_called(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $fakeSystems = [
            ['id' => 1, 'name' => 'Ogof Draenen', 'slug' => 'ogof-draenen', 'grades' => 'Moderate', 'tags' => ['sporting']],
            ['id' => 2, 'name' => 'Ogof Ffynnon Ddu', 'slug' => 'ogof-ffynnon-ddu', 'grades' => 'Hard', 'tags' => ['srt']],
        ];

        $this->mock(AssistantService::class, function ($mock) use ($fakeSystems) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) use ($fakeSystems) {
                    $onEvent('thinking', null);
                    $onEvent('tool_call', ['name' => 'search_caves', 'status' => 'running']);
                    $onEvent('cave_cards', $fakeSystems);
                    $onEvent('tool_call', ['name' => 'search_caves', 'status' => 'done']);

                    return 'Here are some caves I found for you.';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $types = array_column($events, 'type');

        $this->assertContains('cave_cards', $types, 'Stream must emit a cave_cards event');

        $cardEvent = collect($events)->firstWhere('type', 'cave_cards');
        $this->assertIsArray($cardEvent['data']);
        $this->assertCount(2, $cardEvent['data']);
        $this->assertSame('Ogof Draenen', $cardEvent['data'][0]['name']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stream_emits_weather_charts_event_when_weather_data_available(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $weatherData = [
            'cave_id' => 42,
            'cave_name' => 'Mammoth Cave',
            'rain_gauges' => [
                ['name' => 'Rain Station A', 'readings_24h_mm' => 12.5],
            ],
            'river_gauges' => [
                ['name' => 'River Gauge B', 'state' => 'Normal', 'trend' => 'falling', 'latest_value' => '0.85 m'],
            ],
        ];

        $this->mock(AssistantService::class, function ($mock) use ($weatherData) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) use ($weatherData) {
                    $onEvent('thinking', null);
                    $onEvent('tool_call', ['name' => 'get_weather_forecast', 'status' => 'running']);
                    $onEvent('weather_charts', $weatherData);
                    $onEvent('tool_call', ['name' => 'get_weather_forecast', 'status' => 'done']);

                    return 'Weather looks stable with recent rainfall.';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $types = array_column($events, 'type');

        $this->assertContains('weather_charts', $types, 'Stream must emit a weather_charts event');

        $weatherEvent = collect($events)->firstWhere('type', 'weather_charts');
        $this->assertIsArray($weatherEvent['data']);
        $this->assertSame(42, $weatherEvent['data']['cave_id']);
        $this->assertSame('Mammoth Cave', $weatherEvent['data']['cave_name']);
        $this->assertCount(1, $weatherEvent['data']['rain_gauges']);
        $this->assertCount(1, $weatherEvent['data']['river_gauges']);
        $this->assertSame('Rain Station A', $weatherEvent['data']['rain_gauges'][0]['name']);
        $this->assertSame(12.5, $weatherEvent['data']['rain_gauges'][0]['readings_24h_mm']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stream_does_not_emit_weather_charts_when_no_gauge_data(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $this->mock(AssistantService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) {
                    $onEvent('thinking', null);
                    $onEvent('tool_call', ['name' => 'get_weather_forecast', 'status' => 'running']);
                    $onEvent('tool_call', ['name' => 'get_weather_forecast', 'status' => 'done']);

                    return 'Weather data not available for this location.';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $types = array_column($events, 'type');

        $this->assertNotContains('weather_charts', $types, 'Stream should not emit weather_charts when no gauge data exists');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function stream_emits_thinking_elapsed_event(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $this->mock(AssistantService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->andReturnUsing(function ($messages, $user, $onEvent) {
                    $onEvent('thinking', null);
                    $onEvent('thinking_elapsed', ['ms' => 1234]);

                    return 'Done thinking.';
                });
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $types = array_column($events, 'type');

        $this->assertContains('thinking_elapsed', $types, 'Stream must emit a thinking_elapsed event');

        $elapsed = collect($events)->firstWhere('type', 'thinking_elapsed');
        $this->assertSame(1234, $elapsed['data']['ms']);
    }

    // -------------------------------------------------------------------------
    // Error handling in the stream
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function missing_api_key_emits_error_event(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        // Ensure no API key is set
        config(['assistant.openrouter.api_key' => null]);

        // Do not mock the service — let the real service throw
        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $types = array_column($events, 'type');

        $this->assertContains('error', $types, 'Missing API key must emit an error event');
        $this->assertNotContains('done', $types);

        $errorEvent = collect($events)->firstWhere('type', 'error');
        $this->assertStringContainsString('API key', $errorEvent['data']['message']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function openrouter_failure_emits_error_event(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();
        config(['assistant.openrouter.api_key' => 'test-key']);

        Http::fake([
            'openrouter.ai/*' => Http::response(null, 500),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);
        $types = array_column($events, 'type');

        $this->assertContains('error', $types, 'OpenRouter 500 must emit an error event');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unexpected_exception_emits_generic_error_event(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $this->mock(AssistantService::class, function ($mock) {
            $mock->shouldReceive('chat')
                ->once()
                ->andThrow(new \Exception('Database exploded'));
        });

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->validPayload());

        $body = $this->captureStream($response);
        $events = $this->parseSseContent($body);

        $errorEvent = collect($events)->firstWhere('type', 'error');
        $this->assertNotNull($errorEvent);
        // Generic errors should NOT leak internal details to the client
        $this->assertStringNotContainsString('Database exploded', $errorEvent['data']['message']);
        $this->assertSame('An unexpected error occurred. Please try again.', $errorEvent['data']['message']);
    }

    // -------------------------------------------------------------------------
    // Throttle sanity (just ensure the route definition accepts the middleware)
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function route_is_registered_under_expected_name(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Route::has('assistant.chat'),
            'Route assistant.chat must exist'
        );
    }
}
