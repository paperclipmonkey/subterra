<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant;

use App\Models\CaveSystem;
use App\Models\User;
use App\Services\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AssistantServiceTest extends TestCase
{
    use RefreshDatabase;

    private const OPENROUTER_URL = 'openrouter.ai/*';

    private AssistantService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'assistant.openrouter.api_key'      => 'test-key',
            'assistant.openrouter.base_url'     => 'https://openrouter.ai/api/v1',
            'assistant.openrouter.model'        => 'anthropic/claude-3-5-haiku',
            'assistant.openrouter.max_tokens'   => 2048,
            'assistant.openrouter.temperature'  => 0.7,
            'assistant.limits.max_history_messages' => 20,
            'assistant.limits.max_tool_iterations'  => 5,
        ]);

        $this->service = $this->app->make(AssistantService::class);
    }

    private function openRouterReply(string $content, array $toolCalls = []): array
    {
        $message = ['role' => 'assistant', 'content' => $content];

        if (!empty($toolCalls)) {
            $message['tool_calls'] = $toolCalls;
        }

        return [
            'choices' => [['message' => $message]],
        ];
    }

    private function toolCallEntry(string $id, string $name, array $args = []): array
    {
        return [
            'id'       => $id,
            'type'     => 'function',
            'function' => [
                'name'      => $name,
                'arguments' => json_encode($args),
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // API key guard
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_runtime_exception_when_api_key_is_missing(): void
    {
        config(['assistant.openrouter.api_key' => null]);

        $user = User::factory()->create();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenRouter API key is not configured');

        $this->service->chat(
            [['role' => 'user', 'content' => 'Hello']],
            $user
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_runtime_exception_when_api_key_is_empty_string(): void
    {
        config(['assistant.openrouter.api_key' => '']);

        $user = User::factory()->create();

        $this->expectException(\RuntimeException::class);

        $this->service->chat(
            [['role' => 'user', 'content' => 'Hello']],
            $user
        );
    }

    // -------------------------------------------------------------------------
    // Basic conversation
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_assistant_content_from_openrouter(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply('Here are some great caves!'),
                200
            ),
        ]);

        $result = $this->service->chat(
            [['role' => 'user', 'content' => 'Suggest a cave']],
            $user
        );

        $this->assertSame('Here are some great caves!', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function returns_fallback_message_when_content_is_empty(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply(''),
                200
            ),
        ]);

        $result = $this->service->chat(
            [['role' => 'user', 'content' => 'Suggest a cave']],
            $user
        );

        $this->assertStringContainsString('unable to generate', $result);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function throws_on_openrouter_http_error(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::response(null, 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('temporarily unavailable');

        $this->service->chat(
            [['role' => 'user', 'content' => 'Suggest a cave']],
            $user
        );
    }

    // -------------------------------------------------------------------------
    // History capping
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function caps_message_history_to_configured_limit(): void
    {
        config(['assistant.limits.max_history_messages' => 4]);

        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply('Response'),
                200
            ),
        ]);

        // Send 10 user messages (exceeds the limit of 4)
        $messages = [];
        for ($i = 1; $i <= 10; $i++) {
            $messages[] = ['role' => 'user', 'content' => "Message {$i}"];
        }

        $this->service->chat($messages, $user);

        // The system prompt is always prepended (+1), then at most 4 history messages
        Http::assertSent(function ($request) {
            $body     = json_decode($request->body(), true);
            $messages = $body['messages'] ?? [];
            // System message + max 4 history = 5 total
            return count($messages) <= 5;
        });
    }

    // -------------------------------------------------------------------------
    // Tool dispatch loop
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function dispatches_tool_calls_and_returns_final_content(): void
    {
        $user   = User::factory()->create();
        $system = CaveSystem::factory()->create(['name' => 'Gaping Gill', 'slug' => 'gaping-gill']);

        Http::fake([
            self::OPENROUTER_URL => Http::sequence()
                // First call: model requests get_user_experience tool
                ->push($this->openRouterReply('', [
                    $this->toolCallEntry('call_001', 'get_user_experience', []),
                ]), 200)
                // Second call: model returns final text after seeing tool result
                ->push($this->openRouterReply('Based on your experience, I recommend Gaping Gill.'), 200),
        ]);

        $result = $this->service->chat(
            [['role' => 'user', 'content' => 'What cave should I do next?']],
            $user
        );

        $this->assertSame('Based on your experience, I recommend Gaping Gill.', $result);

        // Verify two requests were made to OpenRouter (one for tool call, one for final answer)
        Http::assertSentCount(2);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function unknown_tool_name_returns_error_in_tool_result(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::sequence()
                ->push($this->openRouterReply('', [
                    $this->toolCallEntry('call_001', 'nonexistent_tool', []),
                ]), 200)
                ->push($this->openRouterReply('Apologies, I hit an error.'), 200),
        ]);

        // Should not throw — unknown tools are handled gracefully
        $result = $this->service->chat(
            [['role' => 'user', 'content' => 'Do something']],
            $user
        );

        $this->assertIsString($result);

        // Verify a tool role message was added to the context (error result)
        Http::assertSent(function ($request) {
            $body     = json_decode($request->body(), true);
            $messages = $body['messages'] ?? [];
            foreach ($messages as $msg) {
                if (($msg['role'] ?? '') === 'tool') {
                    $content = json_decode($msg['content'] ?? '{}', true);

                    return isset($content['error']) && str_contains($content['error'], 'Unknown tool');
                }
            }

            return false;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function respects_max_tool_iterations(): void
    {
        config(['assistant.limits.max_tool_iterations' => 2]);

        $user = User::factory()->create();

        // Model always returns a tool call, never a final text answer
        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply('', [
                    $this->toolCallEntry('call_001', 'get_user_experience', []),
                ]),
                200
            ),
        ]);

        // Should stop after 2 iterations and return whatever content it has (empty → fallback)
        $result = $this->service->chat(
            [['role' => 'user', 'content' => 'Suggest something']],
            $user
        );

        $this->assertIsString($result);
        Http::assertSentCount(2);
    }

    // -------------------------------------------------------------------------
    // SSE event callbacks
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function emits_thinking_event_before_first_openrouter_call(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply('Some response'),
                200
            ),
        ]);

        $events = [];
        $this->service->chat(
            [['role' => 'user', 'content' => 'Hello']],
            $user,
            function (string $type, mixed $data) use (&$events) {
                $events[] = ['type' => $type, 'data' => $data];
            }
        );

        $types = array_column($events, 'type');
        $this->assertContains('thinking', $types);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function emits_tool_call_running_and_done_events(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::sequence()
                ->push($this->openRouterReply('', [
                    $this->toolCallEntry('call_001', 'get_user_experience', []),
                ]), 200)
                ->push($this->openRouterReply('All done.'), 200),
        ]);

        $events = [];
        $this->service->chat(
            [['role' => 'user', 'content' => 'Tell me about my experience']],
            $user,
            function (string $type, mixed $data) use (&$events) {
                $events[] = ['type' => $type, 'data' => $data];
            }
        );

        $toolEvents = array_filter($events, fn ($e) => $e['type'] === 'tool_call');
        $statuses   = array_column(array_column(array_values($toolEvents), 'data'), 'status');

        $this->assertContains('running', $statuses);
        $this->assertContains('done', $statuses);
    }

    // -------------------------------------------------------------------------
    // Safety alert injection
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function injects_safety_alert_when_river_gauge_is_high(): void
    {
        $user = User::factory()->create();

        $weatherToolResult = [
            'cave_name'    => 'Bull Pot of the Witches',
            'river_gauges' => [
                ['name' => 'River Ribble at Settle', 'state' => 'High', 'latest_value' => 1.8],
            ],
        ];

        Http::fake([
            self::OPENROUTER_URL => Http::sequence()
                ->push($this->openRouterReply('', [
                    $this->toolCallEntry('call_001', 'get_weather_forecast', ['cave_id' => 999]),
                ]), 200)
                ->push($this->openRouterReply('SAFETY WARNING: flooding risk detected.'), 200),
        ]);

        // Use an anonymous subclass override so definition() remains callable as static
        $fakeWeatherTool = new class ($weatherToolResult) extends \App\Services\Assistant\Tools\GetWeatherForecastTool {
            public function __construct(private readonly array $fakeResult) {
                // Skip parent DI
            }

            public function handle(array $arguments, \App\Models\User $user): array
            {
                return $this->fakeResult;
            }
        };

        $this->app->instance(\App\Services\Assistant\Tools\GetWeatherForecastTool::class, $fakeWeatherTool);
        $service = $this->app->make(AssistantService::class);

        $service->chat(
            [['role' => 'user', 'content' => 'Is Bull Pot safe?']],
            $user
        );

        // Check all recorded requests for the injected safety alert
        $safetyAlertFound = false;
        foreach (Http::recorded() as [$request]) {
            $body = json_decode($request->body(), true);
            foreach ($body['messages'] ?? [] as $msg) {
                if (($msg['role'] ?? '') === 'system' && str_contains($msg['content'] ?? '', '[SAFETY ALERT]')) {
                    $safetyAlertFound = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($safetyAlertFound, 'Safety alert should have been injected for a High river gauge');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function does_not_inject_safety_alert_when_gauges_are_normal(): void
    {
        $user = User::factory()->create();

        $weatherToolResult = [
            'cave_name'    => 'Swildon\'s Hole',
            'river_gauges' => [
                ['name' => 'River Mells', 'state' => 'Normal', 'latest_value' => 0.4],
            ],
        ];

        Http::fake([
            self::OPENROUTER_URL => Http::sequence()
                ->push($this->openRouterReply('', [
                    $this->toolCallEntry('call_001', 'get_weather_forecast', ['cave_id' => 999]),
                ]), 200)
                ->push($this->openRouterReply('Conditions look good.'), 200),
        ]);

        $fakeWeatherTool = new class ($weatherToolResult) extends \App\Services\Assistant\Tools\GetWeatherForecastTool {
            public function __construct(private readonly array $fakeResult) {}

            public function handle(array $arguments, \App\Models\User $user): array
            {
                return $this->fakeResult;
            }
        };

        $this->app->instance(\App\Services\Assistant\Tools\GetWeatherForecastTool::class, $fakeWeatherTool);
        $service = $this->app->make(AssistantService::class);

        $service->chat(
            [['role' => 'user', 'content' => 'Is Swildon\'s safe?']],
            $user
        );

        // Verify NO request contained a safety alert
        $safetyAlertFound = false;
        foreach (Http::recorded() as [$request]) {
            $body = json_decode($request->body(), true);
            foreach ($body['messages'] ?? [] as $msg) {
                if (($msg['role'] ?? '') === 'system' && str_contains($msg['content'] ?? '', '[SAFETY ALERT]')) {
                    $safetyAlertFound = true;
                    break 2;
                }
            }
        }
        $this->assertFalse($safetyAlertFound, 'No safety alert should have been injected for normal river gauges');
    }

    // -------------------------------------------------------------------------
    // OpenRouter request structure
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function sends_authorization_header_to_openrouter(): void
    {
        $user = User::factory()->create();
        config(['assistant.openrouter.api_key' => 'sk-or-test-12345']);

        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply('OK'),
                200
            ),
        ]);

        $this->service->chat(
            [['role' => 'user', 'content' => 'Hi']],
            $user
        );

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer sk-or-test-12345');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function includes_system_prompt_as_first_message(): void
    {
        $user = User::factory()->create(['name' => 'Alice Caver']);

        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply('OK'),
                200
            ),
        ]);

        $this->service->chat(
            [['role' => 'user', 'content' => 'Hi']],
            $user
        );

        Http::assertSent(function ($request) {
            $body     = json_decode($request->body(), true);
            $messages = $body['messages'] ?? [];
            $first    = $messages[0] ?? [];

            return ($first['role'] ?? '') === 'system'
                && str_contains($first['content'] ?? '', 'Vern')
                && str_contains($first['content'] ?? '', 'Alice Caver');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function sends_tool_definitions_to_openrouter(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply('OK'),
                200
            ),
        ]);

        $this->service->chat(
            [['role' => 'user', 'content' => 'Hi']],
            $user
        );

        Http::assertSent(function ($request) {
            $body  = json_decode($request->body(), true);
            $tools = $body['tools'] ?? [];

            $names = array_column(array_column($tools, 'function'), 'name');

            return in_array('get_user_experience', $names)
                && in_array('search_caves', $names)
                && in_array('get_cave_details', $names)
                && in_array('get_weather_forecast', $names)
                && in_array('get_upcoming_permits', $names)
                && in_array('list_routes', $names)
                && in_array('find_nearby_huts', $names)
                && in_array('get_cave_system_activity', $names);
        });
    }

    // -------------------------------------------------------------------------
    // Suggestions event
    // -------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\Test]
    public function emits_suggestions_event_after_tool_use(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::sequence()
                ->push($this->openRouterReply('', [
                    $this->toolCallEntry('call_001', 'search_caves', ['region' => 'Yorkshire']),
                ]), 200)
                ->push($this->openRouterReply('Here are some Yorkshire caves.'), 200),
        ]);

        $events = [];
        $this->service->chat(
            [['role' => 'user', 'content' => 'Find me a Yorkshire cave']],
            $user,
            function (string $type, mixed $data) use (&$events) {
                $events[] = ['type' => $type, 'data' => $data];
            }
        );

        $types = array_column($events, 'type');
        $this->assertContains('suggestions', $types, 'A suggestions event should be emitted after tool use');

        $suggestionsEvent = collect($events)->firstWhere('type', 'suggestions');
        $this->assertIsArray($suggestionsEvent['data']);
        $this->assertNotEmpty($suggestionsEvent['data']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function does_not_emit_suggestions_when_no_tools_used(): void
    {
        $user = User::factory()->create();

        Http::fake([
            self::OPENROUTER_URL => Http::response(
                $this->openRouterReply('Great question! Caves are cool.'),
                200
            ),
        ]);

        $events = [];
        $this->service->chat(
            [['role' => 'user', 'content' => 'Tell me something about caves in general']],
            $user,
            function (string $type, mixed $data) use (&$events) {
                $events[] = ['type' => $type, 'data' => $data];
            }
        );

        $types = array_column($events, 'type');
        $this->assertNotContains('suggestions', $types, 'No suggestions event when no tools were called');
    }
}
