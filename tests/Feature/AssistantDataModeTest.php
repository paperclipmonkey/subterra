<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AssistantDataModeTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/assistant/chat';

    private function dataPayload(): array
    {
        return [
            'messages' => [
                ['role' => 'user', 'content' => 'Give me a summary of data issues'],
            ],
            'mode' => 'data',
        ];
    }

    private function fakeOpenRouter(): void
    {
        config(['assistant.openrouter.api_key' => 'test-key']);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [
                    [
                        'message' => ['role' => 'assistant', 'content' => 'Here is the data health summary.'],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 5],
            ]),
        ]);
    }

    /** Drain a StreamedResponse so Http::recorded() is populated. */
    private function drainStream($response): void
    {
        $this->assertInstanceOf(StreamedResponse::class, $response->baseResponse);
        $startLevel = ob_get_level();
        ob_start();
        ob_start();
        ob_start();
        $response->baseResponse->sendContent();
        while (ob_get_level() > $startLevel) {
            ob_end_clean();
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function pip_user_without_admin_role_cannot_use_data_mode(): void
    {
        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, $this->dataPayload());

        $response->assertStatus(403);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function invalid_mode_is_rejected(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $this->actingAs($admin)
            ->postJson(self::ENDPOINT, [
                'messages' => [['role' => 'user', 'content' => 'hi']],
                'mode' => 'superuser',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function platform_admin_can_chat_in_data_mode(): void
    {
        $this->fakeOpenRouter();
        $admin = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, $this->dataPayload());

        $response->assertStatus(200);
        $this->drainStream($response);

        // The request offered the data-steward tool set and prompt
        $recorded = Http::recorded();
        $this->assertCount(1, $recorded);
        [$request] = $recorded->first();
        $body = $request->data();

        $toolNames = array_map(fn ($t) => $t['function']['name'], $body['tools']);
        $this->assertContains('scan_data_issues', $toolNames);
        $this->assertContains('propose_bulk_tag', $toolNames);
        $this->assertContains('propose_system_merge', $toolNames);
        $this->assertContains('create_collection', $toolNames);
        $this->assertContains('update_collection', $toolNames);
        $this->assertContains('delete_collection', $toolNames);
        $this->assertNotContains('create_trip_report', $toolNames);

        $this->assertStringContainsString('data-steward mode', $body['messages'][0]['content']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function data_mode_accepts_long_conversation_history(): void
    {
        $this->fakeOpenRouter();
        $admin = User::factory()->admin()->pipAgreed()->create();

        $messages = [];
        for ($i = 1; $i <= 40; ++$i) {
            $messages[] = ['role' => $i % 2 === 1 ? 'user' : 'assistant', 'content' => "Message {$i}"];
        }

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, ['messages' => $messages, 'mode' => 'data']);

        $response->assertStatus(200);
        $this->drainStream($response);

        // The full history (plus system prompt) reached the model — no 20-message cap
        [$request] = Http::recorded()->first();
        $this->assertCount(41, $request->data()['messages']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function default_mode_still_caps_history_at_20_messages(): void
    {
        $admin = User::factory()->admin()->pipAgreed()->create();

        $messages = [];
        for ($i = 1; $i <= 21; ++$i) {
            $messages[] = ['role' => 'user', 'content' => "Message {$i}"];
        }

        $this->actingAs($admin)
            ->postJson(self::ENDPOINT, ['messages' => $messages])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['messages']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function default_mode_does_not_offer_proposal_tools(): void
    {
        $this->fakeOpenRouter();
        $admin = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($admin)
            ->postJson(self::ENDPOINT, [
                'messages' => [['role' => 'user', 'content' => 'Suggest a cave']],
            ]);

        $response->assertStatus(200);
        $this->drainStream($response);

        [$request] = Http::recorded()->first();
        $toolNames = array_map(fn ($t) => $t['function']['name'], $request->data()['tools']);
        $this->assertNotContains('propose_data_fix', $toolNames);
        $this->assertNotContains('scan_data_issues', $toolNames);
        $this->assertNotContains('create_collection', $toolNames);
        $this->assertNotContains('delete_collection', $toolNames);
        $this->assertContains('search_caves', $toolNames);
    }
}
