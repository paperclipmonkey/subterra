<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Which Pip modes each role may use. Ordinary Pip users get the trip importer
 * only — trip recommendations (the planner) are for platform admins.
 */
class PipModeAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['assistant.openrouter.api_key' => 'test-key']);
        Http::fake(['openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['role' => 'assistant', 'content' => 'OK'], 'finish_reason' => 'stop']],
        ])]);
    }

    public static function cases(): array
    {
        return [
            'pip user: importer' => ['pip', 'default', 200],
            'pip user: planner' => ['pip', 'plan', 403],
            'pip user: data steward' => ['pip', 'data', 403],
            'data admin with pip: importer' => ['data_admin', 'default', 200],
            'data admin with pip: planner' => ['data_admin', 'plan', 403],
            'data admin with pip: data steward' => ['data_admin', 'data', 200],
            'platform admin: importer' => ['platform_admin', 'default', 200],
            'platform admin: planner' => ['platform_admin', 'plan', 200],
            'platform admin: data steward' => ['platform_admin', 'data', 200],
        ];
    }

    #[Test]
    #[DataProvider('cases')]
    public function modes_are_restricted_by_role(string $role, string $mode, int $status): void
    {
        $user = match ($role) {
            'pip' => User::factory()->pipAccess()->pipAgreed()->create(),
            'data_admin' => User::factory()->dataAdmin()->pipAccess()->pipAgreed()->create(),
            'platform_admin' => User::factory()->admin()->pipAgreed()->create(),
        };

        $response = $this->actingAs($user)->postJson('/api/assistant/chat', [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'mode' => $mode,
        ]);

        $response->assertStatus($status);
        if ($status === 403 && $mode === 'plan') {
            $response->assertJson(['error' => 'Pip can only help you import your trips.']);
        }
    }

    #[Test]
    public function an_unknown_mode_is_rejected(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $this->actingAs($user)->postJson('/api/assistant/chat', [
            'messages' => [['role' => 'user', 'content' => 'Hello']],
            'mode' => 'anything',
        ])->assertStatus(422);
    }
}
