<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Events\UserContributed;
use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Trip;
use App\Models\User;
use App\Services\Assistant\Tools\Import\ImportReadyTripsTool;
use App\Services\Assistant\Tools\Import\ResolveCaveTool;
use App\Services\TripImport\TripImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

/**
 * The trip importer is Pip's default mode and the only one open to ordinary
 * users: these tests cover its guard rails.
 */
class AssistantImportModeTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/assistant/chat';

    private const IMPORT_TOOLS = [
        'get_import_status', 'add_trips', 'find_cave', 'resolve_cave', 'search_users',
        'resolve_person', 'update_trip_rows', 'manage_import', 'import_ready_trips',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.openrouter.api_key' => 'test-key']);
    }

    private function reply(string $content, array $toolCalls = [], int $tokens = 15): array
    {
        $message = ['role' => 'assistant', 'content' => $content];
        if ($toolCalls) {
            $message['tool_calls'] = $toolCalls;
        }

        return [
            'choices' => [['message' => $message, 'finish_reason' => $toolCalls ? 'tool_calls' : 'stop']],
            'usage' => ['prompt_tokens' => $tokens - 5, 'completion_tokens' => 5],
        ];
    }

    private function toolCall(string $name, array $args): array
    {
        return ['id' => 'call_'.$name, 'type' => 'function', 'function' => ['name' => $name, 'arguments' => json_encode($args)]];
    }

    private function fakeReply(string $content = 'OK'): void
    {
        Http::fake(['openrouter.ai/*' => Http::response($this->reply($content))]);
    }

    /** @return array<int, array{type: string, data: mixed}> */
    private function events($response): array
    {
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $startLevel = ob_get_level();
        // The controller ends one output buffer before streaming, so nest a
        // spare one underneath the buffer that collects the events.
        ob_start();
        ob_start();
        ob_start();
        $response->baseResponse->sendContent();
        $output = '';
        while (ob_get_level() > $startLevel) {
            $output = ob_get_clean().$output;
        }

        $events = [];
        foreach (explode("\n", $output) as $line) {
            if (str_starts_with($line, 'data: ')) {
                $events[] = json_decode(substr($line, 6), true);
            }
        }

        return $events;
    }

    private function user(): User
    {
        return User::factory()->pipAccess()->pipAgreed()->create();
    }

    private function chat(User $user, string $content = 'Help me import my trips', array $extra = [])
    {
        return $this->actingAs($user)->postJson(self::ENDPOINT, [
            'messages' => [['role' => 'user', 'content' => $content]],
        ] + $extra);
    }

    private function stageReadyTrip(User $user): void
    {
        $system = CaveSystem::factory()->create(['name' => 'Swildon\'s Hole', 'slug' => 'swildons-hole']);
        Cave::factory()->create(['name' => 'Swildon\'s Hole', 'slug' => 'swildons-hole', 'cave_system_id' => $system->id]);

        $service = $this->app->make(TripImportService::class);
        $service->addRows($service->openOrCreate($user, 'log.csv'), $user, [
            ['cave_name' => 'Swildon\'s Hole', 'date' => '2024-06-14', 'companions' => []],
        ], 'file');
    }

    #[Test]
    public function the_importer_offers_only_import_tools(): void
    {
        $this->fakeReply();

        $this->events($this->chat($this->user())->assertOk());

        [$request] = Http::recorded()->first();
        $names = array_map(fn ($t) => $t['function']['name'], $request->data()['tools']);
        $this->assertEqualsCanonicalizing(self::IMPORT_TOOLS, $names);
        $this->assertNotContains('search_caves', $names);
        $this->assertNotContains('get_weather_forecast', $names);
    }

    #[Test]
    public function the_system_prompt_is_scoped_to_importing_and_carries_the_import_state(): void
    {
        $this->fakeReply();
        $user = $this->user();
        $this->stageReadyTrip($user);

        $this->events($this->chat($user)->assertOk());

        [$request] = Http::recorded()->first();
        $prompt = $request->data()['messages'][0]['content'];
        $this->assertStringContainsString('Do NOT recommend caves', $prompt);
        $this->assertStringContainsString('1 rows — 1 ready', $prompt);
        $this->assertStringContainsString('"log.csv"', $prompt);
    }

    #[Test]
    public function the_importer_uses_its_own_request_limits_and_privacy_routing(): void
    {
        $this->fakeReply();

        $this->events($this->chat($this->user())->assertOk());

        [$request] = Http::recorded()->first();
        $this->assertSame(1024, $request->data()['max_tokens']);
        $this->assertSame(0.2, $request->data()['temperature']);
        $this->assertSame('deny', $request->data()['provider']['data_collection']);
    }

    #[Test]
    public function temperature_can_be_left_out_for_models_that_reject_it(): void
    {
        config(['assistant.openrouter.send_temperature' => false]);
        $this->fakeReply();

        $this->events($this->chat($this->user())->assertOk());

        [$request] = Http::recorded()->first();
        $this->assertArrayNotHasKey('temperature', $request->data());
    }

    #[Test]
    public function history_sent_to_the_model_is_capped(): void
    {
        $this->fakeReply();
        $messages = [];
        for ($i = 1; $i <= 29; ++$i) {
            $messages[] = ['role' => $i % 2 ? 'user' : 'assistant', 'content' => "Message {$i}"];
        }

        $this->events($this->actingAs($this->user())->postJson(self::ENDPOINT, ['messages' => $messages])->assertOk());

        [$request] = Http::recorded()->first();
        // System prompt + the last 12 messages
        $this->assertCount(13, $request->data()['messages']);
    }

    #[Test]
    public function a_conversation_is_limited_to_a_number_of_user_turns(): void
    {
        $this->fakeReply();
        $user = $this->user();
        $messages = fn (int $n) => array_map(fn ($i) => ['role' => 'user', 'content' => "Message {$i}"], range(1, $n));

        $this->actingAs($user)->postJson(self::ENDPOINT, ['messages' => $messages(16)])
            ->assertStatus(422)
            ->assertJson(['code' => 'conversation_limit']);

        $this->events($this->actingAs($user)->postJson(self::ENDPOINT, ['messages' => $messages(15)])->assertOk());
    }

    #[Test]
    public function a_user_is_stopped_once_they_spend_their_daily_token_budget(): void
    {
        config(['assistant.budget.daily_tokens' => 20]);
        $this->fakeReply(); // 15 tokens per call
        $user = $this->user();

        $this->events($this->chat($user)->assertOk());
        $this->events($this->chat($user)->assertOk());

        $this->chat($user)
            ->assertStatus(429)
            ->assertJson(['code' => 'daily_budget_exceeded']);
    }

    #[Test]
    public function platform_admins_are_exempt_from_the_token_budget(): void
    {
        config(['assistant.budget.daily_tokens' => 10]);
        $this->fakeReply();
        $admin = User::factory()->admin()->pipAgreed()->create();

        $this->events($this->chat($admin)->assertOk());
        $this->events($this->chat($admin)->assertOk());
    }

    #[Test]
    public function confirmed_import_creates_trips_and_emits_summary_events(): void
    {
        Event::fake([UserContributed::class]);
        $user = $this->user();
        $this->stageReadyTrip($user);

        Http::fake(['openrouter.ai/*' => Http::sequence()
            ->push($this->reply('', [$this->toolCall('import_ready_trips', ['confirmed' => true])]))
            ->push($this->reply('Saved 1 trip — see [your trips](/trips).')),
        ]);

        $events = $this->events($this->chat($user, 'Yes, import them')->assertOk());

        $this->assertSame(1, Trip::count());
        $types = array_column($events, 'type');
        $this->assertContains('trips_imported', $types);
        $imported = collect($events)->firstWhere('type', 'trips_imported')['data'];
        $this->assertSame(1, $imported['imported']);
        $this->assertSame('/trips', $imported['trips_url']);
        $this->assertSame('done', end($types));
    }

    #[Test]
    public function import_status_is_emitted_after_each_turn_while_an_import_is_open(): void
    {
        $this->fakeReply();
        $user = $this->user();
        $this->stageReadyTrip($user);

        $events = $this->events($this->chat($user)->assertOk());

        $status = collect($events)->firstWhere('type', 'import_status')['data'];
        $this->assertSame(1, $status['ready']);
        $this->assertSame('log.csv', $status['filename']);
    }

    #[Test]
    public function import_ready_trips_refuses_without_explicit_confirmation(): void
    {
        $user = $this->user();
        $this->stageReadyTrip($user);
        $tool = $this->app->make(ImportReadyTripsTool::class);

        $this->assertArrayHasKey('error', $tool->handle([], $user));
        $this->assertArrayHasKey('error', $tool->handle(['confirmed' => 'yes'], $user));
        $this->assertSame(0, Trip::count());
    }

    #[Test]
    public function import_tools_cannot_reach_another_users_import(): void
    {
        $owner = $this->user();
        $this->stageReadyTrip($owner);
        $other = $this->user();

        $result = $this->app->make(ResolveCaveTool::class)->handle([
            'resolutions' => [['row_numbers' => [1], 'cave_system_slug' => 'swildons-hole']],
        ], $other);
        $this->assertStringContainsString('no import in progress', $result['error']);

        $result = $this->app->make(ImportReadyTripsTool::class)->handle(['confirmed' => true], $other);
        $this->assertArrayHasKey('error', $result);
        $this->assertSame(0, Trip::count());
    }

    #[Test]
    public function trips_described_in_chat_are_staged_not_saved(): void
    {
        $user = $this->user();
        CaveSystem::factory()->create(['name' => 'Swildon\'s Hole', 'slug' => 'swildons-hole'])
            ->caves()->create(Cave::factory()->make(['name' => 'Swildon\'s Hole', 'slug' => 'swildons-hole'])->toArray());

        Http::fake(['openrouter.ai/*' => Http::sequence()
            ->push($this->reply('', [$this->toolCall('add_trips', ['trips' => [
                ['cave' => 'Swildons Hole', 'date' => '2024-06-14', 'companions' => ['Zed'], 'duration_minutes' => 180],
            ]])]))
            ->push($this->reply('Got it — 1 trip ready. Shall I import it?')),
        ]);

        $events = $this->events($this->chat($user, 'I did Swildons on 14 June 2024 with Zed, 3 hours')->assertOk());

        $this->assertSame(0, Trip::count());
        $this->assertSame(1, collect($events)->firstWhere('type', 'import_status')['data']['ready']);
    }
}
